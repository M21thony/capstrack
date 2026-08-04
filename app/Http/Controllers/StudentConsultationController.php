<?php

namespace App\Http\Controllers;

use App\Models\ConsultationSchedule;
use App\Models\ConsultationBooking;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentConsultationController extends Controller
{
    public function __construct(private GoogleCalendarService $calendar) {}

    public function index()
    {
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
            ->where('booking_status', 'scheduled')
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

        $booking = ConsultationBooking::create([
            'student_id' => $student->student_id,
            'schedule_id' => $request->schedule_id,
            // Captured once, at booking time — the slot's own available_date
            // is just a weekday name for recurring slots, so re-deriving a
            // date from it later (e.g. on every page load) drifts forward
            // every week instead of staying fixed to the session actually
            // booked.
            'session_date' => $slot->nextOccurrenceOnOrAfter(),
            'booking_status' => 'scheduled',
        ]);

        ConsultationSchedule::where('schedule_id', $request->schedule_id)
            ->update(['is_available' => false]);

        $team = Team::where('team_id', $studentYear->team_id)->first();

        if ($slot->google_event_id && $team) {
            try {
                $this->calendar->updateBookingEvent(
                    expertId: $slot->expert_id,
                    googleEventId: $slot->google_event_id,
                    teamName: $team->team_name
                );
            } catch (\Exception $e) {
                \Log::warning('Google Calendar update failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['success' => true, 'booking_id' => $booking->booking_id]);
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

            if ($slot->google_event_id && $slot->expert && $slot->expert->google_token) {
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
        }

        return response()->json(['success' => true]);
    }
}
