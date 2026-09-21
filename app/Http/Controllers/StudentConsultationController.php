<?php

namespace App\Http\Controllers;

use App\Mail\ConsultationBookedMail;
use App\Mail\ConsultationCancelledMail;
use App\Models\CapstoneExpert;
use App\Models\ConsultationSchedule;
use App\Models\ConsultationAvailability;
use App\Models\ConsultationBooking;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\GoogleCalendarService;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

    public function __construct(private GoogleCalendarService $calendar) {}

    public function index()
    {
        $student = Auth::user();
        $data = $this->loadConsultationData($student);

        return view('student.consultation', [
            'experts' => $data['experts'],
            'defaultExpertId' => $data['defaultExpertId'],
            'availableSlots' => $data['availableSlots'],
            'fullOccurrences' => $this->computeFullOccurrences($data['availableSlots']),
            'upcomingBookings' => $data['upcomingBookings'],
            'recentBookings' => $data['recentBookings'],
            'teamId' => $data['teamId'],
            'student' => $student,
            'bookedExpertIds' => $data['bookedExpertIds'],
            'bookingWindows' => $data['bookingWindows'],
        ]);
    }

    // Polled by the page every 30s (and on demand via a manual refresh
    // button) so a new slot the expert adds, or a booking someone else on
    // the team makes/cancels, shows up without the student needing to
    // reload the whole page.
    public function refreshData()
    {
        $student = Auth::user();
        $data = $this->loadConsultationData($student);

        return response()->json([
            'availableSlots' => $data['availableSlots'],
            'fullOccurrences' => $this->computeFullOccurrences($data['availableSlots']),
            'bookedExpertIds' => $data['bookedExpertIds'],
            'bookingWindows' => $data['bookingWindows'],
            'myBookedDates' => $data['upcomingBookings']->merge($data['recentBookings'])
                ->filter(fn ($b) => $b->session_date)
                ->map(fn ($b) => [
                    'date' => $b->session_date->format('Y-m-d'),
                    'expert_id' => $b->schedule?->expert_id,
                ])->values(),
            'upcomingBookings' => $data['upcomingBookings']->map(fn ($b) => $this->bookingCardData($b)),
            'recentBookings' => $data['recentBookings']->map(fn ($b) => $this->bookingCardData($b)),
        ]);
    }

    private function bookingCardData(ConsultationBooking $b): array
    {
        return [
            'booking_id' => $b->booking_id,
            'expert_name' => trim(($b->schedule?->expert?->first_name ?? 'Expert').' '.($b->schedule?->expert?->last_name ?? '')),
            'slot_type' => $b->schedule?->slot_type ?? 'consult',
            'session_date' => $b->session_date?->format('Y-m-d'),
            'available_date' => $b->schedule?->available_date,
            'start_time' => $b->schedule?->start_time,
            'end_time' => $b->schedule?->end_time,
            'team_name' => $b->team->team_name ?? 'N/A',
            'cancel_requested' => $b->cancellation_requested_at !== null,
        ];
    }

    private function loadConsultationData($student): array
    {
        ConsultationBooking::markPastAsDone();

        $studentId = $student->student_id;

        $studentYear = StudentSchoolYear::where('student_id', $studentId)
            ->orderByDesc('school_year')
            ->first();

        $teamId = $studentYear?->team_id;

        // Only experts actually assigned to this student's own team — via an
        // accepted capstone_expert_application row for their team_id — not
        // every approved expert in the system (which was pulling in admins/
        // instructors with no relationship to this student at all).
        $experts = collect();
        if ($teamId) {
            // An expert can hold more than one of these roles at once (e.g.
            // both Adviser and Consultant for the same team) - the join
            // below still returns one row per (expert, role) pair, so
            // they're grouped back into a single entry per expert with a
            // combined role label. Without this, the same person shows up
            // as two separate options in the picker below.
            $rows = DB::table('capstone_expert_application as a')
                ->join('capstone_experts as e', 'e.expert_id', '=', 'a.expert_id')
                // Through the application, not the expert. Joining on
                // er.expert_id asks "what roles does this person hold
                // anywhere", so an expert who is an adviser to one team and
                // a consultant to another showed up to THIS team as both.
                // a.expert_role_id is the role they were engaged as here.
                ->join('expert_roles as er', 'er.expert_role_id', '=', 'a.expert_role_id')
                ->join('roles as r', 'r.role_id', '=', 'er.role_id')
                ->where('a.team_id', $teamId)
                ->where('a.status', 'approved')
                ->where('e.approved_status', true)
                ->whereRaw('LOWER(r.role_name) IN (?, ?, ?)', ['adviser', 'consultant', 'grammarian'])
                ->select('e.expert_id', 'e.first_name', 'e.last_name', 'e.profile_picture', 'r.role_name')
                ->distinct()
                ->get();

            // Adviser, consultant, grammarian - the order the department
            // names them in, used for both the list and the label on a
            // person holding more than one.
            //
            // The list used to sort on "is this the adviser" alone, which
            // left consultant and grammarian tied and falling back to
            // expert_id; and the label joined the roles in whatever order the
            // query returned them, so the same person could read "Consultant,
            // Adviser" one load and the other way the next.
            $rank = ['adviser' => 0, 'consultant' => 1, 'grammarian' => 2];
            $rankOf = fn ($role) => $rank[strtolower((string) $role)] ?? 99;

            $experts = $rows
                ->groupBy('expert_id')
                ->map(function ($group) use ($rankOf) {
                    $first = $group->first();
                    $roles = $group->pluck('role_name')->unique()->sortBy($rankOf)->values();
                    // The model decides what counts as a usable photo - a
                    // URL, or a preset that exists on disk - so ask it.
                    $photo = new CapstoneExpert(['profile_picture' => $first->profile_picture]);

                    return (object) [
                        'expert_id' => $first->expert_id,
                        'first_name' => $first->first_name,
                        'last_name' => $first->last_name,
                        'avatar' => $photo->hasAvatarImage() ? $photo->avatarUrl() : null,
                        'role_name' => $roles->map('ucfirst')->implode(', '),
                        'is_adviser' => $roles->contains(fn ($r) => strtolower($r) === 'adviser'),
                        // Their most senior role, which is where they sit.
                        'role_rank' => $rankOf($roles->first()),
                    ];
                })
                ->sortBy([
                    fn ($a, $b) => $a->role_rank <=> $b->role_rank,
                    fn ($a, $b) => strcmp($a->last_name, $b->last_name),
                ])
                ->values();
        }

        $defaultExpertId = optional(
            $experts->first(fn ($e) => $e->is_adviser)
        )->expert_id;

        // Both available AND booked slots — the calendar JS needs booked ones
        // too (it checks each slot's own is_available) to show the
        // "Unavailable" state. Filtering them out here meant that state
        // could never render, and an expert whose slots were all currently
        // booked would show a completely empty calendar instead of "booked."
        $availableSlots = ConsultationSchedule::whereIn('expert_id', $experts->pluck('expert_id'))
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->get();

        // Team-wide, not just this student's own bookings - a consultation
        // is booked once per team (whoever happened to click "Book"), so
        // every teammate needs to see it in their own Upcoming/Recent list
        // too, not just the one member who made the booking.
        $teamStudentIds = $teamId
            ? StudentSchoolYear::where('team_id', $teamId)->pluck('student_id')
            : collect([$studentId]);

        $myBookings = ConsultationBooking::whereIn('student_id', $teamStudentIds)
            ->whereIn('booking_status', ['scheduled', 'done'])
            ->with(['schedule.expert', 'student.studentSchoolYear.team'])
            ->get();

        $today = now()->startOfDay();

        // Over once its end time has passed, not only from the next day. A
        // booking with no session_date (older rows) stays upcoming rather
        // than silently dropping from both lists.
        $ended = fn ($b) => $b->session_date
            && $b->session_date->copy()->setTimeFromTimeString($b->schedule?->end_time ?: '23:59:59')->isPast();

        $upcomingBookings = $myBookings
            ->reject($ended)
            ->sortBy(fn ($b) => $b->session_date ?? $today)
            ->values();

        $recentBookings = $myBookings
            ->filter($ended)
            ->sortByDesc(fn ($b) => $b->session_date)
            ->values();

        // Which experts this team already has a session waiting with. One
        // live booking per expert, not one per team: a team due to see their
        // adviser on Thursday should still be able to book their consultant
        // for Friday - different people, different things. This used to be a
        // single yes/no across every expert, which blocked all of it.
        $bookedExpertIds = $teamId
            ? ConsultationBooking::where('booking_status', 'scheduled')
                ->whereIn('student_id', StudentSchoolYear::where('team_id', $teamId)->pluck('student_id'))
                ->with('schedule')
                ->get()
                ->pluck('schedule.expert_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();

        // Each expert's scheduling window, so the calendar only offers times a
        // booking would be accepted for.
        $bookingWindows = ConsultationAvailability::whereIn('expert_id', $experts->pluck('expert_id'))
            ->get()
            ->mapWithKeys(fn ($a) => [$a->expert_id => $a->windowForClient()]);

        return compact('experts', 'defaultExpertId', 'availableSlots', 'upcomingBookings', 'recentBookings', 'teamId', 'bookedExpertIds', 'bookingWindows');
    }

    public function book(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer|exists:consultation_schedule,schedule_id',
            'session_date' => 'nullable|date',
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
            $teamStudentIds = StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id');

            $alreadyWithThisExpert = ConsultationBooking::where('booking_status', 'scheduled')
                ->whereIn('student_id', $teamStudentIds)
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
            'schedule_id' => $request->schedule_id,
            'session_date' => $sessionDate,
            'booking_status' => 'scheduled',
            'google_event_id' => $googleEventId,
        ]);

        // A "once" slot only ever has this single occurrence, so once it's
        // full it's safe to flip the shared row off. Recurring slots must
        // stay available — other future occurrences of the same weekday are
        // still bookable — which is why they're deliberately left alone here
        // and instead gated per-date via computeFullOccurrences().
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
            'fullOccurrences' => $this->computeFullOccurrences($availableSlots),
            'newBooking' => [
                'expert_name' => trim(($slot->expert?->first_name ?? 'Expert').' '.($slot->expert?->last_name ?? '')),
                'slot_type' => $slot->slot_type ?? 'consult',
                'session_date' => $sessionDate,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'team_name' => $team->team_name ?? 'N/A',
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
                .$booking->session_date->format('D, j M Y').'.',
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

    /**
     * Which (schedule_id, session_date) pairs already have as many scheduled
     * bookings as their consultation_limit allows. Used instead of the
     * schedule row's own is_available for recurring slots, since that column
     * is shared across every future occurrence of the same weekday.
     */
    private function computeFullOccurrences($slots): array
    {
        $scheduleIds = $slots->pluck('schedule_id');
        if ($scheduleIds->isEmpty()) {
            return [];
        }

        $limitsBySchedule = $slots->pluck('consultation_limit', 'schedule_id');

        return ConsultationBooking::whereIn('schedule_id', $scheduleIds)
            ->where('booking_status', 'scheduled')
            ->get()
            ->groupBy(fn ($b) => $b->schedule_id.'|'.optional($b->session_date)->format('Y-m-d'))
            ->filter(function ($bookings, $key) use ($limitsBySchedule) {
                [$scheduleId] = explode('|', $key);
                $limit = max(1, (int) ($limitsBySchedule[$scheduleId] ?? 1));

                return $bookings->count() >= $limit;
            })
            ->keys()
            ->values()
            ->all();
    }

    public function cancel(Request $request, $bookingId)
    {
        // A plain $request->validate() would redirect-back on failure
        // instead of returning JSON, since this app's exception handler
        // only forces JSON rendering for /api/* routes - this endpoint is
        // called via fetch() and can't follow a redirect, so the error is
        // built manually here to match every other JSON response below.
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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
        $teamStudentIds = $studentYear?->team_id
            ? StudentSchoolYear::where('team_id', $studentYear->team_id)->pluck('student_id')
            : collect([$student->student_id]);

        $booking = ConsultationBooking::where('booking_id', $bookingId)
            ->whereIn('student_id', $teamStudentIds)
            ->where('booking_status', 'scheduled')
            ->firstOrFail();

        // Too close to call off. An expert who has kept the time free
        // deserves more notice than a day, and a team pulling out the night
        // before leaves a slot nobody else can take.
        $daysAway = now()->startOfDay()->diffInDays(
            \Illuminate\Support\Carbon::parse($booking->session_date)->startOfDay(),
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
