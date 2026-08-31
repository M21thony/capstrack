<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The duplication report suggests experts for a pre-proposal. Applying to
 * one is only open once that pre-proposal has been approved - the expert is
 * being asked to take on a topic that cleared review, not a draft.
 */
class DuplicationReportApplyLockTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaderWithTeam(): array
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create([
            'team_name' => 'Reporting Team',
            'team_status' => 'Active',
            'leader_id' => $student->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        $sy = StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return [$student, $team, $sy];
    }

    private function makeSuggestedExpert(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'last_name' => 'Suggested',
            'max_teams' => 4,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    /** A saved pre-proposal with a duplication check that suggests $expert. */
    private function makeProposalWithReport(Team $team, CapstoneExpert $expert, string $status, $sy): void
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'proposals/report-test.pdf',
            'top_score' => 21,
            'verdict' => 'Low Similarity',
            'matches' => [],
            'concepts' => ['title' => 'A Capstone Title'],
            'instructors_suggested' => [[
                'id' => $expert->expert_id,
                'name' => $expert->name,
                'expertise' => 'Machine Learning',
                'reason' => 'Matches the proposal domain.',
            ]],
        ]);

        // status is derived from approved_status / rejection_reason.
        CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'title' => 'A Capstone Title',
            'slot' => 1,
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Education'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'Some limitations.',
            'sdm' => 'Agile',
            'approved_status' => $status === 'approved',
        ]);
    }

    public function test_apply_is_locked_while_the_pre_proposal_is_pending(): void
    {
        [$student, $team, $sy] = $this->makeLeaderWithTeam();
        $expert = $this->makeSuggestedExpert();
        $this->makeProposalWithReport($team, $expert, 'pending', $sy);

        $response = $this->actingAs($student, 'web')->get('/student/duplication');

        $response->assertStatus(200);
        // The lock has to name who unblocks it, not just that it is locked.
        $response->assertSee('Needs CaPSA approval');
        $response->assertSee('Your CaPSA has to approve this pre-proposal');
        $response->assertDontSee('student/experts?apply='.$expert->expert_id, false);
    }

    public function test_apply_opens_once_the_pre_proposal_is_approved(): void
    {
        [$student, $team, $sy] = $this->makeLeaderWithTeam();
        $expert = $this->makeSuggestedExpert();
        $this->makeProposalWithReport($team, $expert, 'approved', $sy);

        $response = $this->actingAs($student, 'web')->get('/student/duplication');

        $response->assertStatus(200);
        $response->assertSee('apply='.$expert->expert_id, false);
        // Matched on the saved-proposal wording specifically: the fresh-check
        // report carries its own always-locked message in page scripts, so a
        // looser needle would match that instead and never fail.
        $response->assertDontSee('Your CaPSA has to approve this pre-proposal');
    }
}
