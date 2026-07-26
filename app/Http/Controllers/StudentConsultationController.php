<?php

namespace App\Http\Controllers;

use App\Models\ConsultationSchedule;
use App\Models\ConsultationBooking;
use App\Models\CapstoneExpert;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $experts = CapstoneExpert::where('approved_status', true)
            ->orderBy('first_name')
            ->get();

        $availableSlots = ConsultationSchedule::where('is_available', true)
            ->whereHas('expert', function ($q) {
                $q->where('approved_status', true);
            })
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->get();

        $myBookings = ConsultationBooking::where('student_id', $studentId)
            ->where('booking_status', 'scheduled')
            ->with(['schedule', 'student.studentSchoolYear.team'])
            ->get();

        return view('student.consultation', [
            'experts' => $experts,
            'availableSlots' => $availableSlots,
            'myBookings' => $myBookings,
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
