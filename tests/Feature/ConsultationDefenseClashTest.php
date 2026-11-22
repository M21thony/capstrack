<?php

namespace Tests\Feature;

use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsultationDefenseClashTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_slot_during_the_experts_defense_panel_cannot_be_booked(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'clash@my.cspc.edu.ph']);
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Clash', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert(['student_id' => $student->student_id, 'team_id' => $team, 'program' => 'BSCS', 'student_status' => 'Active']);

        $expertId = DB::table('capstone_experts')->insertGetId([
            'email' => 'panelist@gmail.com', 'first_name' => 'Pat', 'last_name' => 'Panelist',
            'is_admin' => false, 'approved_status' => true, 'created_at' => now(),
        ]);
        $slot = fn (string $from, string $to) => DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId, 'available_date' => 'Tuesday', 'start_time' => $from, 'end_time' => $to,
            'consultation_limit' => 1, 'is_available' => true, 'recur' => 'weekly', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $during = $slot('09:00:00', '10:00:00');
        $after = $slot('11:00:00', '12:00:00');

        // Another team's defense, 9:30-11:00 that Tuesday, with this expert on the panel.
        $tuesday = Carbon::parse('next Tuesday');
        $otherTeam = DB::table('team')->insertGetId(['team_name' => 'Other Team', 'team_status' => 'Active']);
        $defense = DefenseSchedule::create([
            'team_id' => $otherTeam, 'defense_type' => 'Pre Oral Defense', 'venue' => 'NAS Lab',
            'schedule_date' => $tuesday->copy()->setTime(9, 30), 'end_date' => $tuesday->copy()->setTime(11, 0),
        ]);
        DefensePanel::create(['defense_id' => $defense->defense_id, 'expert_id' => $expertId, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $book = fn (int $scheduleId) => $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $scheduleId, 'session_date' => $tuesday->toDateString(),
        ]);

        $book($during)->assertStatus(422)->assertJson(['success' => false, 'message' => 'Your expert is at a defense at that time. Pick another time.']);
        $this->assertDatabaseMissing('consultation_booking', ['schedule_id' => $during]);

        $book($after)->assertOk()->assertJson(['success' => true]);
    }
}
