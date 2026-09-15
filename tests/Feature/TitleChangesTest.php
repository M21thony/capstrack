<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * When a title may change after the Pre-proposal Defense: never after a
 * plain pass, reworded after a pass with revision until the final title form
 * is in, and replaced after a redefense with a new title.
 */
class TitleChangesTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'CapsTrack Centralized Capstone Project Management System for College Computer Studies Students';

    private const REVISED = 'CapsTrack Centralized Capstone Project Tracking Platform with Adviser Scheduling for College Computer Studies Students';

    /** @return array{0: Team, 1: Student, 2: CapstonePreProposal} */
    private function team(): array
    {
        $leader = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Bulbasaur', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $leader->student_id]);
        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        $proposal = $this->proposal($team, $sy->s_y_id, 1, self::TITLE, approved: true);
        $team->update(['selected_pre_proposal_id' => $proposal->id, 'project_title' => self::TITLE]);

        return [$team->fresh(), $leader, $proposal];
    }

    private function proposal(Team $team, int $syId, int $slot, string $title, bool $approved = false): CapstonePreProposal
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => $syId, 'document_path' => "checks/{$slot}.pdf", 'concepts' => [],
            'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => $slot, 'title' => $title,
            'sdg_goals' => 'SDG 4', 'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile',
            'approved_status' => $approved, 'submitted_at' => now(),
        ]);
    }

    private function decided(Team $team, string $outcome): void
    {
        DefenseSchedule::create([
            'team_id' => $team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1,
            'outcome' => $outcome, 'outcome_recorded_at' => now()->subHour(),
        ]);
    }

    private function revise(Student $leader, string $title)
    {
        return $this->actingAs($leader)->patch(route('student.pre-proposal.title.update'), ['title' => $title]);
    }

    public function test_a_title_passed_with_revision_can_be_reworded_until_the_final_title_form_is_in(): void
    {
        [$team, $leader, $proposal] = $this->team();
        $this->decided($team, DefenseOutcome::PASSED_WITH_REVISION);

        $this->revise($leader, self::REVISED)->assertSessionHas('status');
        $this->assertSame(self::REVISED, $team->fresh()->project_title);
        // The approved pre-proposal keeps the title as it was approved.
        $this->assertSame(self::TITLE, $proposal->fresh()->title);

        // The final title form goes in: from here the title is final.
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true]);
        $checklist = DB::table('checklists')->insertGetId(['capsa_id' => $capsa->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1', 'created_at' => now(), 'updated_at' => now()]);
        $milestone = DB::table('checklist_milestones')->insertGetId(['checklist_id' => $checklist, 'name' => 'Pre-proposal', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $form = DB::table('deliverables')->insertGetId(['checklist_milestone_id' => $milestone, 'template_key' => 'final_title_form', 'title' => 'Signed Final Project Title Form', 'kind' => 'form', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        CapstoneDocument::create(['student_id' => $leader->student_id, 'file_name' => 'Final Title Form', 'deliverable_id' => $form, 'file_path' => 'submissions/final.pdf', 'file_version' => 1, 'document_status' => 'Pending']);

        $this->revise($leader, self::TITLE)->assertSessionHas('error');
        $this->assertSame(self::REVISED, $team->fresh()->project_title);
    }

    public function test_a_title_that_simply_passed_cannot_be_changed(): void
    {
        [$team, $leader] = $this->team();
        $this->decided($team, DefenseOutcome::PASSED);

        $this->revise($leader, self::REVISED)->assertSessionHas('error');
        $this->assertSame(self::TITLE, $team->fresh()->project_title);
    }

    public function test_a_team_sent_back_for_a_new_title_gets_its_new_approved_title(): void
    {
        [$team, $leader, $old] = $this->team();
        $this->decided($team, DefenseOutcome::REDEFENSE_NEW_TITLE);

        // What the outcome does: set the titles aside and clear the team's title.
        $team->preProposals()->update(['retired_at' => now()]);
        $team->update(['selected_pre_proposal_id' => null, 'project_title' => null]);

        $sy = StudentSchoolYear::where('team_id', $team->team_id)->first();
        $new = $this->proposal($team, $sy->s_y_id, 2, self::REVISED);

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.verify_teams.pre_proposals.update', $new), ['status' => 'approved'])
            ->assertOk();

        $team->refresh();
        // Already verified, and still its new title lands.
        $this->assertSame($new->id, $team->selected_pre_proposal_id);
        $this->assertSame(self::REVISED, $team->project_title);
        $this->assertNotNull($old->fresh()->retired_at);
    }
}
