<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsultationBookingDateTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(): int
    {
        return DB::table('capstone_experts')->insertGetId([
            'email' => 'adviser@gmail.com',
            'first_name' => 'Ada',
            'last_name' => 'Adviser',
            'is_admin' => false,
            'approved_status' => true,
            'created_at' => now(),
        ]);
    }

    public function test_booking_a_weekly_slot_stores_a_fixed_session_date_instead_of_a_weekday_name(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'booker@my.cspc.edu.ph']);
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Book', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $expertId = $this->makeExpert();

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId,
            'available_date' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'weekly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $booking = DB::table('consultation_booking')->where('schedule_id', $scheduleId)->first();

        $this->assertNotNull($booking->session_date, 'session_date must be captured at booking time.');
        $this->assertSame(
            \Illuminate\Support\Carbon::parse($booking->session_date)->dayOfWeek,
            \Illuminate\Support\Carbon::parse('Tuesday')->dayOfWeek,
            'The stored date must actually fall on the slot\'s weekday.'
        );
    }

    public function test_booking_a_once_slot_stores_its_exact_specific_date(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'booker2@my.cspc.edu.ph']);
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Once', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $expertId = $this->makeExpert();

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId,
            'available_date' => 'Wednesday',
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'once',
            'specific_date' => '2026-07-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId,
        ]);

        $response->assertOk();

        $booking = DB::table('consultation_booking')->where('schedule_id', $scheduleId)->first();
        $this->assertSame('2026-07-15', \Illuminate\Support\Carbon::parse($booking->session_date)->toDateString());
    }

    public function test_booking_a_later_occurrence_of_a_recurring_slot_succeeds_once_the_first_is_done(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'booker3@my.cspc.edu.ph']);
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Recur', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $expertId = $this->makeExpert();

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId,
            'available_date' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'weekly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstTuesday = \Illuminate\Support\Carbon::parse('next Tuesday')->toDateString();
        $secondTuesday = \Illuminate\Support\Carbon::parse('next Tuesday')->addWeek()->toDateString();

        // Book the nearest occurrence first.
        $first = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId,
            'session_date' => $firstTuesday,
        ]);
        $first->assertOk();
        $first->assertJson(['success' => true]);

        // A team can only have one ACTIVE booking at a time, so a later
        // occurrence of the same recurring schedule_id is correctly refused
        // while the first is still scheduled...
        $tooSoon = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId,
            'session_date' => $secondTuesday,
        ]);
        $tooSoon->assertStatus(422);
        $tooSoon->assertJsonFragment(['success' => false]);

        // ...but once that first booking is finished, booking the later
        // occurrence must succeed - proving the date-scoping (not just
        // schedule_id) still works correctly once the team is free again.
        DB::table('consultation_booking')->where('schedule_id', $scheduleId)->update(['booking_status' => 'done']);

        $second = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId,
            'session_date' => $secondTuesday,
        ]);
        $second->assertOk();
        $second->assertJson(['success' => true]);

        $booking = DB::table('consultation_booking')->where('schedule_id', $scheduleId)->where('booking_status', 'scheduled')->first();
        $this->assertSame($secondTuesday, \Illuminate\Support\Carbon::parse($booking->session_date)->toDateString());
    }

    public function test_team_cannot_book_a_new_slot_while_it_has_an_active_booking(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'booker4@my.cspc.edu.ph']);
        $team = DB::table('team')->insertGetId(['team_name' => 'Team OneAtATime', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $expertId = $this->makeExpert();

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId,
            'available_date' => 'Thursday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'weekly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A second, entirely different slot/expert - the restriction is
        // team-wide, not scoped to one schedule_id.
        $otherExpertId = DB::table('capstone_experts')->insertGetId([
            'email' => 'other-expert@gmail.com',
            'first_name' => 'Other',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => true,
            'created_at' => now(),
        ]);
        $otherScheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $otherExpertId,
            'available_date' => 'Friday',
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'weekly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pinned to "next Thursday"/"next Friday" (always strictly after
        // today, even when today itself is a Thursday/Friday) rather than
        // relying on nextOccurrenceOnOrAfter()'s default pick of "today" -
        // if the suite runs after the slot's own end_time on a day that
        // matches its weekday, markPastAsDone() correctly (by design) flips
        // an already-ended "today" booking to 'done' before the second
        // request, which frees up the team and makes this assertion flaky
        // depending purely on what time of day the tests happen to run.
        $first = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId,
            'session_date' => \Illuminate\Support\Carbon::parse('next Thursday')->toDateString(),
        ]);
        $first->assertOk();
        $first->assertJson(['success' => true]);

        $blocked = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $otherScheduleId,
            'session_date' => \Illuminate\Support\Carbon::parse('next Friday')->toDateString(),
        ]);
        $blocked->assertStatus(422);
        $blocked->assertJson([
            'success' => false,
            'message' => 'Your team already has an active consultation booking. You can book another once that session is finished.',
        ]);

        // Cancelling the active booking frees the team up immediately.
        DB::table('consultation_booking')->where('schedule_id', $scheduleId)->update(['booking_status' => 'cancelled']);

        $afterCancel = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $otherScheduleId,
        ]);
        $afterCancel->assertOk();
        $afterCancel->assertJson(['success' => true]);
    }

    public function test_consultation_page_renders_when_a_booking_schedule_no_longer_exists(): void
    {
        // A booking whose schedule relation resolves to null (e.g. the
        // real MySQL database has 5 rows like this in production, despite
        // the FK being declared onDelete('cascade') - some path bypasses
        // it). The view must gracefully show a dash instead of crashing on
        // ->schedule->start_time.
        //
        // Simulated by nulling the loaded relation directly rather than by
        // deleting the referenced row: SQLite (used in tests) actually
        // enforces that FK, and disabling enforcement requires toggling a
        // pragma that SQLite refuses to change mid-transaction - which
        // RefreshDatabase always has one of active.
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'orphaned@my.cspc.edu.ph']);
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Orphaned', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $expertId = $this->makeExpert();

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId,
            'available_date' => 'Wednesday',
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'once',
            'specific_date' => now()->addDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = DB::table('consultation_booking')->insertGetId([
            'student_id' => $student->student_id,
            'schedule_id' => $scheduleId,
            'session_date' => now()->addDay()->toDateString(),
            'booking_status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = \App\Models\ConsultationBooking::find($bookingId);
        $booking->setRelation('schedule', null);
        $orphanedBookings = collect([$booking]);

        $this->actingAs($student, 'web');

        $html = view('student.consultation', [
            'experts' => collect(),
            'defaultExpertId' => null,
            'availableSlots' => collect(),
            'fullOccurrences' => collect(),
            'upcomingBookings' => $orphanedBookings,
            'recentBookings' => collect(),
            'teamId' => $team,
            'student' => $student,
            'teamHasActiveBooking' => false,
        ])->render();

        // The literal formatted date can only come from the server-rendered
        // @forelse loop (the page's JS template for this same markup uses a
        // ${...} placeholder, not a real date string), proving the loop
        // actually ran for this booking rather than silently skipping it.
        $this->assertStringContainsString($booking->session_date->format('M d, Y'), $html);
        $this->assertStringContainsString('Team Orphaned', $html);
    }

    public function test_my_booked_dates_are_tagged_with_expert_id_not_shared_across_experts(): void
    {
        // Regression: the "you have a booking here" calendar dot used to be
        // driven by a flat list of dates with no expert attached, so it
        // showed identically no matter which expert was selected in the
        // Target Expert dropdown - switching from the expert you actually
        // booked with to a completely different one still showed the same
        // booked-date dots, making it look like that other expert somehow
        // shared the first expert's schedule.
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'taggedbooking@my.cspc.edu.ph']);
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Tagged', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $expertId = $this->makeExpert();

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId,
            'available_date' => 'Wednesday',
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'once',
            'specific_date' => now()->addDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('consultation_booking')->insert([
            'student_id' => $student->student_id,
            'schedule_id' => $scheduleId,
            'session_date' => now()->addDay()->toDateString(),
            'booking_status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student, 'web')->get(route('student.consultation'));
        $response->assertOk();

        preg_match('/let myBookedDates = (\[.*?\]);/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'myBookedDates must be present in the rendered page.');

        $myBookedDates = json_decode($matches[1], true);
        $this->assertCount(1, $myBookedDates);
        $this->assertSame($expertId, $myBookedDates[0]['expert_id'], 'Each booked date must carry the expert_id it was actually booked with.');
    }
}
