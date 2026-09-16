<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The Edit milestones window's endpoints: a CaPSA's checklists, reordered by
 * drag or arrow keys, edited in place and in bulk, archived and restored,
 * removed with Undo until they are purged.
 */
class ChecklistEditorTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor(['BSIT-3A', 'BSIS-3A']);
        $this->actingAs($this->capsa, 'expert');
    }

    public function test_it_lists_her_checklists_and_the_programs_she_has_none_for(): void
    {
        $this->departmentChecklist($this->capsa, 'BSIT');
        $this->teamInSection('CapsTrack');
        $other = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIS-3B'], 'first_name' => 'Rina', 'last_name' => 'Castro']);
        $this->departmentChecklist($other, 'BSIS');

        $this->getJson(route('capsa.checklists.index'))
            ->assertOk()
            ->assertJsonPath('checklists.0.program', 'BSIT')
            ->assertJsonPath('checklists.0.used_by', '1 team in BSIT-3A')
            ->assertJsonPath('missing.0.program', 'BSIS')
            ->assertJsonCount(1, 'missing.0.copies');
    }

    public function test_a_checklist_starts_from_the_department_one_once_per_program(): void
    {
        $this->postJson(route('capsa.checklists.store'), ['program' => 'BSIT'])
            ->assertCreated()
            ->assertJsonCount(6, 'milestones');

        $this->postJson(route('capsa.checklists.store'), ['program' => 'BSIT'])->assertStatus(422);
        $this->postJson(route('capsa.checklists.store'), ['program' => 'BLIS'])->assertStatus(422);
    }

    public function test_a_checklist_can_be_copied_from_another_capsas(): void
    {
        $other = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIS-3B']]);
        $source = $this->emptyChecklist($other, 'BSIS', 'Requirements');
        $this->addDeliverable($source, ['title' => 'Business Process Analysis', 'kind' => Deliverable::DOCUMENT]);
        $this->addDeliverable($source, ['title' => 'Archived line', 'archived_at' => now()]);

        $this->postJson(route('capsa.checklists.store'), ['program' => 'BSIS', 'copy_of' => $source->id])
            ->assertCreated()
            ->assertJsonPath('milestones.0.name', 'Requirements')
            ->assertJsonCount(1, 'milestones.0.deliverables');

        $this->assertSame(2, Checklist::count());
    }

    public function test_another_capsas_checklist_is_not_hers_to_open_or_change(): void
    {
        $theirs = $this->departmentChecklist(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3B']]));

        $this->getJson(route('capsa.checklists.show', $theirs))->assertForbidden();
        $this->postJson(route('capsa.checklists.milestones.store', $theirs), ['name' => 'Mine now'])->assertForbidden();
        $this->deleteJson(route('capsa.deliverables.destroy_many'), ['ids' => [$theirs->deliverables()->first()->id]])->assertForbidden();
        $this->assertSame(19, $theirs->deliverables()->count());
    }

    public function test_a_drag_saves_the_whole_order_and_a_stale_one_is_refused(): void
    {
        $checklist = $this->departmentChecklist($this->capsa);
        $order = $checklist->milestones->map(fn (ChecklistMilestone $m) => ['id' => $m->id, 'deliverables' => $m->deliverables->pluck('id')->all()])->all();

        // Title Defense to the top, and the Team Assignment Form into it.
        $moved = array_shift($order[0]['deliverables']);
        $last = array_pop($order);
        $last['deliverables'][] = $moved;
        array_unshift($order, $last);

        $this->putJson(route('capsa.checklists.order.update', $checklist), ['milestones' => $order])
            ->assertOk()
            ->assertJsonPath('milestones.0.name', 'Title Defense');

        $form = Deliverable::find($moved);
        $this->assertSame('Title Defense', $form->milestone->name);

        // Leaving a deliverable out is refused, so a stale window cannot drop a line.
        array_pop($order[0]['deliverables']);
        $this->putJson(route('capsa.checklists.order.update', $checklist), ['milestones' => $order])->assertStatus(422);
    }

    public function test_milestones_and_deliverables_are_added_many_at_once(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);

        $milestone = $this->postJson(route('capsa.checklists.milestones.store', $checklist), ['name' => 'Chapter 4'])
            ->assertCreated()
            ->json();

        $this->postJson(route('capsa.checklist_milestones.deliverables.store', $milestone['id']), [
            'titles' => ['Chapter 4 Draft', 'Signed CLF Approving Chapter 4'],
            'kind' => Deliverable::APPROVAL,
        ])->assertStatus(422);

        $this->postJson(route('capsa.checklist_milestones.deliverables.store', $milestone['id']), [
            'titles' => ['Chapter 4 Draft', 'Signed CLF Approving Chapter 4'],
            'kind' => Deliverable::APPROVAL,
            'role' => 'consultant',
            'due_date' => '2026-12-01',
        ])->assertCreated()->assertJsonCount(2, 'deliverables')->assertJsonPath('deliverables.1.role', 'consultant');
    }

    public function test_the_bulk_bar_changes_several_and_undo_puts_each_back(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $a = $this->addDeliverable($checklist, ['title' => 'A', 'due_date' => '2026-10-01']);
        $b = $this->addDeliverable($checklist, ['title' => 'B', 'due_date' => '2026-10-05', 'kind' => Deliverable::DOCUMENT]);
        $other = $checklist->milestones()->create(['name' => 'Later', 'position' => 1]);

        $this->patchJson(route('capsa.deliverables.update_many'), ['ids' => [$a->id, $b->id], 'change' => ['shift_days' => 7]])->assertOk();
        $this->assertSame(['2026-10-08', '2026-10-12'], [$a->fresh()->due_date->toDateString(), $b->fresh()->due_date->toDateString()]);

        $this->patchJson(route('capsa.deliverables.update_many'), ['ids' => [$a->id, $b->id], 'change' => ['kind' => Deliverable::APPROVAL]])->assertOk();
        $this->assertSame('adviser', $b->fresh()->role, 'a CLF always says who signs it');

        $this->patchJson(route('capsa.deliverables.update_many'), ['ids' => [$a->id], 'change' => ['milestone_id' => $other->id]])->assertOk();
        $this->assertSame($other->id, $a->fresh()->checklist_milestone_id);

        $this->patchJson(route('capsa.deliverables.update_many'), ['items' => [
            ['id' => $a->id, 'kind' => Deliverable::FORM, 'role' => null, 'due_date' => '2026-10-01', 'checklist_milestone_id' => $checklist->milestones()->first()->id],
            ['id' => $b->id, 'kind' => Deliverable::DOCUMENT, 'role' => null, 'due_date' => '2026-10-05'],
        ]])->assertOk();

        $this->assertSame([Deliverable::FORM, '2026-10-01', 'Capstone 1'], [$a->fresh()->kind, $a->fresh()->due_date->toDateString(), $a->fresh()->milestone->name]);
        $this->assertSame([Deliverable::DOCUMENT, null], [$b->fresh()->kind, $b->fresh()->role]);
    }

    /** A half-typed year like 0200 is still a date, so it is the range that refuses it. */
    public function test_a_due_date_outside_2000_to_2100_is_refused(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $form = $this->addDeliverable($checklist, ['title' => 'Signed form', 'due_date' => '2026-09-13']);

        $this->patchJson(route('capsa.deliverables.update', $form), ['due_date' => '0200-09-13'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date' => 'from the year 2000']);
        $this->patchJson(route('capsa.deliverables.update_many'), ['ids' => [$form->id], 'change' => ['due_date' => '2200-01-01']])
            ->assertStatus(422);
        $this->postJson(route('capsa.checklist_milestones.deliverables.store', $checklist->milestones()->first()), [
            'titles' => ['Another'], 'kind' => Deliverable::FORM, 'due_date' => '0026-01-01',
        ])->assertStatus(422);

        $this->assertSame('2026-09-13', $form->fresh()->due_date->toDateString());
    }

    public function test_archiving_hides_from_teams_until_restored(): void
    {
        [$team] = $this->teamInSection('CapsTrack');
        $checklist = $this->emptyChecklist($this->capsa);
        $form = $this->addDeliverable($checklist, ['title' => 'Signed form']);
        $milestone = $checklist->milestones()->first();

        $this->patchJson(route('capsa.deliverables.update', $form), ['archived' => true])->assertOk()->assertJsonPath('archived', true);
        $this->assertSame(0, MilestoneProgress::forTeam($checklist, $team->team_id)->total);

        $this->patchJson(route('capsa.deliverables.update', $form), ['archived' => false])->assertOk();
        $this->patchJson(route('capsa.checklist_milestones.update', $milestone), ['archived' => true, 'name' => 'Renamed'])->assertOk()->assertJsonPath('name', 'Renamed');
        $this->assertSame(0, MilestoneProgress::forTeam($checklist, $team->team_id)->total);

        $this->patchJson(route('capsa.checklist_milestones.update', $milestone), ['archived' => false])->assertOk();
        $this->assertSame(1, MilestoneProgress::forTeam($checklist, $team->team_id)->total);
    }

    public function test_a_removed_milestone_and_deliverables_come_back_with_undo(): void
    {
        $checklist = $this->departmentChecklist($this->capsa);
        $milestone = $checklist->milestones()->where('name', 'Chapter 1')->first();
        $form = $this->line($checklist, 'td_rsc');

        $this->deleteJson(route('capsa.checklist_milestones.destroy', $milestone))->assertOk();
        $this->deleteJson(route('capsa.deliverables.destroy_many'), ['ids' => [$form->id]])->assertOk();
        $this->getJson(route('capsa.checklists.show', $checklist))->assertJsonCount(5, 'milestones');

        $this->putJson(route('capsa.removals.update'), ['milestones' => [$milestone->id], 'deliverables' => [$form->id]])->assertOk();

        $this->assertSame(19, $checklist->deliverables()->count());
    }

    public function test_removed_things_are_purged_for_good_after_five_days(): void
    {
        [$team] = $this->teamInSection('CapsTrack');
        $checklist = $this->emptyChecklist($this->capsa);
        $old = $this->addDeliverable($checklist, ['title' => 'Removed a week ago']);
        $recent = $this->addDeliverable($checklist, ['title' => 'Removed yesterday']);
        DeliverableCheck::create(['deliverable_id' => $old->id, 'team_id' => $team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);

        $this->travelTo(now()->subWeek(), fn () => $old->delete());
        $this->travelTo(now()->subDay(), fn () => $recent->delete());

        $this->artisan('checklists:purge-removed')->assertSuccessful();

        $this->assertNull(Deliverable::withTrashed()->find($old->id));
        $this->assertNotNull(Deliverable::withTrashed()->find($recent->id));
        $this->assertSame(0, DeliverableCheck::count());
    }
}
