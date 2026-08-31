<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DuplicationCheck;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a panel decides at the end of a defense.
 *
 * The panel confers and gives the team one result, so this is a single
 * decision recorded by the Panel Chair - not a verdict collected from each
 * panelist and then tallied, which is what the grading form used to do
 * without any rule for what a split meant.
 */
class DefenseOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private GradingRubric $rubric;

    /** @return array{0: Team, 1: DefenseSchedule, 2: CapstoneExpert, 3: CapstoneExpert} */
    private function makeDefense(string $defenseType = 'Pre-proposal Defense'): array
    {
        $this->rubric = GradingRubric::create([
            'rubric_name' => $defenseType.' Rubric',
            'rubric_type' => $defenseType,
            'total_points' => 100,
            'is_active' => true,
        ]);

        $team = Team::create([
            'team_name' => 'Defending Team',
            'team_status' => 'Active',
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        $student = Student::factory()->withCompleteProfile()->create(['email' => 'member@my.cspc.edu.ph']);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => $defenseType,
            'attempt' => 1,
        ]);

        $chair = CapstoneExpert::factory()->create(['approved_status' => true, 'email' => 'chair@gmail.com']);
        $member = CapstoneExpert::factory()->create(['approved_status' => true, 'email' => 'member@gmail.com']);

        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $member->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        return [$team, $schedule, $chair, $member];
    }

    private function grade(DefenseSchedule $schedule, CapstoneExpert $expert, float $score = 85): void
    {
        PanelGrade::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $expert->expert_id,
            'rubric_id' => $this->rubric->rubric_id,
            'scores' => [],
            'total_score' => $score,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    private function recordOutcome(CapstoneExpert $as, DefenseSchedule $schedule, string $outcome, ?string $remarks = null)
    {
        return $this->actingAs($as, 'expert')->postJson(
            "/instructor/grading/{$schedule->defense_id}/outcome",
            array_filter(['outcome' => $outcome, 'remarks' => $remarks]),
        );
    }

    public function test_the_chair_records_the_panels_decision(): void
    {
        [, $schedule, $chair, $member] = $this->makeDefense();
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::PASSED, 'Well argued.')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $schedule->refresh();
        $this->assertSame(DefenseOutcome::PASSED, $schedule->outcome);
        $this->assertSame('Well argued.', $schedule->outcome_remarks);
        $this->assertSame($chair->expert_id, $schedule->outcome_recorded_by);
        $this->assertNotNull($schedule->outcome_recorded_at);
    }

    public function test_a_panel_member_cannot_record_the_decision(): void
    {
        [, $schedule, $chair, $member] = $this->makeDefense();
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $this->recordOutcome($member, $schedule, DefenseOutcome::PASSED)->assertStatus(403);

        $this->assertNull($schedule->fresh()->outcome);
    }

    // A briefing cannot have happened while someone still has not marked.
    public function test_the_decision_waits_until_every_panelist_has_scored(): void
    {
        [, $schedule, $chair] = $this->makeDefense();
        $this->grade($schedule, $chair);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::PASSED)->assertStatus(422);

        $this->assertNull($schedule->fresh()->outcome);
    }

    public function test_the_decision_cannot_be_recorded_twice(): void
    {
        [, $schedule, $chair, $member] = $this->makeDefense();
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::PASSED)->assertStatus(200);
        $this->recordOutcome($chair, $schedule, DefenseOutcome::REDEFENSE)->assertStatus(422);

        $this->assertSame(DefenseOutcome::PASSED, $schedule->fresh()->outcome);
    }

    // Only a pre-proposal can be sent back for a whole new title; by Final
    // Oral Defense that is not something anyone can ask for.
    public function test_a_later_stage_cannot_send_a_team_back_for_a_new_title(): void
    {
        [, $schedule, $chair, $member] = $this->makeDefense('Final Oral Defense');
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::REDEFENSE_NEW_TITLE)->assertStatus(422);

        $this->assertNull($schedule->fresh()->outcome);
    }

    public function test_a_later_stage_cannot_pass_a_team_with_revisions(): void
    {
        [, $schedule, $chair, $member] = $this->makeDefense('Title Defense');
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::PASSED_WITH_REVISION)->assertStatus(422);
    }

    // A redefense has to actually exist, or someone has to remember to make
    // one by hand.
    public function test_a_redefense_outcome_opens_the_next_attempt(): void
    {
        [$team, $schedule, $chair, $member] = $this->makeDefense();
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::REDEFENSE)->assertStatus(200);

        $attempts = DefenseSchedule::where('team_id', $team->team_id)
            ->where('defense_type', 'Pre-proposal Defense')
            ->orderBy('attempt')
            ->get();

        $this->assertCount(2, $attempts);
        $this->assertSame(2, (int) $attempts->last()->attempt);
        // The new attempt is unscheduled and unpanelled - both are the
        // CAPSA's to set, the same as any first attempt.
        $this->assertNull($attempts->last()->schedule_date);
        $this->assertNull($attempts->last()->outcome);
    }

    public function test_passing_does_not_open_another_attempt(): void
    {
        [$team, $schedule, $chair, $member] = $this->makeDefense();
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::PASSED)->assertStatus(200);

        $this->assertSame(1, DefenseSchedule::where('team_id', $team->team_id)->count());
    }

    /**
     * "Redefense with a new title" means exactly that, so the team's
     * pre-proposals are handed back for them to put a new one up.
     */
    public function test_a_new_title_outcome_reopens_the_teams_pre_proposals(): void
    {
        [$team, $schedule, $chair, $member] = $this->makeDefense();
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $sy = StudentSchoolYear::where('team_id', $team->team_id)->first();
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'checks/one.pdf',
            'concepts' => [],
            'top_score' => 10,
            'verdict' => 'Passed',
            'matches' => [],
            'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => 'The Title The Panel Has Just Sent This Team Back About',
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile',
            'approved_status' => true,
            'submitted_at' => now(),
        ]);
        $team->update(['selected_pre_proposal_id' => $proposal->id, 'project_title' => $proposal->title]);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::REDEFENSE_NEW_TITLE)->assertStatus(200);

        $team->refresh();
        $proposal->refresh();

        // Handed back, not deleted: what was tried stays on the record.
        $this->assertNull($proposal->submitted_at);
        $this->assertFalse((bool) $proposal->approved_status);
        $this->assertNull($team->selected_pre_proposal_id);
        $this->assertNull($team->project_title);
        $this->assertFalse($team->hasSubmittedPreProposals());
    }

    public function test_a_plain_redefense_leaves_the_pre_proposals_alone(): void
    {
        [$team, $schedule, $chair, $member] = $this->makeDefense();
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $sy = StudentSchoolYear::where('team_id', $team->team_id)->first();
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'checks/one.pdf',
            'concepts' => [], 'top_score' => 10, 'verdict' => 'Passed',
            'matches' => [], 'instructors_suggested' => [],
        ]);
        CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => 'A Title This Team Keeps Because Only The Defense Repeats',
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile',
            'approved_status' => true,
            'submitted_at' => now(),
        ]);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::REDEFENSE)->assertStatus(200);

        $this->assertTrue($team->fresh()->hasSubmittedPreProposals());
    }

    public function test_the_team_is_told_what_the_panel_decided(): void
    {
        [$team, $schedule, $chair, $member] = $this->makeDefense();
        $this->grade($schedule, $chair);
        $this->grade($schedule, $member);

        $this->recordOutcome($chair, $schedule, DefenseOutcome::PASSED)->assertStatus(200);

        $studentId = StudentSchoolYear::where('team_id', $team->team_id)->value('student_id');
        $this->assertDatabaseHas('notifications', [
            'student_id' => $studentId,
            'type' => 'defense_outcome',
        ]);
    }

    public function test_the_options_offered_depend_on_the_stage(): void
    {
        $this->assertSame(
            [DefenseOutcome::PASSED, DefenseOutcome::PASSED_WITH_REVISION, DefenseOutcome::REDEFENSE, DefenseOutcome::REDEFENSE_NEW_TITLE],
            DefenseOutcome::forStage('Pre-proposal Defense'),
        );

        foreach (['Title Defense', 'Pre Oral Defense', 'Final Oral Defense'] as $stage) {
            $this->assertSame([DefenseOutcome::PASSED, DefenseOutcome::REDEFENSE], DefenseOutcome::forStage($stage));
        }
    }
}
