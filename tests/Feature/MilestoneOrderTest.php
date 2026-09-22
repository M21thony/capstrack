<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * What a team may hand in when: a ticked milestone waits for everything
 * above it, an unticked one opens with the one above, and inside a milestone
 * a CLF waits for its draft and a defense for the papers before it.
 */
class MilestoneOrderTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_chapters_open_together_and_items_keep_their_order(): void
    {
        [$team, $student] = $this->teamInSection('Order Team');
        $checklist = Checklist::create(['capsa_id' => $this->capsaFor()->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1 Checklist']);

        $add = function (string $name, bool $gate, array $items) use ($checklist) {
            $milestone = $checklist->milestones()->create(['name' => $name, 'position' => $checklist->milestones()->count(), 'requires_previous' => $gate]);

            return collect($items)->values()->map(fn ($item, $at) => $milestone->deliverables()->create($item + ['position' => $at]))->all();
        };

        [$oral, $titleForm] = $add('Pre-proposal', false, [
            ['title' => 'Pre-Proposal Oral Presentation', 'kind' => Deliverable::DEFENSE, 'defense_type' => DefenseOutcome::PRE_PROPOSAL],
            ['title' => 'Signed Final Project Title Form', 'kind' => Deliverable::FORM],
        ]);
        [$draft1, $clf1] = $add('Chapter 1', true, [
            ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '1'],
            ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser', 'chapter' => '1'],
        ]);
        [$draft2] = $add('Chapter 2', false, [
            ['title' => 'Chapter 2 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '2'],
        ]);
        [$recommend, $notice, $defense, $manuscript] = $add('Title Defense', true, [
            ['title' => 'Signed CLF Recommending for Title Defense', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser'],
            ['title' => 'Signed Title Defense Hearing Notice', 'kind' => Deliverable::FORM],
            ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Title Defense'],
            ['title' => 'Revised Proposal Manuscript', 'kind' => Deliverable::DOCUMENT, 'chapter' => 'revised'],
        ]);

        $waits = fn (Deliverable $d) => MilestoneProgress::forTeam($checklist, $team->team_id)->items->firstWhere('item.id', $d->id)->waits_for;

        // Before the pre-proposal defense: its form waits for it, and everything below waits for Pre-proposal.
        $this->assertNull($waits($oral));
        $this->assertSame('Pre-Proposal Oral Presentation', $waits($titleForm));
        $this->assertSame('Pre-proposal', $waits($draft1));
        $this->assertSame('Pre-proposal', $waits($draft2), 'Chapter 2 opens with Chapter 1, so it waits for the same thing');
        $this->assertSame('Pre-proposal, Chapter 1 and Chapter 2', $waits($recommend));

        $this->pass($team, DefenseOutcome::PRE_PROPOSAL);
        $this->check($team, $titleForm);

        // Both chapters open at once; a chapter's CLF waits for its draft.
        $this->assertNull($waits($draft1));
        $this->assertNull($waits($draft2));
        $this->assertSame('Chapter 1 Draft', $waits($clf1));
        $this->assertSame('Chapter 1 and Chapter 2', $waits($recommend));

        // Chapter 1's draft passes: its CLF opens.
        $this->approve($this->upload($student, $draft1, now()->subDays(3)->toDateTimeString()));
        $this->assertNull($waits($clf1));

        // The chapters done: Title Defense opens, the defense waits for the
        // papers above it, the manuscript for the defense.
        $this->approve($this->upload($student, $draft2, now()->subDays(3)->toDateTimeString()));
        $this->check($team, $clf1);

        $this->assertNull($waits($recommend));
        $this->assertNull($waits($notice));
        $this->assertSame('Signed CLF Recommending for Title Defense and Signed Title Defense Hearing Notice', $waits($defense));
        $this->assertSame('Title Defense', $waits($manuscript));

        $this->check($team, $recommend);
        $this->check($team, $notice);
        $this->assertNull($waits($defense));
    }

    /** Both reviewers approve a draft. */
    private function approve(int $fileId): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true])->expert_id;

        foreach (['adviser', 'consultant'] as $role) {
            CapstoneDocumentReview::create([
                'file_id' => $fileId, 'expert_id' => $expert, 'role' => $role,
                'review_status' => CapstoneDocumentReview::REVIEWED,
                'verdict' => CapstoneDocumentReview::APPROVED, 'verdict_at' => now()->subDay(),
            ]);
        }
    }

    private function check(Team $team, Deliverable $deliverable): void
    {
        DeliverableCheck::create(['deliverable_id' => $deliverable->id, 'team_id' => $team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);
    }

    private function pass(Team $team, string $stage): void
    {
        DB::table('defense_schedule')->insert([
            'team_id' => $team->team_id, 'defense_type' => $stage,
            'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now(),
        ]);
    }
}
