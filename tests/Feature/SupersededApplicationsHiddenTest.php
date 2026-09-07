<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A team sees where an application got to, once.
 *
 * Re-applying after a decline makes a second row rather than replacing the
 * first - the index that prevented it was dropped so a team could try again.
 * The declined row then sat beside the approved one, so a team who had been
 * turned down and then accepted by the same person saw both.
 */
class SupersededApplicationsHiddenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_declined_application_is_replaced_by_the_later_approved_one(): void
    {
        $adviser = Role::firstOrCreate(['role_name' => 'adviser']);

        $expert = CapstoneExpert::factory()->create([
            'first_name' => 'Neil', 'last_name' => 'Inocencio', 'approved_status' => true,
        ]);
        $expert->roles()->attach($adviser->role_id);
        $link = DB::table('expert_roles')->where('expert_id', $expert->expert_id)->value('expert_role_id');

        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentRoster::create([
            'email' => $student->email, 'name' => 'Mark Balilla', 'section' => '4th · A',
            'program' => 'BSIT', 'school_year' => \App\Models\Term::currentSchoolYear(),
        ]);
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        // Turned down, then applied to again and accepted.
        TeamExpertAssignment::create([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id,
            'expert_role_id' => $link, 'status' => 'rejected',
            'rejection_reason' => 'Tighten the scope and come back to me.',
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);
        TeamExpertAssignment::create([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id,
            'expert_role_id' => $link, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $shown = $this->actingAs($student, 'web')
            ->get('/student/experts')->assertOk()->viewData('assignments');

        $forThisExpert = $shown->where('expert_id', $expert->expert_id);

        $this->assertCount(1, $forThisExpert, 'the declined attempt is superseded, not a second card');
        $this->assertSame('approved', $forThisExpert->first()->status);
    }
}
