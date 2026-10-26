<?php

namespace App\Http\Controllers;

use App\Mail\ConsultationBookedMail;
use App\Mail\ConsultationCancelledMail;
use App\Models\CapstoneExpert;
use App\Models\ConsultationAvailability;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSchedule;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\GoogleCalendarService;
use App\Services\Notifier;
use App\Support\ConsultationBoard;
use App\Support\ConsultationTopics;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class StudentConsultationController extends Controller
{
    /**
     * How near a session can get before it is too late to call it off.
     *
     * Public because the booking page states the rule, and a page that says
     * a different number from the one enforced is worse than one that says
     * nothing.
     */
    public const CANCEL_CUTOFF_DAYS = 3;

    public function __construct(private GoogleCalendarService $calendar, private ConsultationBoard $board) {}

    // Polled by the Experts page every 30s (and when the tab comes back into
    // view) so a new slot the expert adds, or a booking someone else on the
    // team makes or cancels, shows up without reloading the whole page.
    public function refreshData()
    {
        $data = $this->board->forStudent(Auth::user());

        return response()->json([
            'availableSlots' => $data['availableSlots'],
            'fullOccurrences' => $this->board->fullOccurrences($data['availableSlots']),
            'bookedExpertIds' => $data['bookedExpertIds'],
            'bookingWindows' => $data['bookingWindows'],
            'myBookedDates' => $data['upcomingBookings']->merge($data['recentBookings'])
                ->filter(fn ($b) => $b->session_date)
                ->map(fn ($b) => [
                    'date' => $b->session_date->format('Y-m-d'),
                    'expert_id' => $b->schedule?->expert_id,
                ])->values(),
            'upcomingBookings' => $data['upcomingBookings']->map(fn ($b) => $this->board->cardData($b)),
            'recentBookings' => $data['recentBookings']->map(fn ($b) => $this->board->cardData($b)),
        ]);
    }

    /**
     * POST /student/consultation/book
     *
     * One booking at a time per expert. Checking a slot's capacity and then
     * inserting the booking are two steps; two students booking the same
     * one-person slot at the same moment both passed the check and both got
     * in (performance test PT-04). The tables are MyISAM, so there is no
     * transaction to lock a row in - a cache lock, held across every web
     * worker, lines the requests up instead.
     */
    public function book(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer|exists:consultation_schedule,schedule_id',
            'session_date' => 'nullable|date',
        ]);

        $expertId = ConsultationSchedule::where('schedule_id', $request->integer('schedule_id'))->value('expert_id');

        try {
            return Cache::lock('consultation-booking:expert:'.$expertId, 20)
                ->block((int) config('capstrack.booking_lock_wait', 10), fn () => $this->bookSlot($request));
        } catch (LockTimeoutException) {
            return response()->json([
                'success' => false,
                'message' => 'Many students are booking this expert right now. Try again in a moment.',
            ], 503);
        }
    }

    private function bookSlot(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer|exists:consultation_schedule,schedule_id',
            'session_date' => 'nullable|date',
            'topics' => 'nullable|array',
            'topics.*' => 'string|in:'.implode(',', array_keys(ConsultationTopics::LABELS)),
            'note' => 'nullable|string|max:500',
            'deliverables' => 'nullable|array|max:10',
            'deliverables.*' => 'nullable|string|max:120',
        ], [
            'note.max' => 'Keep the note to 500 characters.',
            'deliverables.max' => 'Pick at most 10 deliverables.',
            'deliverables.*.max' => 'Keep each deliverable to 120 characters.',
        ]);

        // Refresh statuses first so a session that just ended (but whose row
        // hasn't been touched since) doesn't wrongly count as still active
        // below.
        ConsultationBooking::markPastAsDone();

        $student = Auth::user();

        $studentYear = StudentSchoolYear::where('student_id', $student->student_id)
            ->orderByDesc('school_year')
            ->first();

        $team = $studentYear ? Team::where('team_id', $studentYear->team_id)->first() : null;

        $slot = ConsultationSchedule::find($request->schedule_id);
        if (! $slot || ! $slot->is_available) {
            return response()->json(['success' => false, 'message' => 'This slot is no longer available.'], 422);
        }

        // One live booking per expert, not one per team.
        //
        // This used to refuse any second booking at all, across every expert
        // - so a team waiting to see their adviser on Thursday could not book
        // their consultant for Friday, which are different people about
        // different things. What it should stop is booking the same person
        // twice while the first session is still ahead of them.
        //
        // Frees up on its own once that session is cancelled or has passed
        // (markPastAsDone flips it to 'done' above), so there is no separate
        // release step.
        if ($team) {
            $alreadyWithThisExpert = ConsultationBooking::where('booking_status', 'scheduled')
                ->where('team_id', $team->team_id)
                ->whereHas('schedule', fn ($q) => $q->where('expert_id', $slot->expert_id))
                ->exists();

            if ($alreadyWithThisExpert) {
                $who = CapstoneExpert::find($slot->expert_id)?->name;

                return response()->json([
                    'success' => false,
                    'message' => 'Your team already has a session booked with '
                        .($who ?: 'this expert').'. You can book them again once that one is done.',
                ], 422);
            }
        }

        // What the team will bring, from what this expert covers for them:
        // the grammarian always checks the manuscript, and the adviser and
        // consultant each cover two things, of which the team picks one or
        // both. An expert the team has no recorded role with takes whatever
        // was sent, which may be nothing.
        $roles = ConsultationTopics::rolesOf((int) $slot->expert_id, $team?->team_id);
        $choices = ConsultationTopics::choicesFor($roles);
        $topics = array_values(array_unique((array) $request->input('topics', [])));

        if ($fixed = ConsultationTopics::fixedFor($roles)) {
            $topics = [$fixed];
        } elseif ($choices && $request->has('topics')) {
            // Asked only of a page that offers the choice: one open from
            // before it did sends no topics at all, and still books.
            $topics = array_values(array_intersect($choices, $topics));

            if (! $topics) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pick what you will consult about: '.implode(' or ', array_map(fn ($t) => ConsultationTopics::LABELS[$t], $choices)).'.',
                ], 422);
            }
        }

        // Which deliverables the team is bringing, picked from its checklist
        // or typed. Asked only of a page that offers the field, like topics.
        $deliverables = collect((array) $request->input('deliverables', []))
            ->map(fn ($d) => trim((string) $d))
            ->filter()
            ->unique(fn ($d) => mb_strtolower($d))
            ->values()
            ->all();

        if ($request->has('deliverables') && ! $deliverables) {
            return response()->json(['success' => false, 'message' => 'Pick or type at least one deliverable.'], 422);
        }

        // The date the student actually clicked in the calendar. Recurring
        // slots share one schedule row across every future occurrence of
        // that weekday, so without this the booking always silently landed
        // on "whichever occurrence is nearest today" regardless of which one
        // was picked — falls back to that only for older clients that don't
        // send it yet.
        $sessionDate = $request->session_date ?? $slot->nextOccurrenceOnOrAfter();

        if (! $sessionDate || ! $slot->isValidOccurrenceDate($sessionDate)) {
            return response()->json(['success' => false, 'message' => 'That date is not a valid occurrence of this slot.'], 422);
        }

        // The page never offers a time that has already ended, but a request
        // can still name one directly.
        if (Carbon::parse($sessionDate.' '.($slot->end_time ?: '23:59:59'))->isPast()) {
            return response()->json(['success' => false, 'message' => 'That time has already passed. Pick a later date or time.'], 422);
        }

        // Scoped by session_date, not just schedule_id — a recurring slot's
        // schedule_id is shared across every future occurrence, so a student
        // booking a later date on the same weekly slot is a different
        // booking, not a duplicate of one they already made for an earlier
        // occurrence.
        $existing = ConsultationBooking::where('student_id', $student->student_id)
            ->where('schedule_id', $request->schedule_id)
            ->whereDate('session_date', $sessionDate)
            ->where('booking_status', 'scheduled')
            ->exists();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'You already booked this date.'], 422);
        }

        // The expert's scheduling window: how far ahead, how much notice, and
        // between which dates they take bookings.
        $availability = ConsultationAvailability::where('expert_id', $slot->expert_id)->first();

        if ($availability && ! $availability->allows($sessionDate, (string) $slot->start_time)) {
            return response()->json(['success' => false, 'message' => 'That time is outside when this expert takes bookings. Pick another date.'], 422);
        }

        // How many consultations this expert accepts in a single day.
        if ($availability && $availability->max_per_day) {
            $thatDay = ConsultationBooking::whereDate('session_date', $sessionDate)
                ->where('booking_status', 'scheduled')
                ->whereHas('schedule', fn ($q) => $q->where('expert_id', $slot->expert_id))
                ->count();

            if ($thatDay >= $availability->max_per_day) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your expert is fully booked on that day. Pick another date.',
                ], 422);
            }
        }

        // Recurring (weekly/biweekly) slots share a single schedule row
        // across every future occurrence of that weekday, so capacity has
        // to be checked per calendar date rather than per row — otherwise
        // one booking would exhaust every future occurrence at once.
        $limit = max(1, (int) ($slot->consultation_limit ?? 1));
        $bookedCount = ConsultationBooking::where('schedule_id', $slot->schedule_id)
            ->whereDate('session_date', $sessionDate)
            ->where('booking_status', 'scheduled')
            ->count();

        if ($bookedCount >= $limit) {
            return response()->json(['success' => false, 'message' => 'This slot is fully booked for that date.'], 422);
        }

        // Every booking gets its own event on the expert's Google Calendar, on
        // the date booked. Open slots are never put there, so the expert's
        // calendar shows booked consultations and nothing else.
        $googleEventId = null;

        if ($team && $slot->expert && $slot->expert->google_token) {
            try {
                $googleEventId = $this->calendar->createBookingEvent(
                    expertId: $slot->expert_id,
                    date: $sessionDate,
                    startTime: $slot->start_time,
                    endTime: $slot->end_time,
                    slotType: $slot->slot_type ? ucfirst($slot->slot_type) : 'Consultation',
                    teamName: $team->team_name,
                    studentEmail: $student->email,
                    expertEmail: $slot->expert->email,
                    topic: "Consultation booked by team: {$team->team_name}",
                );
            } catch (\Exception $e) {
                \Log::warning('Google Calendar event creation failed', ['error' => $e->getMessage()]);
            }
        }

        $booking = ConsultationBooking::create([
            'student_id' => $student->student_id,
            'team_id' => $team?->team_id,
            'schedule_id' => $request->schedule_id,
            'session_date' => $sessionDate,
            'booking_status' => 'scheduled',
            'google_event_id' => $googleEventId,
            'topics' => $topics ?: null,
            'note' => trim((string) $request->input('note')) ?: null,
            'deliverables' => $deliverables ?: null,
        ]);

        // A "once" slot only ever has this single occurrence, so once it's
        // full it's safe to flip the shared row off. Recurring slots must
        // stay available — other future occurrences of the same weekday are
        // still bookable — which is why they're deliberately left alone here
        // and instead gated per-date via ConsultationBoard::fullOccurrences().
        if ($slot->recur === 'once' && $bookedCount + 1 >= $limit) {
            ConsultationSchedule::where('schedule_id', $slot->schedule_id)
                ->update(['is_available' => false]);
        }

        if ($team && $slot->expert) {
            $this->notifyTeamOfBooking($booking, $slot->expert, $team);
        }

        // Fresh occurrence state so the frontend can update the calendar and
        // booking lists in place instead of reloading the page.
        $availableSlots = ConsultationSchedule::where('expert_id', $slot->expert_id)->get();

        return response()->json([
            'success' => true,
            'booking_id' => $booking->booking_id,
            'fullOccurrences' => $this->board->fullOccurrences($availableSlots),
            'newBooking' => [
                'expert_name' => trim(($slot->expert?->first_name ?? 'Expert').' '.($slot->expert?->last_name ?? '')),
                'slot_type' => $slot->slot_type ?? 'consult',
                'session_date' => $sessionDate,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'team_name' => $team->team_name ?? 'N/A',
                'topics' => $booking->topicsLabel(),
                'note' => $booking->note,
                'deliverables' => $booking->deliverablesLabel(),
            ],
        ]);
    }

    /**
     * One email that a consultation was booked - to the expert, copied to the
     * team.
     *
     * It used to send a separate copy to every member and the expert, so a
     * four-person team meant five emails saying the same thing.
     */
    private function notifyTeamOfBooking(ConsultationBooking $booking, $expert, Team $team): void
    {
        $members = $team->members->pluck('email')->filter()->reject(fn ($e) => $e === $expert->email)->unique()->values();

        Notifier::toExpert(
            $expert,
            'consultation_booked',
            'Consultation booked',
            '<strong>'.e($team->team_name).'</strong> booked a consultation on '
                .$booking->session_date->format('D, j M Y')
                .($booking->topics ? ', about '.e($booking->topicsLabel()) : '').'.',
            route('instructor.schedule'),
            ['team_id' => $team->team_id, 'booking_id' => $booking->booking_id],
        );

        try {
            // An expert who has turned this email off still gets the desktop
            // notification above, and the team still gets their own copy.
            $mail = new ConsultationBookedMail($booking, $expert, $team);

            if ($expert->wants('consultation_booked', 'email')) {
                Mail::to($expert->email)->cc($members->all())->queue($mail);
            } elseif ($members->isNotEmpty()) {
                Mail::to($members->all())->queue($mail);
            }
        } catch (\Exception $e) {
            \Log::warning('Consultation booked email failed', ['error' => $e->getMessage()]);
        }
    }

    // Emails the assigned expert immediately (not queued behind a worker)
    // that a booked session was cancelled, including the student's reason -
    // this is the only notification channel to instructors/experts at all,
    // since there's no in-app notification system on that side.
    private function notifyExpertOfCancellation(ConsultationBooking $booking, $expert, ?Team $team, $student, string $reason): void
    {
        $studentName = trim(($student->f_name ?? '').' '.($student->l_name ?? '')) ?: $student->email;

        if (! $expert->wants('consultation_cancelled', 'email')) {
            return;
        }

        try {
            Mail::to($expert->email)->send(new ConsultationCancelledMail($booking, $expert, $team, $studentName, $reason));
        } catch (\Exception $e) {
            \Log::warning('Consultation cancelled email failed', ['error' => $e->getMessage()]);
        }
    }

    public function cancel(Request $request, $bookingId)
    {
        // A plain $request->validate() would redirect-back on failure
        // instead of returning JSON, since this app's exception handler
        // only forces JSON rendering for /api/* routes - this endpoint is
        // called via fetch() and can't follow a redirect, so the error is
        // built manually here to match every other JSON response below.
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('reason'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $student = Auth::user();

        // Any teammate can cancel it, not just whoever originally booked it
        // - a consultation is booked once per team, and now that every
        // member sees it in their own list too, only the original booker
        // being able to cancel would be a confusing dead end for everyone
        // else.
        $studentYear = StudentSchoolYear::where('student_id', $student->student_id)
            ->orderByDesc('school_year')
            ->first();
        // Only the team the consultation was booked for can call it off.
        $booking = ConsultationBooking::where('booking_id', $bookingId)
            ->forTeam($studentYear?->team_id)
            ->where('booking_status', 'scheduled')
            ->firstOrFail();

        // Too close to call off. An expert who has kept the time free
        // deserves more notice than a day, and a team pulling out the night
        // before leaves a slot nobody else can take.
        $daysAway = now()->startOfDay()->diffInDays(
            Carbon::parse($booking->session_date)->startOfDay(),
            false
        );

        if ($daysAway < self::CANCEL_CUTOFF_DAYS) {
            return response()->json([
                'success' => false,
                'message' => 'This consultation is in '.($daysAway <= 0 ? 'less than a day' : $daysAway.' day'.($daysAway === 1 ? '' : 's'))
                    .'. It can only be cancelled more than '.self::CANCEL_CUTOFF_DAYS.' days ahead - message your expert instead.',
            ], 422);
        }

        if ($booking->cancellation_requested_at) {
            return response()->json([
                'success' => false,
                'message' => 'You already asked to cancel this. Your expert will approve or decline it.',
            ], 422);
        }

        // The expert decides. The booking stays on and the slot stays held
        // until they approve it - see Instructor\ScheduleController::updateCancellation.
        $booking->update([
            'cancellation_reason' => $validated['reason'],
            'cancellation_requested_at' => now(),
            'cancellation_requested_by' => trim(($student->f_name ?? '').' '.($student->l_name ?? '')) ?: $student->email,
        ]);

        // Nothing told the expert a team wanted out. The booking stays on
        // their calendar until they approve it, so if they never look at the
        // page the slot is held for a session nobody is coming to.
        $who = $booking->team?->team_name
            ?: (trim(($student->f_name ?? '').' '.($student->l_name ?? '')) ?: $student->email);

        Notifier::toExpert(
            $booking->schedule?->expert,
            'consultation_cancelled',
            'Cancellation requested',
            '<strong>'.e($who).'</strong> asked to call off the '
                .$booking->session_date->format('D, j M Y').' consultation.',
            route('instructor.schedule'),
            ['booking_id' => $booking->booking_id],
        );

        return response()->json(['success' => true]);
    }
}
