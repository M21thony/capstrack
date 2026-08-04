<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    public function __construct(private GoogleCalendarService $calendar) {}

    // GET /instructor/schedule/slots
    public function getSlots()
    {
        Log::info('getSlots reached');

        $expert = Auth::guard('expert')->user();

        Log::info([
            'auth_id' => Auth::guard('expert')->id(),
            'expert' => $expert,
        ]);

        $slots = DB::table('consultation_schedule')
            ->where('expert_id', Auth::guard('expert')->id())
            ->leftJoin('consultation_booking', 'consultation_booking.schedule_id', '=', 'consultation_schedule.schedule_id')
            ->leftJoin('students', 'students.student_id', '=', 'consultation_booking.student_id')
            ->leftJoin('student_s_y', 'student_s_y.student_id', '=', 'students.student_id')
            ->leftJoin('team', 'team.team_id', '=', 'student_s_y.team_id')
            ->select(
                'consultation_schedule.*',
                'consultation_booking.booking_id',
                'consultation_booking.booking_status',
                'consultation_booking.created_at as booked_at',
                'students.f_name as student_fname',
                'students.l_name as student_lname',
                'team.team_name'
            )
            ->get();

        return response()->json($slots);
    }

    // POST /instructor/schedule/slots
    public function addSlot(Request $request)
    {
        $request->validate([
            'available_date' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'consultation_limit' => 'required|integer|min:1|max:10',
            'slot_type' => 'nullable|string',
            'recur' => 'nullable|in:once,weekly,biweekly',
            'specific_date' => 'nullable|date',
        ]);

        $expert = Auth::guard('expert')->user();

        $nextDate = $this->getNextDateForDay($request->available_date);

        $newRecur = $request->recur ?? 'weekly';
        $newDate  = $newRecur === 'once' ? $request->specific_date : null;

        // available_date is only a weekday name, not a real date, so a plain
        // weekday+time match here would flag a new one-time slot as
        // "overlapping" against any unrelated once/biweekly slot that merely
        // shares that weekday — even one whose actual occurrence never
        // coincides with the date being added. Narrow it down to rows whose
        // real occurrences can actually collide.
        $candidates = DB::table('consultation_schedule')
            ->where('expert_id', $expert->expert_id)
            ->where('available_date', $request->available_date)
            ->where('start_time', '<', $request->end_time)
            ->where('end_time', '>', $request->start_time)
            ->get(['recur', 'specific_date']);

        $hasOverlap = $candidates->contains(
            fn ($existing) => $this->occurrencesCanCollide(
                $newRecur,
                $newDate,
                $existing->recur ?? 'weekly',
                $existing->specific_date
            )
        );

        $googleEventId = null;

        if ($expert->google_token && ! $hasOverlap) {
            try {
                $googleEventId = $this->calendar->createBookingEvent(
                    expertId: $expert->expert_id,
                    date: $nextDate,
                    startTime: $request->start_time,
                    endTime: $request->end_time,
                    slotType: $request->slot_type ? ucfirst($request->slot_type) : 'Consultation',
                    teamName: 'Available Slot',
                    studentEmail: $expert->email,
                    expertEmail: $expert->email,
                    topic: "Weekly {$request->slot_type} slot — {$request->available_date}s",
                );
            } catch (\Exception $e) {
                \Log::warning('Google Calendar sync failed', ['error' => $e->getMessage()]);
            }
        }

        $id = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expert->expert_id,
            'available_date' => $request->available_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'consultation_limit' => $request->consultation_limit,
            'slot_type' => $request->slot_type,
            'is_available' => ! $hasOverlap,
            'google_event_id' => $googleEventId,
            'recur' => $request->recur ?? 'weekly',
            'specific_date' => $request->recur === 'once' ? $request->specific_date : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'schedule_id' => $id,
            'google_synced' => $googleEventId !== null,
            'is_available' => ! $hasOverlap,
        ]);
    }

    // PATCH /instructor/schedule/slots/{id}
    public function updateSlot(Request $request, int $id)
    {
        $request->validate([
            'available_date' => 'sometimes|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'consultation_limit' => 'sometimes|integer|min:1|max:10',
            'slot_type' => 'sometimes|nullable|string',
            'is_available' => 'sometimes|boolean',
        ]);

        $expert = Auth::guard('expert')->user();

        $slot = DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $slot) {
            return response()->json(['error' => 'Slot not found.'], 404);
        }

        $updates = array_filter([
            'available_date' => $request->available_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'consultation_limit' => $request->consultation_limit,
            'slot_type' => $request->slot_type,
            'is_available' => $request->has('is_available') ? $request->boolean('is_available') : null,
            'updated_at' => now(),
        ], fn ($v) => $v !== null);

        DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->update($updates);

        // Update Google Calendar event if exists
        if ($slot->google_event_id && $expert->google_token) {
            try {
                // Delete old event and create new one with updated details
                $this->calendar->cancelBookingEvent($expert->expert_id, $slot->google_event_id);

                $day = $request->available_date ?? $slot->available_date;
                $start = $request->start_time ?? $slot->start_time;
                $end = $request->end_time ?? $slot->end_time;
                $nextDate = $this->getNextDateForDay($day);

                $newEventId = $this->calendar->createBookingEvent(
                    expertId: $expert->expert_id,
                    date: $nextDate,
                    startTime: $start,
                    endTime: $end,
                    slotType: ucfirst($request->slot_type ?? $slot->slot_type ?? 'Consultation'),
                    teamName: 'Available Slot',
                    studentEmail: $expert->email,
                    expertEmail: $expert->email,
                    topic: "Weekly " . ($request->slot_type ?? $slot->slot_type ?? 'consultation') . " slot — {$day}s",
                );

                DB::table('consultation_schedule')
                    ->where('schedule_id', $id)
                    ->update(['google_event_id' => $newEventId]);

            } catch (\Exception $e) {
                \Log::warning('Google Calendar update failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['updated' => true]);
    }

    // DELETE /instructor/schedule/slots/{id}
    public function deleteSlot(int $id)
    {
        $expert = Auth::guard('expert')->user();

        $slot = DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $slot) {
            return response()->json(['error' => 'Slot not found.'], 404);
        }

        // Delete from Google Calendar
        if ($slot->google_event_id && $expert->google_token) {
            try {
                $this->calendar->cancelBookingEvent($expert->expert_id, $slot->google_event_id);
            } catch (\Exception $e) {
                \Log::warning('Google Calendar delete failed', ['error' => $e->getMessage()]);
            }
        }

        DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->delete();

        return response()->json(['deleted' => true]);
    }

    // Toggle availability without deleting
    // PATCH /instructor/schedule/slots/{id}/toggle
    public function toggleAvailability(int $id)
    {
        $expert = Auth::guard('expert')->user();

        $slot = DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $slot) {
            return response()->json(['error' => 'Slot not found.'], 404);
        }

        $newStatus = ! $slot->is_available;

        DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->update([
                'is_available' => $newStatus,
                'updated_at' => now(),
            ]);

        return response()->json(['is_available' => $newStatus]);
    }

    // Helper — get next occurrence of a weekday from today
    private function getNextDateForDay(string $dayName): string
    {
        $days = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
        $target = $days[$dayName];
        $today = now()->dayOfWeek;
        $diff = ($target - $today + 7) % 7;
        $diff = $diff === 0 ? 7 : $diff; // if today, use next week

        return now()->addDays($diff)->format('Y-m-d');
    }

    // Two slots on the same weekday+time only truly conflict if some real
    // calendar date exists where both are actually active. Caller has
    // already confirmed both share a weekday and overlapping time — this
    // resolves whether their recur/specific_date combination can ever
    // land on the same day.
    private function occurrencesCanCollide(string $recurA, ?string $dateA, string $recurB, ?string $dateB): bool
    {
        if ($recurA === 'once' && $recurB === 'once') {
            return $dateA !== null && $dateB !== null && $dateA === $dateB;
        }

        // A weekly slot recurs on that weekday indefinitely, so it will
        // eventually coincide with any once/biweekly slot on the same day.
        if ($recurA === 'weekly' || $recurB === 'weekly') {
            return true;
        }

        // Remaining combinations involve at least one biweekly side —
        // check whether the dates align on the same 14-day cadence.
        $biweeklyDate = $recurA === 'biweekly' ? $dateA : $dateB;
        $otherDate    = $recurA === 'biweekly' ? $dateB : $dateA;

        if (! $biweeklyDate || ! $otherDate) {
            return true; // can't verify alignment — fail safe
        }

        return Carbon::parse($otherDate)->diffInDays(Carbon::parse($biweeklyDate)) % 14 === 0;
    }
}
