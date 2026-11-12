<?php

namespace Tests\Feature;

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
 * What a team may hand in when: a ticked milestone waits only for the
 * defense above it to be passed, the chapters never wait for each other, and
 * inside a milestone a CLF waits for its draft and a defense for the papers
 * before it. An unopened milestone waits for the CaPSA/TSA.
 */
class MilestoneOrderTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_chapters_open_together_and_items_keep_their_order(): void
    {
        [$team, $student] = $this->teamInSection('Order Team');
        $checklist = Checklist::create(['capsa_id' => $this->capsaFor()->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1 Checklist']);

        $add = function (string $name, array $items) use ($checklist) {
            $milestone = $checklist->milestones()->create(['name' => $name, 'position' => $checklist->milestones()->count(), 'opened_at' => now()]);

            return collect($items)->values()->map(fn ($item, $at) => $milestone->deliverables()->create($item + ['position' => $at]))->all();
        };

        [$oral, $titleForm] = $add('Pre-proposal', [
            ['title' => 'Pre-Proposal Oral Presentation', 'kind' => Deliverable::DEFENSE, 'defense_type' => DefenseOutcome::PRE_PROPOSAL],
            ['title' => 'Signed Final Project Title Form', 'kind' => Deliverable::FORM],
        ]);
        [$draft1, $clf1] = $add('Chapter 1', [
            ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '1'],
            ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser', 'chapter' => '1'],
        ]);
        [$draft2] = $add('Chapter 2', [
            ['title' => 'Chapter 2 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '2'],
        ]);
        [$recommend, $notice, $defense, $manuscript] = $add('Title Defense', [
            ['title' => 'Signed CLF Recommending for Title Defense', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser'],
            ['title' => 'Signed Title Defense Hearing Notice', 'kind' => Deliverable::FORM],
            ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Title Defense'],
            ['title' => 'Revised Proposal Manuscript', 'kind' => Deliverable::DOCUMENT, 'chapter' => 'revised'],
        ]);

        $waits = fn (Deliverable $d) => MilestoneProgress::forTeam($checklist, $team->team_id)->items->firstWhere('item.id', $d->id)->waits_for;

        // Before the pre-proposal defense: its form waits for it; the
        // chapters and Title Defense are open anyway.
        $this->assertNull($waits($oral));
        $this->assertSame('Pre-Proposal Oral Presentation', $waits($titleForm));
        $this->assertNull($waits($draft1));
        $this->assertNull($waits($draft2), 'Chapter 2 opens even with Chapter 1 unfinished');
        $this->assertSame('Chapter 1 Draft', $waits($clf1));
        $this->assertNull($waits($recommend), 'Title Defense does not wait for a passed Pre-proposal Defense');
        $this->assertNull($waits($notice));

        // Inside it, the defense waits for the papers above it, the manuscript for the defense.
        $this->assertSame('Signed CLF Recommending for Title Defense and Signed Title Defense Hearing Notice', $waits($defense));
        $this->assertSame('Title Defense', $waits($manuscript));

        $this->check($team, $recommend);
        $this->check($team, $notice);
        $this->assertNull($waits($defense));
    }

    public function test_a_milestone_the_capsa_has_not_opened_waits_for_her(): void
    {
        [$team] = $this->teamInSection('Waiting Team');
        $checklist = Checklist::create(['capsa_id' => $this->capsaFor()->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1 Checklist']);
        $milestone = $checklist->milestones()->create(['name' => 'Chapter 1', 'position' => 0]);
        $draft = $milestone->deliverables()->create(['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '1', 'position' => 0]);

        $progress = MilestoneProgress::forTeam($checklist, $team->team_id);
        $this->assertSame('your CaPSA/TSA to open it', $progress->items->firstWhere('item.id', $draft->id)->waits_for);

        $milestone->update(['opened_at' => now()]);
        $this->assertNull(MilestoneProgress::forTeam($checklist, $team->team_id)->items->firstWhere('item.id', $draft->id)->waits_for);
    }

    private function check(Team $team, Deliverable $deliverable): void
    {
        DeliverableCheck::create(['deliverable_id' => $deliverable->id, 'team_id' => $team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);
    }
}
