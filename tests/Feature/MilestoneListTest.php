<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/** Manage milestones: every milestone in order, with its due date, requirements and availability. */
class MilestoneListTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Checklist $checklist;

    private Team $done;

    private Team $behind;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor();
        [$this->done] = $this->teamInSection('Team Done');
        [$this->behind] = $this->teamInSection('Team Behind');

        $this->checklist = $this->emptyChecklist($this->capsa, milestone: 'Chapter 1-3 Submission');
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed form', 'kind' => Deliverable::FORM]);
        $this->checklist->milestones()->first()->update([
            'description' => 'Title defense forms and the first three chapters.',
            'due_on' => now()->subDays(3)->toDateString(),
        ]);
        DeliverableCheck::create(['deliverable_id' => $form->id, 'team_id' => $this->done->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()->subDays(5)]);

        $later = $this->checklist->milestones()->create(['name' => 'Pre-Oral Defense Requirements', 'position' => 1, 'due_on' => now()->addDays(28)->toDateString()]);
        $later->deliverables()->create(['title' => 'Revised manuscript', 'kind' => Deliverable::FORM, 'position' => 0]);

        $this->actingAs($this->capsa, 'expert');
    }

    private function page(array $query = [])
    {
        return $this->get(route('capsa.team_management', ['tab' => 'milestone_list'] + $query));
    }

    /** Configuration only: due date, requirement count and availability, no team progress. */
    public function test_every_milestone_shows_its_setup_but_no_team_progress(): void
    {
        $milestone = $this->checklist->milestones()->where('name', 'Chapter 1-3 Submission')->first();

        $this->page()
            ->assertOk()
            ->assertSee('class="hub-tabs"', false)
            ->assertSee('Manage milestones')
            ->assertSeeInOrder(['Chapter 1-3 Submission', 'Pre-Oral Defense Requirements'])
            ->assertDontSee('Title defense forms and the first three chapters.')
            ->assertSee(now()->subDays(3)->format('M j, Y'))
            ->assertSee(now()->addDays(28)->format('M j, Y'))
            ->assertSee('<span class="mlx-pill open">Open</span>', false)
            ->assertSee(route('capsa.team_management.milestones.edit', $milestone), false)
            ->assertSee(route('capsa.team_management.milestones.show', $milestone), false)
            ->assertDontSee('past due')
            ->assertDontSee('width:50%', false);
    }

    public function test_finished_and_hidden_milestones_stay_in_the_list_with_their_availability(): void
    {
        $this->checklist->milestones()->where('name', 'Chapter 1-3 Submission')->update(['closed_at' => now()]);
        $this->checklist->milestones()->where('name', 'Pre-Oral Defense Requirements')->update(['archived_at' => now()]);

        $this->page()
            ->assertOk()
            ->assertSeeInOrder(['Chapter 1-3 Submission', 'Finished', 'Pre-Oral Defense Requirements', 'Hidden'], false);
    }

    public function test_a_capsa_with_no_checklist_is_offered_the_setup(): void
    {
        $this->checklist->delete();

        $this->page()
            ->assertOk()
            ->assertSee('BSIT-3A has no milestones yet.')
            ->assertSee(route('capsa.team_management.milestones.setup', 'BSIT'), false);
    }

    public function test_the_team_list_links_to_it(): void
    {
        $this->get(route('capsa.team_management'))
            ->assertOk()
            ->assertSee(route('capsa.team_management', ['tab' => 'milestone_list']), false);
    }
}
