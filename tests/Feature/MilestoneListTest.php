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

/** Manage milestones: every milestone, open or finished, with how many teams are done with each. */
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

    /** No description on screen: it is not part of the milestone the system documents. */
    public function test_open_milestones_show_due_date_and_how_many_teams_finished_but_no_description(): void
    {
        $this->page()
            ->assertOk()
            ->assertSee('class="hub-tabs"', false)
            ->assertSee('Milestones')
            ->assertSee('Chapter 1-3 Submission')
            ->assertDontSee('Title defense forms and the first three chapters.')
            ->assertSee('Due '.now()->subDays(3)->format('M j, Y'))
            ->assertSee('Past due for 1 team')
            ->assertSee('1 of 2 teams done')
            ->assertSee('50%')
            ->assertSee('in 28 days');
    }

    public function test_sorting_by_due_date_puts_the_soonest_first_and_by_completeness_the_most_done(): void
    {
        $this->checklist->milestones()->where('name', 'Chapter 1-3 Submission')->update(['due_on' => now()->addDays(40)->toDateString()]);

        $this->page()->assertSeeInOrder(['1. Chapter 1-3 Submission', '2. Pre-Oral Defense Requirements']);
        $this->page(['sort' => 'due'])->assertSeeInOrder(['Pre-Oral Defense Requirements', 'Chapter 1-3 Submission']);
        $this->page(['sort' => 'percent'])->assertSeeInOrder(['Chapter 1-3 Submission', 'Pre-Oral Defense Requirements']);
        $this->page(['sort' => 'name'])->assertSeeInOrder(['Chapter 1-3 Submission', 'Pre-Oral Defense Requirements']);
    }

    public function test_a_finished_milestone_moves_to_the_finished_tab(): void
    {
        $this->checklist->milestones()->where('name', 'Pre-Oral Defense Requirements')->update(['closed_at' => now()]);

        $this->page()
            ->assertOk()
            ->assertSee('Chapter 1-3 Submission')
            ->assertDontSee('Pre-Oral Defense Requirements');

        $this->page(['state' => 'finished'])
            ->assertOk()
            ->assertSee('Pre-Oral Defense Requirements')
            ->assertSee('Finished '.now()->format('M j, Y'))
            ->assertSee('Reopen')
            ->assertDontSee('Chapter 1-3 Submission');
    }

    public function test_a_hidden_milestone_is_with_the_finished_ones_and_can_be_shown_again(): void
    {
        $this->checklist->milestones()->where('name', 'Pre-Oral Defense Requirements')->update(['archived_at' => now()]);

        $this->page()->assertDontSee('Pre-Oral Defense Requirements');
        $this->page(['state' => 'finished'])
            ->assertSee('Pre-Oral Defense Requirements')
            ->assertSee('Hidden from teams')
            ->assertSee('Show to teams again');
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
