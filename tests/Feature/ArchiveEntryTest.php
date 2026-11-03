<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Student;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\MilestoneProgress;
use App\Support\MilestoneTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The Archive Entry: its own milestone after the Final Oral Defense. The
 * team sends a title, a typed abstract and its proponents - no manuscript -
 * and the line closes once the CaPSA publishes it in the Archive.
 */
class ArchiveEntryTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Team $team;

    private Student $leader;

    private Checklist $checklist;

    private Deliverable $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor();
        [$this->team, $this->leader] = $this->teamInSection('Bulbasaur');
        $this->checklist = $this->departmentChecklist($this->capsa);
        $this->entry = $this->line($this->checklist, Deliverable::ARCHIVE_ENTRY);

        DefenseSchedule::create([
            'team_id' => $this->team->team_id, 'defense_type' => 'Final Oral Defense', 'attempt' => 1,
            'schedule_date' => now(), 'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now(),
        ]);
    }

    private function state(): object
    {
        return $this->progressOf($this->checklist, $this->team, $this->entry);
    }

    private function send()
    {
        return $this->actingAs($this->leader, 'web')->post(route('student.archive.manuscript.submit'), [
            'title' => 'A Tracker for Capstone Teams',
            'abstract' => 'We built a tracker. It tracks.',
        ]);
    }

    public function test_it_is_its_own_milestone_at_the_end(): void
    {
        $milestone = $this->entry->milestone;

        $this->assertSame('Archive Entry', $milestone->name);
        $this->assertSame(1, $milestone->deliverables()->count());
        $this->assertSame($milestone->id, $this->checklist->milestones()->reorder('position', 'desc')->value('id'));
    }

    public function test_an_older_checklist_gets_it_once(): void
    {
        $milestone = $this->entry->milestone;
        $this->entry->forceDelete();
        $milestone->forceDelete();

        $this->assertSame(1, MilestoneTemplate::appendArchiveEntry($this->checklist));
        $this->assertSame(0, MilestoneTemplate::appendArchiveEntry($this->checklist));
        $this->assertSame(1, $this->checklist->deliverables()->where('deliverables.template_key', Deliverable::ARCHIVE_ENTRY)->count());

        // No Capstone 2 yet: nothing to put it after.
        $this->assertSame(0, MilestoneTemplate::appendArchiveEntry($this->emptyChecklist($this->capsa)));
    }

    public function test_it_waits_on_the_capsa_once_sent_and_closes_when_published(): void
    {
        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state()->state);

        $this->send()->assertSessionHas('success');
        $row = $this->state();
        $this->assertSame(MilestoneProgress::HANDED_IN, $row->state);
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $row->waiting_on);

        ArchivedDocument::where('team_id', $this->team->team_id)->update(['submission_status' => ArchivedDocument::PUBLISHED]);
        $this->assertSame(MilestoneProgress::DONE, $this->state()->state);
    }

    public function test_the_capsas_check_also_closes_it(): void
    {
        // She checks only an open line; lift the earlier milestones' lock.
        $this->checklist->milestones()->update(['requires_previous' => false]);

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('capsa.deliverables.checks.update', [$this->entry->id, $this->team->team_id]), ['status' => DeliverableCheck::COMPLETE])
            ->assertOk();

        $this->assertSame(MilestoneProgress::DONE, $this->state()->state);
    }

    public function test_the_form_has_a_typed_abstract_and_read_only_proponents(): void
    {
        $this->actingAs($this->leader, 'web')->get(route('student.archive'))
            ->assertOk()
            ->assertSee('id="ms-proponents"', false)
            ->assertSee('Type your abstract...')
            ->assertSee('Send Archive Entry');
    }

    public function test_a_closed_milestone_refuses_the_entry_and_hides_the_form(): void
    {
        $this->entry->milestone->update(['closed_at' => now()]);

        $this->actingAs($this->leader, 'web')->get(route('student.archive'))
            ->assertOk()
            ->assertSee('Your CaPSA has closed the Archive Entry.')
            ->assertDontSee('id="ms-proponents"', false);

        $this->send()->assertSessionHas('error', 'Your CaPSA has closed the Archive Entry.');
        $this->assertSame(0, ArchivedDocument::count());
    }

    public function test_the_tracker_shows_the_description_and_how_it_closes(): void
    {
        $this->entry->milestone->update(['description' => 'Send your title, abstract and proponents.']);

        $this->actingAs($this->leader, 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Send your title, abstract and proponents.')
            ->assertSee('Closes when your CaPSA publishes it in the Archive')
            ->assertSee(route('student.archive'), false);
    }
}
