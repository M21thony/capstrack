<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Student;
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

    private Student $leader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor();
        [$this->team, $this->leader] = $this->teamInSection('Team Done');
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
            ->assertSee('Upload file')->assertSee('1<span class="ms-mut"> of 2 done', false)
            ->assertSee('Old draft')
            ->assertSee('Hidden from teams')
            ->assertSee(route('capsa.team_management.milestones.requirements.create', $this->milestone), false)
            ->assertSee(route('capsa.team_management.milestones.requirements.edit', $this->form), false);
    }

    public function test_a_locked_milestone_names_the_defense_it_waits_for_not_the_one_above(): void
    {
        $defense = $this->checklist->milestones()->create(['name' => 'Final Oral Defense', 'position' => 1, 'opened_at' => now()]);
        $defense->deliverables()->create(['title' => 'Final Oral Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Final Oral Defense', 'position' => 0]);
        $this->checklist->milestones()->create(['name' => 'Final Manuscript', 'position' => 2, 'requires_previous' => true]);
        $archive = $this->checklist->milestones()->create(['name' => 'Archive Entry', 'position' => 3, 'requires_previous' => true]);

        $this->get(route('capsa.team_management.milestones.show', $archive))
            ->assertOk()
            ->assertSee('Opens after Final Oral Defense')
            ->assertDontSee('Opens after Final Manuscript');
    }

    public function test_the_forms_open_for_new_and_existing_milestones_and_requirements(): void
    {
        $this->addDeliverable($this->checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Title Defense']);

        $this->get(route('capsa.team_management.milestones.create', $this->checklist))
            ->assertOk()
            ->assertSee('New milestone')
            ->assertSee('Teams can start this only after they pass the defense in Chapter 1-3 Submission')
            ->assertSee('Description')
            ->assertSee('Place in the list')
            ->assertSee('value="2 of 2"', false);

        // Named once, in the top bar: the milestone, then Edit.
        $this->get(route('capsa.team_management.milestones.edit', $this->milestone))
            ->assertOk()
            ->assertSeeInOrder(['tm-topcrumb', 'Chapter 1-3 Submission', 'Edit'], false)
            ->assertDontSee('<h1 class="ml-title">', false)
            ->assertSee('value="Chapter 1-3 Submission"', false)
            ->assertSee('Hide from teams')
            ->assertSee('value="1 of 1"', false)
            ->assertSee('Delete milestone')
            ->assertDontSee('Teams can start this only after');

        $this->get(route('capsa.team_management.milestones.requirements.create', $this->milestone))
            ->assertOk()
            ->assertSee('Add requirement')
            ->assertSee('Instructions')
            ->assertSee('Upload file')
            ->assertSee('Which defense')
            ->assertSee('In <b>Chapter 1-3 Submission</b>', false);

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

    public function test_setup_offers_the_department_milestones_or_none_but_never_another_capsas(): void
    {
        $this->checklist->delete();
        $this->emptyChecklist($this->capsaFor(['BSIT-3B']));

        $this->get(route('capsa.team_management.milestones.setup', 'bsit'))
            ->assertOk()
            ->assertSee('Set up milestones')
            ->assertSee("Use the department's milestones", false)
            ->assertDontSee("Copy another CaPSA's milestones", false)
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
            'description' => '  Bring the revised manuscript.  ',
        ])->assertCreated();

        $made = ChecklistMilestone::findOrFail($response->json('id'));
        $this->assertSame('Pre-Oral Defense', $made->name);
        $this->assertSame('Bring the revised manuscript.', $made->description);
        $this->assertSame('2026-11-30', $made->due_on->toDateString());
        $this->assertTrue((bool) $made->requires_previous);
    }

    public function test_a_new_requirement_keeps_its_instructions_and_which_defense(): void
    {
        $this->postJson(route('capsa.checklist_milestones.deliverables.store', $this->milestone), [
            'titles' => ['Pre Oral'], 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Pre Oral Defense', 'instructions' => "Bring 5 copies.\nArrive early.",
        ])->assertCreated();
        // An upload never carries a defense, whatever is sent with it.
        $this->postJson(route('capsa.checklist_milestones.deliverables.store', $this->milestone), [
            'titles' => ['Endorsement'], 'kind' => Deliverable::FORM, 'defense_type' => 'Title Defense',
        ])->assertCreated();

        $defense = Deliverable::where('title', 'Pre Oral')->sole();
        $this->assertSame('Pre Oral Defense', $defense->defense_type);
        $this->assertSame("Bring 5 copies.\nArrive early.", $defense->instructions);
        $this->assertNull(Deliverable::where('title', 'Endorsement')->sole()->defense_type);

        $this->postJson(route('capsa.checklist_milestones.deliverables.store', $this->milestone), [
            'titles' => ['Bad'], 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Midterm Defense',
        ])->assertUnprocessable();
    }

    public function test_editing_an_upload_without_a_type_keeps_how_it_is_checked(): void
    {
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT]);

        $this->patchJson(route('capsa.deliverables.update', $draft), [
            'title' => 'Chapter 1', 'instructions' => 'Use the template.', 'due_date' => '2026-12-01',
        ])->assertOk();

        $draft->refresh();
        $this->assertSame(Deliverable::DOCUMENT, $draft->kind);
        $this->assertSame('Chapter 1', $draft->title);
        $this->assertSame('Use the template.', $draft->instructions);

        $this->patchJson(route('capsa.deliverables.update', $draft), ['instructions' => null])->assertOk();
        $this->assertNull($draft->fresh()->instructions);
    }

    public function test_a_defense_requirement_shows_and_opens_as_its_defense(): void
    {
        $defense = $this->addDeliverable($this->checklist, ['title' => 'Oral', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Pre Oral Defense']);

        $this->get(route('capsa.team_management.milestones.show', $this->milestone))
            ->assertSee('Pre Oral Defense')->assertSee('0<span class="ms-mut"> of 2 done', false);

        $this->get(route('capsa.team_management.milestones.requirements.edit', $defense))
            ->assertOk()
            ->assertSee('value="defense" checked', false)
            ->assertSee('<option selected>Pre Oral Defense</option>', false)
            ->assertDontSee('hidden >Which defense', false);

        $this->get(route('capsa.team_management.milestones.requirements.edit', $this->form))
            ->assertSee('value="upload" checked', false)
            ->assertSee('hidden >Which defense', false);
    }

    public function test_students_see_a_requirements_instructions(): void
    {
        $this->form->update(['instructions' => 'Signed by the adviser and the dean.']);
        $open = $this->addDeliverable($this->checklist, ['title' => 'Approval sheet', 'kind' => Deliverable::FORM, 'instructions' => 'Signed by the adviser and the dean.']);

        $this->actingAs($this->leader)->get('/student/progress')
            ->assertOk()
            ->assertSee('Signed by the adviser and the dean.');

        $this->actingAs($this->leader)->getJson(route('student.submission.plan', ['item' => $open->id]))
            ->assertOk()
            ->assertJsonPath('item.instructions', 'Signed by the adviser and the dean.');
    }
}
