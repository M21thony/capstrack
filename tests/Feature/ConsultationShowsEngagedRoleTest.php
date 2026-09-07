<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A team sees an expert as the role they engaged them as - nothing else.
 *
 * The picker joined expert_roles on expert_id, which asks what roles this
 * person holds anywhere. Somebody who advises one team and consults for
 * another therefore appeared to THIS team as both, whichever one they had
 * actually applied to them for.
 */
class ConsultationShowsEngagedRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expert_holding_two_roles_shows_only_the_engaged_one(): void
    {
        $adviserRole = Role::firstOrCreate(['role_name' => 'adviser']);
        $consultantRole = Role::firstOrCreate(['role_name' => 'consultant']);

        // Holds both, the way a real faculty member does.
        $expert = CapstoneExpert::factory()->create([
            'first_name' => 'Mark', 'last_name' => 'Balilla', 'approved_status' => true,
        ]);
        $expert->roles()->attach([$adviserRole->role_id, $consultantRole->role_id]);

        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Lead', 'l_name' => 'Er',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentRoster::create([
            'email' => $student->email, 'name' => 'Lead Er', 'section' => '4th · A',
            'program' => 'BSIT', 'school_year' => \App\Models\Term::currentSchoolYear(),
        ]);
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        // Engaged as consultant only.
        $consultantLink = DB::table('expert_roles')
            ->where('expert_id', $expert->expert_id)->where('role_id', $consultantRole->role_id)
            ->value('expert_role_id');

        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $consultantLink,
            'status' => 'approved',
        ]);

        $html = $this->actingAs($student, 'web')->get('/student/consultation')->assertOk()->content();

        $this->assertStringContainsString('Consultant', $html);
        $this->assertStringNotContainsString('Adviser, Consultant', $html);
        $this->assertStringNotContainsString('Consultant, Adviser', $html);
    }
}
