<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/** The pages under Manage milestones: one milestone, its forms, and setting up a program. */
class MilestonePagesTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Checklist $checklist;

    private ChecklistMilestone $milestone;

    private Deliverable $form;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor();
        [$this->team] = $this->teamInSection('Team Done');
        $this->teamInSection('Team Behind');

        $this->checklist = $this->emptyChecklist($this->capsa, milestone: 'Chapter 1-3 Submission');
        $this->milestone = $this->checklist->milestones()->first();
        $this->form = $this->addDeliverable($this->checklist, ['title' => 'Signed endorsement form', 'kind' => Deliverable::FORM]);
        $this->addDeliverable($this->checklist, ['title' => 'Old draft', 'kind' => Deliverable::DOCUMENT, 'archived_at' => now()]);
        DeliverableCheck::create(['deliverable_id' => $this->form->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);

        $this->actingAs($this->capsa, 'expert');
    }

    public function test_a_milestone_page_lists_its_requirements_with_how_many_teams_are_done(): void
    {
        $this->get(route('capsa.team_management.milestones.show', $this->milestone))
            ->assertOk()
            ->assertSee('Chapter 1-3 Submission')
            ->assertSee('Manage milestones')
            ->assertSee('Edit milestone')
            ->assertSee('Mark finished')
            ->assertSee('Signed endorsement form')
            ->assertSee('Signed form · 1 of 2 teams done')
            ->assertSee('Old draft')
            ->assertSee('Hidden from teams')
            ->assertSee(route('capsa.team_management.milestones.requirements.create', $this->milestone), false)
            ->assertSee(route('capsa.team_management.milestones.requirements.edit', $this->form), false);
    }

    public function test_the_forms_open_for_new_and_existing_milestones_and_requirements(): void
    {
        $this->get(route('capsa.team_management.milestones.create', $this->checklist))
            ->assertOk()
            ->assertSee('New milestone')
            ->assertSee('Teams can start this only after they finish Chapter 1-3 Submission')
            ->assertSee('It goes at the end of the list.');

        $this->get(route('capsa.team_management.milestones.edit', $this->milestone))
            ->assertOk()
            ->assertSee('Edit milestone')
            ->assertSee('value="Chapter 1-3 Submission"', false)
            ->assertSee('Hide from teams')
            ->assertDontSee('Teams can start this only after');

        $this->get(route('capsa.team_management.milestones.requirements.create', $this->milestone))
            ->assertOk()
            ->assertSee('Add requirement')
            ->assertSee('How teams complete it')
            ->assertSee('In Chapter 1-3 Submission');

        $this->get(route('capsa.team_management.milestones.requirements.edit', $this->form))
            ->assertOk()
            ->assertSee('value="Signed endorsement form"', false)
            ->assertSee('Remove requirement');
    }

    public function test_another_capsas_milestone_pages_are_refused(): void
    {
        $this->actingAs($this->capsaFor(['BSIT-3B']), 'expert');

        $this->get(route('capsa.team_management.milestones.show', $this->milestone))->assertForbidden();
        $this->get(route('capsa.team_management.milestones.edit', $this->milestone))->assertForbidden();
        $this->get(route('capsa.team_management.milestones.create', $this->checklist))->assertForbidden();
        $this->get(route('capsa.team_management.milestones.requirements.create', $this->milestone))->assertForbidden();
        $this->get(route('capsa.team_management.milestones.requirements.edit', $this->form))->assertForbidden();
    }

    public function test_setup_offers_the_department_milestones_and_another_capsas_to_copy(): void
    {
        $this->checklist->delete();
        $this->emptyChecklist($this->capsaFor(['BSIT-3B']));

        $this->get(route('capsa.team_management.milestones.setup', 'bsit'))
            ->assertOk()
            ->assertSee('Set up BSIT milestones')
            ->assertSee("Use the department's milestones", false)
            ->assertSee("Copy another CaPSA's milestones", false)
            ->assertSee('Start empty');
    }

    public function test_setup_goes_back_to_the_list_once_she_has_milestones_and_is_not_found_for_a_program_she_does_not_handle(): void
    {
        $this->get(route('capsa.team_management.milestones.setup', 'BSIT'))
            ->assertRedirect(route('capsa.team_management', ['tab' => 'milestone_list']));

        $this->get(route('capsa.team_management.milestones.setup', 'BSIS'))->assertNotFound();
    }

    public function test_starting_empty_makes_a_checklist_with_no_milestones(): void
    {
        $this->checklist->delete();

        $this->postJson(route('capsa.checklists.store'), ['program' => 'BSIT', 'empty' => true])->assertCreated();

        $made = Checklist::where('capsa_id', $this->capsa->expert_id)->where('program', 'BSIT')->sole();
        $this->assertSame(0, $made->milestones()->count());
    }

    public function test_a_new_milestone_keeps_its_due_date_and_lock(): void
    {
        $response = $this->postJson(route('capsa.checklists.milestones.store', $this->checklist), [
            'name' => 'Pre-Oral Defense',
            'due_on' => '2026-11-30',
            'requires_previous' => true,
        ])->assertCreated();

        $made = ChecklistMilestone::findOrFail($response->json('id'));
        $this->assertSame('Pre-Oral Defense', $made->name);
        $this->assertSame('2026-11-30', $made->due_on->toDateString());
        $this->assertTrue((bool) $made->requires_previous);
    }
}
