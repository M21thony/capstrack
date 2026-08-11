<?php

namespace App\Http\Controllers;

use App\Mail\ConsultationBookedMail;
use App\Models\ConsultationSchedule;
use App\Models\ConsultationBooking;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class StudentConsultationController extends Controller
{
    public function __construct(private GoogleCalendarService $calendar) {}

    public function index()
    {
        ConsultationBooking::markPastAsDone();

        $student = Auth::user();
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
            $experts = DB::table('capstone_expert_application as a')
                ->join('capstone_experts as e', 'e.expert_id', '=', 'a.expert_id')
                ->join('expert_roles as er', 'er.expert_id', '=', 'e.expert_id')
                ->join('roles as r', 'r.role_id', '=', 'er.role_id')
                ->where('a.team_id', $teamId)
                ->where('a.status', 'accepted')
                ->where('e.approved_status', true)
                ->whereRaw('LOWER(r.role_name) IN (?, ?, ?)', ['adviser', 'consultant', 'grammarian'])
                ->select('e.expert_id', 'e.first_name', 'e.last_name', 'r.role_name')
                ->distinct()
                ->get()
                ->sortBy(fn ($e) => strtolower($e->role_name) === 'adviser' ? 0 : 1)
                ->values();
        }

        $defaultExpertId = optional(
            $experts->first(fn ($e) => strtolower($e->role_name) === 'adviser')
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

        $myBookings = ConsultationBooking::where('student_id', $studentId)
            ->whereIn('booking_status', ['scheduled', 'done'])
            ->with(['schedule', 'student.studentSchoolYear.team'])
            ->get();

        $today = now()->startOfDay();

        // A booking with no session_date yet (shouldn't happen for anything
        // booked after the session_date migration, but guards older rows
        // that predate it) is treated as upcoming rather than silently
        // dropped from both lists.
        $upcomingBookings = $myBookings
            ->filter(fn ($b) => ! $b->session_date || $b->session_date->greaterThanOrEqualTo($today))
            ->sortBy(fn ($b) => $b->session_date ?? $today)
            ->values();

        $recentBookings = $myBookings
            ->filter(fn ($b) => $b->session_date && $b->session_date->lt($today))
            ->sortByDesc(fn ($b) => $b->session_date)
            ->values();

        return view('student.consultation', [
            'experts' => $experts,
            'defaultExpertId' => $defaultExpertId,
            'availableSlots' => $availableSlots,
            'fullOccurrences' => $this->computeFullOccurrences($availableSlots),
            'upcomingBookings' => $upcomingBookings,
            'recentBookings' => $recentBookings,
            'teamId' => $teamId,
            'student' => $student,
        ]);
    }

    public function book(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer|exists:consultation_schedule,schedule_id',
        ]);

        $student = Auth::user();

        $studentYear = StudentSchoolYear::where('student_id', $student->student_id)
            ->orderByDesc('school_year')
            ->first();

        $existing = ConsultationBooking::where('student_id', $student->student_id)
            ->where('schedule_id', $request->schedule_id)
            ->where('booking_status', 'scheduled')
            ->exists();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'You already booked this slot.'], 422);
        }

        $slot = ConsultationSchedule::find($request->schedule_id);
        if (! $slot || ! $slot->is_available) {
            return response()->json(['success' => false, 'message' => 'This slot is no longer available.'], 422);
        }

        // Captured once, at booking time — the slot's own available_date is
        // just a weekday name for recurring slots, so re-deriving a date
        // from it later (e.g. on every page load) drifts forward every week
        // instead of staying fixed to the session actually booked.
        $sessionDate = $slot->nextOccurrenceOnOrAfter();

        // Recurring (weekly/biweekly) slots share a single schedule row
        // across every future occurrence of that weekday, so capacity has
        // to be checked per calendar date rather than per row — otherwise
        // one booking would exhaust every future occurrence at once.
        $limit = max(1, (int) ($slot->consultation_limit ?? 1));
        $bookedCount = ConsultationBooking::where('schedule_id', $slot->schedule_id)
            ->where('session_date', $sessionDate)
            ->where('booking_status', 'scheduled')
            ->count();

        if ($bookedCount >= $limit) {
            return response()->json(['success' => false, 'message' => 'This slot is fully booked for that date.'], 422);
        }

        $team = Team::where('team_id', $studentYear->team_id)->first();

        // Recurring slots share a single schedule row (and a single
        // placeholder Google event, pinned to whatever date it happened to
        // be created on) across every future weekday occurrence. Relabeling
        // that shared event on every booking would mis-date every occurrence
        // after the first onto the placeholder's original date, so a
        // recurring booking instead gets its own event on the actual date
        // booked; a "once" slot only ever has this one occurrence, so its
        // existing placeholder IS this session and just gets relabeled.
        $googleEventId = null;

        if ($slot->recur === 'once') {
            if ($slot->google_event_id && $team) {
                try {
                    $this->calendar->updateBookingEvent(
                        expertId: $slot->expert_id,
                        googleEventId: $slot->google_event_id,
                        teamName: $team->team_name
                    );
                    $googleEventId = $slot->google_event_id;
                } catch (\Exception $e) {
                    \Log::warning('Google Calendar update failed', ['error' => $e->getMessage()]);
                }
            }
        } elseif ($team && $slot->expert && $slot->expert->google_token) {
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

    // Emails every student on the booking team plus the assigned expert that
    // a consultation was booked, not just the one student who clicked book.
    private function notifyTeamOfBooking(ConsultationBooking $booking, $expert, Team $team): void
    {
        $recipients = $team->members->pluck('email')->filter()->push($expert->email)->unique();

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->queue(new ConsultationBookedMail($booking, $expert, $team));
            } catch (\Exception $e) {
                \Log::warning('Consultation booked email failed', ['error' => $e->getMessage()]);
            }
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
        $student = Auth::user();

        $booking = ConsultationBooking::where('booking_id', $bookingId)
            ->where('student_id', $student->student_id)
            ->where('booking_status', 'scheduled')
            ->firstOrFail();

        $slot = ConsultationSchedule::find($booking->schedule_id);

        $booking->update(['booking_status' => 'cancelled']);

        if ($slot) {
            ConsultationSchedule::where('schedule_id', $booking->schedule_id)
                ->update(['is_available' => true]);

            if (! $slot->expert || ! $slot->expert->google_token) {
                return response()->json(['success' => true]);
            }

            if ($slot->recur === 'once') {
                if ($slot->google_event_id) {
                    try {
                        $this->calendar->updateBookingEvent(
                            expertId: $slot->expert_id,
                            googleEventId: $slot->google_event_id,
                            teamName: 'Available Slot'
                        );
                    } catch (\Exception $e) {
                        \Log::warning('Google Calendar update failed on cancel', ['error' => $e->getMessage()]);
                    }
                }
            } elseif ($booking->google_event_id) {
                // Recurring occurrence — this booking had its own event
                // (there's no shared placeholder for this specific date to
                // fall back to), so remove it entirely rather than relabeling.
                try {
                    $this->calendar->cancelBookingEvent($slot->expert_id, $booking->google_event_id);
                } catch (\Exception $e) {
                    \Log::warning('Google Calendar event deletion failed on cancel', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['success' => true]);
    }
}
