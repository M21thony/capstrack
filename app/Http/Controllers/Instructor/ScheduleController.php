<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Jobs\SyncConsultationCancellation;
use App\Mail\NewConsultationSlotMail;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSchedule;
use App\Models\TeamExpertAssignment;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    public function __construct(private GoogleCalendarService $calendar) {}

    // GET /instructor/schedule/slots
    public function getSlots()
    {
        ConsultationBooking::markPastAsDone();

        // Debug logging used to sit here dumping the whole expert model,
        // which writes their Google access and refresh tokens into
        // laravel.log in plain text on every page load of the schedule.
        $expert = Auth::guard('expert')->user();

        // Scoped to booking_status in ('scheduled', 'done') in the join's ON
        // clause (not a WHERE) so schedule rows with no booking, or only a
        // cancelled one, still come through as a left join instead of being
        // dropped. A recurring slot can have several distinct bookings — one
        // per weekday occurrence it was booked on — so this intentionally
        // returns one row per (schedule, booking) pair rather than one row
        // per schedule; the frontend groups them back by schedule_id and
        // keeps each booking's own session_date to know which specific
        // calendar occurrence it belongs to.
        $slots = DB::table('consultation_schedule')
            ->where('expert_id', Auth::guard('expert')->id())
            ->leftJoin('consultation_booking', function ($join) {
                $join->on('consultation_booking.schedule_id', '=', 'consultation_schedule.schedule_id')
                    ->whereIn('consultation_booking.booking_status', ['scheduled', 'done']);
            })
            ->leftJoin('students', 'students.student_id', '=', 'consultation_booking.student_id')
            ->leftJoin('student_s_y', 'student_s_y.student_id', '=', 'students.student_id')
            ->leftJoin('team', 'team.team_id', '=', 'student_s_y.team_id')
            ->select(
                'consultation_schedule.*',
                'consultation_booking.booking_id',
                'consultation_booking.session_date',
                'consultation_booking.booking_status',
                'consultation_booking.cancellation_requested_at',
                'consultation_booking.cancellation_reason',
                'consultation_booking.created_at as booked_at',
                'students.f_name as student_fname',
                'students.l_name as student_lname',
                'team.team_name'
            )
            ->get();

        // The dates a weekly slot does NOT happen on. Without these the page
        // would draw an occurrence the adviser has already moved or skipped,
        // and it would sit there beside the one-off that replaced it.
        $exceptions = DB::table('consultation_schedule_exception')
            ->whereIn('schedule_id', $slots->pluck('schedule_id')->unique()->all())
            ->get(['schedule_id', 'exception_date']);

        return response()->json(['slots' => $slots, 'exceptions' => $exceptions]);
    }

    // PATCH /instructor/schedule/bookings/{bookingId}/cancellation
    // A team asked to call a consultation off; the expert approves or declines.
    public function updateCancellation(Request $request, int $bookingId)
    {
        $validator = Validator::make($request->all(), ['decision' => 'required|in:approve,decline']);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $booking = ConsultationBooking::where('booking_id', $bookingId)
            ->where('booking_status', 'scheduled')
            ->whereNotNull('cancellation_requested_at')
            ->whereHas('schedule', fn ($q) => $q->where('expert_id', Auth::guard('expert')->id()))
            ->firstOrFail();

        $approve = $request->input('decision') === 'approve';

        if ($approve) {
            $booking->update(['booking_status' => 'cancelled', 'cancellation_requested_at' => null]);
            ConsultationSchedule::where('schedule_id', $booking->schedule_id)->update(['is_available' => true]);

            // Mail and Google Calendar wait on other servers, so they run after the answer.
            SyncConsultationCancellation::dispatch(
                $booking->booking_id,
                (string) $booking->cancellation_reason,
                (string) $booking->cancellation_requested_by,
            );
        } else {
            $booking->update(['cancellation_requested_at' => null, 'cancellation_requested_by' => null, 'cancellation_reason' => null]);
        }

        $when = $booking->session_date ? Carbon::parse($booking->session_date)->format('M j') : 'the booked date';
        $studentIds = $booking->team
            ? StudentSchoolYear::where('team_id', $booking->team->team_id)->pluck('student_id')
            : collect([$booking->student_id]);

        foreach ($studentIds as $studentId) {
            Notification::create([
                'student_id' => $studentId,
                'type' => 'consultation_cancellation',
                'title' => $approve ? 'Cancellation approved' : 'Cancellation declined',
                'message' => $approve
                    ? "Your consultation on {$when} is cancelled."
                    : "Your expert kept your consultation on {$when}. It is still on.",
                'link' => route('student.consultation'),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * The dates this weekly slot has been taken out of.
     *
     * @return array<int, string>
     */
    private function exceptionDates(int $scheduleId): array
    {
        return DB::table('consultation_schedule_exception')
            ->where('schedule_id', $scheduleId)
            ->pluck('exception_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();
    }

    // POST /instructor/schedule/slots
    public function addSlot(Request $request)
    {
        // Request::validate() would redirect on failure here — this app
        // only auto-renders JSON exceptions for api/* routes
        // (bootstrap/app.php's shouldRenderJsonWhen), and this endpoint is
        // hit via fetch from an instructor/ page. Validate manually so a
        // failure returns JSON instead of a redirect the JS can't handle.
        $validator = Validator::make($request->all(), [
            'available_date' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'consultation_limit' => 'required|integer|min:1|max:10',
            'slot_type' => 'nullable|string',
            'recur' => 'nullable|in:once,weekly,biweekly',
            'specific_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $expert = Auth::guard('expert')->user();

        $nextDate = $this->getNextDateForDay($request->available_date);

        $newRecur = $request->recur ?? 'weekly';
        $newDate  = $newRecur === 'once' ? $request->specific_date : null;

        // A slot identical to one that already exists is nobody's intention.
        // It used to be inserted anyway, then flagged unavailable by the
        // overlap check below - and an unavailable slot reads as a booked
        // one, so a repeated save left phantom appointments behind. Hand
        // back the slot that is already there instead.
        // Times are compared trimmed to H:i on both sides - the column holds
        // '09:00:00' and the request sends '09:00', and only MySQL treats
        // those as equal.
        $hm = fn ($t) => substr((string) $t, 0, 5);

        $duplicate = DB::table('consultation_schedule')
            ->where('expert_id', $expert->expert_id)
            ->where('available_date', $request->available_date)
            ->get()
            ->first(fn ($row) => $hm($row->start_time) === $hm($request->start_time)
                && $hm($row->end_time) === $hm($request->end_time)
                && ($row->recur ?? 'weekly') === $newRecur
                && ($row->specific_date ?? null) === $newDate);

        if ($duplicate) {
            return response()->json([
                'schedule_id' => $duplicate->schedule_id,
                'google_synced' => $duplicate->google_event_id !== null,
                'is_available' => (bool) $duplicate->is_available,
                'already_existed' => true,
            ]);
        }

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
                    recurrence: GoogleCalendarService::recurrenceRuleFor($request->recur ?? 'weekly'),
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

        $this->notifyTeamsOfNewSlot(ConsultationSchedule::find($id), $expert);

        return response()->json([
            'schedule_id' => $id,
            'google_synced' => $googleEventId !== null,
            'is_available' => ! $hasOverlap,
        ]);
    }

    // Emails every student on every team this expert handles that a new
    // consultation slot just opened up, showing how often it recurs (e.g.
    // "Every Monday") rather than just its next date.
    private function notifyTeamsOfNewSlot(ConsultationSchedule $slot, CapstoneExpert $expert): void
    {
        $teams = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with('team.members')
            ->get()
            ->pluck('team')
            ->filter();

        foreach ($teams as $team) {
            foreach ($team->members as $student) {
                if (! $student->email) {
                    continue;
                }

                try {
                    Mail::to($student->email)->queue(new NewConsultationSlotMail($slot, $expert, $team));
                } catch (\Exception $e) {
                    Log::warning('New consultation slot email failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    // PATCH /instructor/schedule/slots/{id}
    public function updateSlot(Request $request, int $id)
    {
        // See addSlot() — validate() here would try to redirect on
        // failure instead of returning JSON, since this route isn't
        // under api/*.
        $validator = Validator::make($request->all(), [
            'available_date' => 'sometimes|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'consultation_limit' => 'sometimes|integer|min:1|max:10',
            'slot_type' => 'sometimes|nullable|string',
            'is_available' => 'sometimes|boolean',
            // addSlot has always taken these two; this did not, so a slot's
            // recurrence and its one-off date were dropped on the way in and
            // editing either of them appeared to do nothing at all.
            'recur' => 'sometimes|in:once,weekly,biweekly',
            'specific_date' => 'sometimes|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $expert = Auth::guard('expert')->user();

        $slot = DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $slot) {
            return response()->json(['error' => 'Slot not found.'], 404);
        }


        // What the slot will look like once this edit lands - the new value
        // where one was sent, the current one where it was not.
        $newRecur = $request->has('recur') ? $request->recur : ($slot->recur ?? 'weekly');
        $newDay = $request->has('available_date') ? $request->available_date : $slot->available_date;
        $newStart = $request->has('start_time') ? $request->start_time : substr((string) $slot->start_time, 0, 5);
        $newEnd = $request->has('end_time') ? $request->end_time : substr((string) $slot->end_time, 0, 5);

        // A weekly slot has no date of its own, so switching away from 'once'
        // has to clear it. array_filter dropped nulls, which is why it could
        // not have been done through that list.
        $newDate = $newRecur === 'once'
            ? ($request->has('specific_date') ? $request->specific_date : $slot->specific_date)
            : null;

        if ($newRecur === 'once' && ! $newDate) {
            return response()->json(['error' => 'A one-time slot needs a date.'], 422);
        }

        // The same collision check addSlot makes. Without it an edit could
        // walk a slot straight into an overlap that adding it would have
        // refused - itself excluded, since a slot cannot clash with itself.
        $clashes = DB::table('consultation_schedule')
            ->where('expert_id', $expert->expert_id)
            ->where('schedule_id', '!=', $id)
            ->where('available_date', $newDay)
            ->where('start_time', '<', $newEnd)
            ->where('end_time', '>', $newStart)
            ->get(['recur', 'specific_date'])
            ->contains(fn ($existing) => $this->occurrencesCanCollide(
                $newRecur,
                $newDate,
                $existing->recur ?? 'weekly',
                $existing->specific_date
            ));

        if ($clashes) {
            return response()->json(['error' => 'That overlaps another slot you already have.'], 422);
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

        // Set outside the filter above, because null is a real value for
        // specific_date - it is what a slot that is no longer one-time has.
        $updates['recur'] = $newRecur;
        $updates['specific_date'] = $newDate;

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
                    recurrence: GoogleCalendarService::recurrenceRuleFor($slot->recur ?? 'weekly'),
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

    /**
     * Change one week of a repeating slot, and only that week.
     *
     * The slot itself is never touched. The date is taken out of it, and a
     * one-off slot carrying the new time takes its place - so next Monday and
     * every Monday after it stay exactly as they were, which is what made
     * editing a single week impossible before.
     */
    // PUT /instructor/schedule/slots/{id}/occurrences/{date}
    public function replaceOccurrence(Request $request, int $id, string $date)
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'slot_type' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $expert = Auth::guard('expert')->user();

        $slot = DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $slot) {
            return response()->json(['error' => 'Slot not found.'], 404);
        }

        if (($slot->recur ?? 'weekly') === 'once') {
            return response()->json([
                'error' => 'This slot already happens on one date only - edit it directly.',
            ], 422);
        }

        if (Carbon::parse($date)->format('l') !== $slot->available_date) {
            return response()->json([
                'error' => "That date is not a {$slot->available_date}.",
            ], 422);
        }

        // A week somebody has booked cannot be moved out from under them.
        $booked = DB::table('consultation_booking')
            ->where('schedule_id', $slot->schedule_id)
            ->whereDate('session_date', $date)
            ->where('booking_status', 'scheduled')
            ->exists();

        if ($booked) {
            return response()->json([
                'error' => 'A team has booked this date. Cancel the booking first.',
            ], 422);
        }

        $start = $request->start_time ?? substr((string) $slot->start_time, 0, 5);
        $end = $request->end_time ?? substr((string) $slot->end_time, 0, 5);
        $type = $request->slot_type ?? $slot->slot_type;

        // The rule being replaced is excluded from this date, so it must not
        // count as a collision here - nor must any other rule already excluded
        // from it. Every remaining slot on that weekday still does.
        $excluded = DB::table('consultation_schedule_exception')
            ->whereDate('exception_date', $date)
            ->pluck('schedule_id')
            ->push($slot->schedule_id)
            ->all();

        $clashes = DB::table('consultation_schedule')
            ->where('expert_id', $expert->expert_id)
            ->whereNotIn('schedule_id', $excluded)
            ->where('available_date', $slot->available_date)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->get(['recur', 'specific_date'])
            ->contains(fn ($existing) => $this->occurrencesCanCollide(
                'once', $date, $existing->recur ?? 'weekly', $existing->specific_date
            ));

        if ($clashes) {
            return response()->json(['error' => 'That overlaps another slot on this date.'], 422);
        }

        // updateOrInsert, not insert: pressing this twice on the same date is
        // an ordinary thing to do and must not fail on the unique key.
        DB::table('consultation_schedule_exception')->updateOrInsert(
            ['schedule_id' => $slot->schedule_id, 'exception_date' => $date],
            ['updated_at' => now(), 'created_at' => now()]
        );

        $newId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expert->expert_id,
            'available_date' => $slot->available_date,
            'specific_date' => $date,
            'recur' => 'once',
            'start_time' => $start,
            'end_time' => $end,
            'consultation_limit' => $slot->consultation_limit,
            'slot_type' => $type,
            'is_available' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($slot->google_event_id && $expert->google_token) {
            try {
                $this->calendar->excludeDateFromSeries($expert->expert_id, $slot->google_event_id, $date);

                $eventId = $this->calendar->createBookingEvent(
                    expertId: $expert->expert_id,
                    date: $date,
                    startTime: $start,
                    endTime: $end,
                    slotType: ucfirst($type ?? 'Consultation'),
                    teamName: 'Available Slot',
                    studentEmail: $expert->email,
                    expertEmail: $expert->email,
                    topic: 'Moved '.($type ?? 'consultation')." slot — {$date}",
                );

                DB::table('consultation_schedule')
                    ->where('schedule_id', $newId)
                    ->update(['google_event_id' => $eventId]);
            } catch (\Exception $e) {
                // The slot has already moved in CapsTrack. A calendar that
                // failed to follow is worth a line in the log, not a 500 that
                // makes the adviser think the move did not happen.
                Log::warning('Calendar did not follow a moved occurrence', [
                    'schedule_id' => $slot->schedule_id,
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'updated' => true,
            'scope' => 'occurrence',
            'schedule_id' => $newId,
            'moved_from' => $slot->schedule_id,
            'date' => $date,
        ]);
    }

    // DELETE /instructor/schedule/slots/{id}/occurrences/{date}
    //
    // Removing one week is not removing the slot. Before this, the only way
    // to clear a single date was to delete the rule, which took every other
    // week with it.
    public function removeOccurrence(int $id, string $date)
    {
        $expert = Auth::guard('expert')->user();

        $slot = DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $slot) {
            return response()->json(['error' => 'Slot not found.'], 404);
        }

        if (($slot->recur ?? 'weekly') === 'once') {
            return response()->json(['error' => 'This slot is a single date already.'], 422);
        }

        if (Carbon::parse($date)->format('l') !== $slot->available_date) {
            return response()->json(['error' => "That date is not a {$slot->available_date}."], 422);
        }

        $booked = DB::table('consultation_booking')
            ->where('schedule_id', $id)
            ->whereDate('session_date', $date)
            ->where('booking_status', 'scheduled')
            ->exists();

        if ($booked) {
            return response()->json([
                'error' => 'A team has booked this date. Cancel the booking first.',
            ], 422);
        }

        // updateOrInsert, not insert: removing the same week twice is an
        // ordinary thing to do and must not fail on the unique key.
        DB::table('consultation_schedule_exception')->updateOrInsert(
            ['schedule_id' => $id, 'exception_date' => $date],
            ['updated_at' => now(), 'created_at' => now()]
        );

        if ($slot->google_event_id && $expert->google_token) {
            try {
                $this->calendar->excludeDateFromSeries($expert->expert_id, $slot->google_event_id, $date);
            } catch (\Exception $e) {
                Log::warning('Calendar did not follow a skipped week', [
                    'schedule_id' => $id, 'date' => $date, 'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['removed' => true, 'date' => $date]);
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

        // Deleting the rule deletes every week of it, so a single booking
        // anywhere ahead is enough to stop it. The team is expecting that
        // session; removing the slot would take it away without telling them,
        // where cancelling the booking emails them.
        $booked = DB::table('consultation_booking')
            ->where('schedule_id', $id)
            ->where('booking_status', 'scheduled')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->exists();

        if ($booked) {
            return response()->json([
                'error' => 'A team has booked this slot. Cancel the booking first.',
            ], 422);
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

        // No foreign keys on MyISAM, so nothing cascades. Left behind, these
        // rows would attach themselves to whichever slot inherits this id -
        // TRUNCATE resets the counter, so that is a real possibility.
        DB::table('consultation_schedule_exception')->where('schedule_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    // Toggle availability without deleting
    // PUT /instructor/schedule/slots/{id}/availability
    public function setAvailability(Request $request, int $id)
    {
        $expert = Auth::guard('expert')->user();

        $slot = DB::table('consultation_schedule')
            ->where('schedule_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $slot) {
            return response()->json(['error' => 'Slot not found.'], 404);
        }

        // The caller says what it wants; it already knows what it is
        // showing. Falling back to a flip keeps any caller that does not.
        $newStatus = $request->has('is_available')
            ? $request->boolean('is_available')
            : ! $slot->is_available;

        // is_available belongs to the rule, not to a date, so switching it off
        // hides every occurrence - including one a team is expecting to turn up
        // to. A slot somebody has booked cannot be withdrawn from underneath
        // them; the booking is cancelled first, which is a conversation rather
        // than a toggle. Past bookings do not count: those already happened.
        if (! $newStatus) {
            $booked = DB::table('consultation_booking')
                ->where('schedule_id', $id)
                ->where('booking_status', 'scheduled')
                ->whereDate('session_date', '>=', now()->toDateString())
                ->exists();

            if ($booked) {
                return response()->json([
                    'error' => 'A team has booked this slot. Cancel the booking first.',
                ], 422);
            }
        }

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
