<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Student;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\MilestoneProgress;
use App\Support\TitleRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The Signed Final Project Title Form is signed on the title the panel
 * passed, so it cannot be closed before the Pre-proposal Defense is passed.
 */
class FinalTitleFormWaitsForPreProposalPassTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Team $team;

    private Student $student;

    private Checklist $checklist;

    private Deliverable $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor();
        [$this->team, $this->student] = $this->teamInSection('Bulbasaur');
        $this->checklist = $this->emptyChecklist($this->capsa);
        // Above the defense on purpose: the order inside the milestone is not what holds it.
        $this->form = $this->addDeliverable($this->checklist, ['title' => 'Signed Final Project Title Form', 'template_key' => TitleRevision::FINAL_TITLE_FORM]);
        $this->addDeliverable($this->checklist, ['title' => 'Pre-Proposal Oral Presentation', 'kind' => Deliverable::DEFENSE, 'defense_type' => DefenseOutcome::PRE_PROPOSAL]);
        $this->actingAs($this->capsa, 'expert');
    }

    private function checkUrl(Deliverable $deliverable): string
    {
        return route('capsa.deliverables.checks.update', [$deliverable->id, $this->team->team_id]);
    }

    private function passPreProposal(?Team $team = null): void
    {
        DefenseSchedule::create([
            'team_id' => ($team ?? $this->team)->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1,
            'schedule_date' => now()->subDay(), 'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now()->subDay(),
        ]);
    }

    public function test_it_is_locked_until_the_pre_proposal_defense_is_passed(): void
    {
        $row = $this->progressOf($this->checklist, $this->team, $this->form);

        $this->assertTrue($row->locked);
        $this->assertSame('a passed '.DefenseOutcome::PRE_PROPOSAL, $row->waits_for);
    }

    public function test_the_capsa_cannot_check_it_before_the_pass(): void
    {
        $this->upload($this->student, $this->form);

        $this->putJson($this->checkUrl($this->form), ['status' => DeliverableCheck::COMPLETE])
            ->assertStatus(422)
            ->assertJsonPath('message', "Signed Final Project Title Form can't be checked yet: it waits for a passed Pre-proposal Defense.");

        $this->assertSame(0, DeliverableCheck::count());
    }

    public function test_a_check_made_before_the_pass_does_not_count(): void
    {
        $this->upload($this->student, $this->form);
        DeliverableCheck::create(['deliverable_id' => $this->form->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);

        $row = $this->progressOf($this->checklist, $this->team, $this->form);

        $this->assertNotContains($row->state, MilestoneProgress::COMPLETE_STATES);
        $this->assertNull($row->file_id);
    }

    public function test_after_the_pass_it_is_handed_in_and_checked_as_usual(): void
    {
        $this->passPreProposal();
        $this->upload($this->student, $this->form);

        $this->assertFalse($this->progressOf($this->checklist, $this->team, $this->form)->locked);

        $this->putJson($this->checkUrl($this->form), ['status' => DeliverableCheck::COMPLETE])
            ->assertOk()
            ->assertJsonPath('state', MilestoneProgress::DONE);
    }

    public function test_any_locked_line_cannot_be_checked(): void
    {
        $notice = $this->addDeliverable($this->checklist, ['title' => 'Signed (Noted) TD RSC']);
        $this->upload($this->student, $notice);

        $this->putJson($this->checkUrl($notice), ['status' => DeliverableCheck::COMPLETE])->assertStatus(422);
    }

    /** Bulbasaur's form, checked before its pass, is cleared; a team that passed keeps its own. */
    public function test_the_migration_clears_forms_closed_before_the_pass(): void
    {
        $fileId = $this->upload($this->student, $this->form);
        DeliverableCheck::create(['deliverable_id' => $this->form->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);

        [$passed, $passedStudent] = $this->teamInSection('Squirtle');
        $this->passPreProposal($passed);
        $keptFile = $this->upload($passedStudent, $this->form);
        $kept = DeliverableCheck::create(['deliverable_id' => $this->form->id, 'team_id' => $passed->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);

        (require base_path('database/migrations/2026_09_24_084440_clear_final_title_forms_closed_before_the_pre_proposal_pass.php'))->up();

        $this->assertFalse(DeliverableCheck::where('team_id', $this->team->team_id)->exists());
        $this->assertNull(DB::table('capstone_documents')->where('file_id', $fileId)->value('deliverable_id'));
        $this->assertModelExists($kept);
        $this->assertSame($this->form->id, (int) DB::table('capstone_documents')->where('file_id', $keptFile)->value('deliverable_id'));
    }
}
