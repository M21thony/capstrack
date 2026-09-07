<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four capstone team roles exist, and the leader's one can be found.
 *
 * student_roles was emptied and nothing anywhere said so. Every caller that
 * wants the leader's role goes through StudentRole::leaderRoleId(), which
 * answers null on an empty table - and null is an acceptable s_role_id, so
 * teams carried on being created with nobody recorded as holding any role
 * at all. It took somebody reading a team card to notice.
 *
 * The seeder that was supposed to fill the table wrote to a column called
 * role_name, which is neither the column on the table nor in the model's
 * fillable list, so the names were dropped in silence. It also invented two
 * roles - "Leader" and "Member" - that the course does not have, and it was
 * never called from DatabaseSeeder in the first place.
 *
 * So: the names are checked against the course document, and the lookup is
 * checked to actually return something.
 */
class CapstoneTeamRolesTest extends TestCase
{
    use RefreshDatabase;

    /** Straight from the course's Capstone Team Roles table. */
    private const EXPECTED = [
        'Project Head/Database Designer',
        'Network Designer/UI Designer',
        'Software Engineer/Programmer',
        'QA Tester/Documentation Writer',
    ];

    public function test_the_four_roles_are_seeded(): void
    {
        (new StudentRoleSeeder)->run();

        $this->assertSame(
            self::EXPECTED,
            StudentRole::orderBy('s_role_id')->pluck('s_role_name')->all()
        );
    }

    /** Every one of them says what the person is for. */
    public function test_each_role_carries_its_duties(): void
    {
        (new StudentRoleSeeder)->run();

        foreach (StudentRole::all() as $role) {
            $this->assertNotEmpty($role->s_role_description, $role->s_role_name.' has duties');
        }
    }

    /**
     * The constant and the seeded name have to be the same string. When they
     * drift, leaderRoleId() returns null and every leader created afterwards
     * is recorded as holding no role - with no error raised anywhere.
     */
    public function test_the_leader_role_can_actually_be_found(): void
    {
        (new StudentRoleSeeder)->run();

        $this->assertNotNull(
            StudentRole::leaderRoleId(),
            'LEADER_ROLE_NAME has to match a row the seeder writes'
        );
        $this->assertContains(StudentRole::LEADER_ROLE_NAME, self::EXPECTED);
    }

    /** Running it twice does not double the table or move any id. */
    public function test_seeding_twice_changes_nothing(): void
    {
        (new StudentRoleSeeder)->run();
        $before = StudentRole::orderBy('s_role_id')->pluck('s_role_id')->all();

        (new StudentRoleSeeder)->run();

        $this->assertSame($before, StudentRole::orderBy('s_role_id')->pluck('s_role_id')->all());
        $this->assertCount(4, $before);
    }

    /** A new team's leader is recorded as the Project Head. */
    public function test_creating_a_team_records_the_leader_as_project_head(): void
    {
        (new StudentRoleSeeder)->run();

        $student = Student::create([
            'email' => 'head@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id]);

        $this->actingAs($student, 'web')
            ->post(route('team.store'), ['team_name' => 'Bulbasaurus'])
            ->assertRedirect();

        $team = Team::where('leader_id', $student->student_id)->first();
        $this->assertNotNull($team, 'the team was created');

        $enrolment = StudentSchoolYear::where('student_id', $student->student_id)->first();

        $this->assertSame(StudentRole::leaderRoleId(), $enrolment->s_role_id);
        $this->assertSame(StudentRole::LEADER_ROLE_NAME, $enrolment->role->s_role_name);
    }
}
