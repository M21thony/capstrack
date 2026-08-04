<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentConsultationExpertListTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(string $email, string $first, string $last): int
    {
        return DB::table('capstone_experts')->insertGetId([
            'email' => $email,
            'first_name' => $first,
            'last_name' => $last,
            'is_admin' => false,
            'approved_status' => true,
            'created_at' => now(),
        ]);
    }

    private function makeRole(string $name): int
    {
        return DB::table('roles')->insertGetId([
            'role_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_only_shows_experts_assigned_and_accepted_on_the_students_team_and_defaults_to_adviser(): void
    {
        $team = DB::table('team')->insertGetId([
            'team_name' => 'Team Alpha',
            'team_status' => 'on-going',
        ]);

        $student = Student::factory()->withCompleteProfile()->create(['email' => 'consult@my.cspc.edu.ph']);

        DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $adviserRoleId = $this->makeRole('adviser');
        $consultantRoleId = $this->makeRole('consultant');

        $adviserId = $this->makeExpert('adviser@gmail.com', 'Ada', 'Adviser');
        DB::table('expert_roles')->insert(['expert_id' => $adviserId, 'role_id' => $adviserRoleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team,
            'expert_id' => $adviserId,
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assigned but still pending — should not appear yet.
        $consultantId = $this->makeExpert('consultant@gmail.com', 'Cara', 'Consultant');
        DB::table('expert_roles')->insert(['expert_id' => $consultantId, 'role_id' => $consultantRoleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team,
            'expert_id' => $consultantId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Approved expert entirely unrelated to this team — should never appear.
        $strangerId = $this->makeExpert('stranger@gmail.com', 'Ian', 'Stranger');
        DB::table('expert_roles')->insert(['expert_id' => $strangerId, 'role_id' => $adviserRoleId]);

        // Slots for both the adviser and the unrelated stranger.
        DB::table('consultation_schedule')->insert([
            'expert_id' => $adviserId,
            'available_date' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'weekly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('consultation_schedule')->insert([
            'expert_id' => $strangerId,
            'available_date' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'weekly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student, 'web')->get(route('student.consultation'));

        $response->assertOk();

        $experts = $response->viewData('experts');
        $this->assertCount(1, $experts, 'Only the accepted adviser should be listed.');
        $this->assertSame($adviserId, $experts->first()->expert_id);
        $this->assertSame($adviserId, $response->viewData('defaultExpertId'));

        $slots = $response->viewData('availableSlots');
        $this->assertCount(1, $slots);
        $this->assertSame($adviserId, $slots->first()->expert_id);

        $response->assertSee('Ada Adviser');
        $response->assertDontSee('Cara Consultant');
        $response->assertDontSee('Ian Stranger');
    }

    public function test_shows_no_experts_when_student_has_no_team(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'noteam@my.cspc.edu.ph']);

        $response = $this->actingAs($student, 'web')->get(route('student.consultation'));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('experts'));
        $this->assertNull($response->viewData('defaultExpertId'));
    }
}
