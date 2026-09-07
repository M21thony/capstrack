<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\RecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Who a document is offered to on the submission page.
 *
 * Both of the things pinned down here were found by a team looking at their
 * own screen. Neither raised an error: one silently offered to send the
 * document to the same person twice, and the other silently offered to send
 * it to nobody.
 */
class SubmissionRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): RecipientResolver
    {
        return app(RecipientResolver::class);
    }

    private function expertHolding(string $name, array $roleNames): CapstoneExpert
    {
        [$first, $last] = explode(' ', $name, 2);

        $expert = CapstoneExpert::factory()->create([
            'first_name' => $first, 'last_name' => $last,
            'approved_status' => true, 'is_admin' => false,
        ]);

        foreach ($roleNames as $roleName) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $roleName])->role_id);
        }

        return $expert;
    }

    /** The team engages this expert as exactly one of the roles they hold. */
    private function engage(Team $team, CapstoneExpert $expert, string $asRole): void
    {
        $expertRoleId = DB::table('expert_roles as er')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('er.expert_id', $expert->expert_id)
            ->where('r.role_name', $asRole)
            ->value('er.expert_role_id');

        $this->assertNotNull($expertRoleId, "the expert holds the {$asRole} role");

        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $expertRoleId,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** A team whose one member computes to the given section. */
    private function teamInSection(string $course = 'BSIT', string $yearSection = '4th · A'): Team
    {
        $student = Student::create([
            'email' => 'member@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => $course, 'year_section' => $yearSection,
        ]);

        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return $team;
    }

    private function capsa(array $sections): CapstoneExpert
    {
        $capsa = $this->expertHolding('Grom Hellscream', [CapstoneExpert::CAPSA_ROLE]);
        $capsa->update(['sections' => $sections]);

        return $capsa;
    }

    // ----------------------------------------------------------------
    // Engaged as one role, not every role they hold
    // ----------------------------------------------------------------

    /**
     * Most experts hold both adviser and consultant. The resolver used to
     * join expert_roles by expert_id and match on the role's name, which
     * asks what roles a person holds anywhere rather than what this team
     * engaged them as - so an approved adviser who also holds the consultant
     * role came back as the team's consultant too, and the page offered to
     * send them the same document twice.
     */
    public function test_an_expert_fills_only_the_role_they_were_engaged_as(): void
    {
        $team = $this->teamInSection();
        $mark = $this->expertHolding('Mark Balilla', ['adviser', 'consultant']);

        $this->engage($team, $mark, 'adviser');

        $names = $this->resolver()->getAssignedNames($team->team_id);

        $this->assertSame('Mark Balilla', $names['adviser']['name']);
        $this->assertNull($names['consultant']['name'], 'they were not engaged as the consultant');
        $this->assertNull($names['grammarian']['name']);
    }

    /** Two different people in two roles both come back. */
    public function test_separate_experts_fill_separate_roles(): void
    {
        $team = $this->teamInSection();
        $this->engage($team, $this->expertHolding('Mark Balilla', ['adviser', 'consultant']), 'adviser');
        $this->engage($team, $this->expertHolding('Rosa Villanueva', ['consultant']), 'consultant');

        $names = $this->resolver()->getAssignedNames($team->team_id);

        $this->assertSame('Mark Balilla', $names['adviser']['name']);
        $this->assertSame('Rosa Villanueva', $names['consultant']['name']);
    }

    /**
     * One expert, one role per team - the database says so.
     *
     * capstone_expert_application carries a unique index on (team_id,
     * expert_id), and the apply form states the same rule in words. Which
     * makes the bug above worse than it looked: the schema forbids what the
     * submission page was displaying.
     */
    public function test_the_same_expert_cannot_hold_two_roles_on_one_team(): void
    {
        $team = $this->teamInSection();
        $mark = $this->expertHolding('Mark Balilla', ['adviser', 'consultant']);

        $this->engage($team, $mark, 'adviser');

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->engage($team, $mark, 'consultant');
    }

    /** An application still waiting on a decision is not an assignment. */
    public function test_an_unapproved_application_fills_nothing(): void
    {
        $team = $this->teamInSection();
        $mark = $this->expertHolding('Mark Balilla', ['adviser']);
        $this->engage($team, $mark, 'adviser');

        DB::table('capstone_expert_application')->update(['status' => 'pending']);

        $this->assertNull($this->resolver()->getAssignedNames($team->team_id)['adviser']['name']);
    }

    // ----------------------------------------------------------------
    // The CaPSA
    // ----------------------------------------------------------------

    /**
     * It used to match capstone_experts.program against student_s_y.program.
     * Nothing fills either column - a CaPSA is given sections, and a
     * student's programme lives on students.course - so both were NULL, and
     * "WHERE program = NULL" is never true. The Subject Adviser read "Not yet
     * assigned" permanently, with nothing ever erroring.
     */
    public function test_the_capsa_is_found_by_the_teams_section(): void
    {
        $team = $this->teamInSection('BSIT', '4th · A');
        $this->capsa(['BSIT-4A', 'BSIT-4B']);

        $names = $this->resolver()->getAssignedNames($team->team_id);

        $this->assertSame('Grom Hellscream', $names['subject_adviser']['name']);
    }

    /** A CaPSA who does not cover that section is not this team's. */
    public function test_a_capsa_covering_other_sections_is_not_returned(): void
    {
        $team = $this->teamInSection('BSIT', '4th · A');
        $this->capsa(['BSIT-3A']);

        $this->assertNull($this->resolver()->getAssignedNames($team->team_id)['subject_adviser']['name']);
    }

    /**
     * A team whose members have incomplete profiles has no computable
     * section, so no CaPSA can be named. Nothing is guessed - naming the
     * wrong one would send the document to somebody who is not theirs.
     */
    public function test_no_section_means_no_subject_adviser(): void
    {
        $student = Student::create([
            'email' => 'blank@my.cspc.edu.ph', 'f_name' => 'Ana', 'l_name' => 'Cruz',
        ]);
        $team = Team::create(['team_name' => 'Nameless', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $this->capsa(['BSIT-4A']);

        $this->assertNull($this->resolver()->getAssignedNames($team->team_id)['subject_adviser']['name']);
    }

    /** The single-role entry point agrees with the four-at-once one. */
    public function test_resolve_agrees_with_get_assigned_names(): void
    {
        $team = $this->teamInSection();
        $mark = $this->expertHolding('Mark Balilla', ['adviser', 'consultant']);
        $this->engage($team, $mark, 'adviser');
        $capsa = $this->capsa(['BSIT-4A']);

        $this->assertSame($mark->expert_id, $this->resolver()->resolve('adviser', $team->team_id));
        $this->assertNull($this->resolver()->resolve('consultant', $team->team_id));
        $this->assertSame($capsa->expert_id, $this->resolver()->resolve('subject_adviser', $team->team_id));
    }
}
