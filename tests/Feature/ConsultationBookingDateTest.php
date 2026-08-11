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
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Book', 'team_status' => 'on-going']);
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
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Once', 'team_status' => 'on-going']);
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

    public function test_booking_a_later_occurrence_of_an_already_booked_recurring_slot_succeeds(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'booker3@my.cspc.edu.ph']);
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Recur', 'team_status' => 'on-going']);
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

        // Booking a LATER occurrence of the same recurring schedule_id must
        // succeed, not be rejected as "already booked" — that check has to
        // be scoped by session_date, not just schedule_id.
        $second = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId,
            'session_date' => $secondTuesday,
        ]);
        $second->assertOk();
        $second->assertJson(['success' => true]);

        $this->assertSame(
            2,
            DB::table('consultation_booking')->where('schedule_id', $scheduleId)->where('booking_status', 'scheduled')->count()
        );

        // Re-booking the SAME date the student already has must still be
        // rejected as a genuine duplicate.
        $duplicate = $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId,
            'session_date' => $firstTuesday,
        ]);
        $duplicate->assertStatus(422);
        $duplicate->assertJson(['success' => false, 'message' => 'You already booked this date.']);
    }
}
