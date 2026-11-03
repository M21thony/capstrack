<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Student;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\DefenseProgress;
use App\Support\MilestoneProgress;
use App\Support\MilestoneTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * One checklist from Getting started to Final Oral Defense: Capstone 2
 * carries on below Title Defense, and a team can work it top to bottom
 * without a line ever being stuck.
 */
class ChecklistRunsToFinalOralDefenseTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Team $team;

    private Student $student;

    private Checklist $checklist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor();
        [$this->team, $this->student] = $this->teamInSection('Charmander');
        $this->checklist = $this->departmentChecklist($this->capsa);
    }

    private function progress(): object
    {
        return MilestoneProgress::forTeam($this->checklist, $this->team->team_id);
    }

    /** Close a line the way it closes for real. */
    private function close(Deliverable $item): void
    {
        match ($item->kind) {
            Deliverable::DEFENSE => DefenseSchedule::create([
                'team_id' => $this->team->team_id, 'defense_type' => $item->defense_type, 'attempt' => 1,
                'schedule_date' => now(), 'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now(),
            ]),
            Deliverable::DOCUMENT => $this->approve($this->upload($this->student, $item, chapter: $item->chapter)),
            // A signed paper or a CaPSA's own task: her check closes it, and she may only check an open line.
            default => $this->actingAs($this->capsa, 'expert')
                ->putJson(route('capsa.deliverables.checks.update', [$item->id, $this->team->team_id]), ['status' => DeliverableCheck::COMPLETE])
                ->assertOk(),
        };
    }

    private function approve(int $fileId): void
    {
        foreach (['adviser', 'consultant'] as $role) {
            CapstoneDocumentReview::create([
                'file_id' => $fileId, 'expert_id' => $this->capsa->expert_id, 'role' => $role,
                'review_status' => CapstoneDocumentReview::REVIEWED, 'verdict' => CapstoneDocumentReview::APPROVED, 'verdict_at' => now(),
            ]);
        }
    }

    public function test_the_department_checklist_carries_on_into_capstone_2(): void
    {
        $this->assertSame(
            ['Getting started', 'Pre-proposal', 'Chapter 1', 'Chapter 2', 'Chapter 3', 'Title Defense',
                'System Development', 'Chapter 4', 'Pre Oral Defense', 'Chapter 5', 'Final Oral Defense', 'Final Manuscript', 'Archive Entry'],
            $this->checklist->milestones->pluck('name')->all(),
        );
        $this->assertSame(
            DefenseProgress::STAGES,
            $this->checklist->deliverables()->where('kind', Deliverable::DEFENSE)->orderBy('checklist_milestones.position')->pluck('defense_type')->all(),
        );
    }

    /** System Development has no lock of its own; the Final Oral Defense still waits for the defenses before it. */
    public function test_only_the_final_oral_defense_waits_for_capstone_1(): void
    {
        $progress = $this->progress();
        $systemDevelopment = $progress->milestones->first(fn ($m) => $m->milestone->name === 'System Development');

        $this->assertFalse($systemDevelopment->locked);
        $this->assertTrue($progress->items->first(fn ($r) => $r->item->template_key === 'final_oral_defense')->locked);
        $this->assertNotEmpty($this->team->checklistBlockersFor('Final Oral Defense'));
    }

    /** Work the checklist top to bottom: the next open line is never locked, and every defense is clear when its turn comes. */
    public function test_a_team_works_it_through_to_the_final_oral_defense(): void
    {
        $order = [];

        for ($guard = 0; $guard < 60; $guard++) {
            $next = $this->progress()->items->first(fn ($r) => ! in_array($r->state, MilestoneProgress::COMPLETE_STATES, true));

            if (! $next) {
                break;
            }

            $this->assertFalse($next->locked, $next->item->title.' is locked when its turn comes: waits for '.$next->waits_for);

            if ($next->item->kind === Deliverable::DEFENSE) {
                $this->assertSame([], $this->team->fresh()->checklistBlockersFor($next->item->defense_type), $next->item->title.' still has blockers');
            }

            $this->close($next->item);
            $order[] = $next->milestone->name;

            $closed = $this->progress()->items->first(fn ($r) => $r->item->id === $next->item->id);
            $this->assertContains($closed->state, MilestoneProgress::COMPLETE_STATES, $next->item->title.' did not close');
        }

        $this->assertSame(39, count($order));
        $this->assertSame(
            ['Getting started', 'Pre-proposal', 'Chapter 1', 'Chapter 2', 'Chapter 3', 'Title Defense',
                'System Development', 'Chapter 4', 'Pre Oral Defense', 'Chapter 5', 'Final Oral Defense', 'Final Manuscript', 'Archive Entry'],
            array_values(array_unique($order)),
        );
        $this->assertSame(100, $this->progress()->percent);
    }

    /** Pre Oral Defense cannot be put on the calendar while Chapter 4 is open. */
    public function test_a_defense_waits_for_the_chapter_above_it(): void
    {
        foreach ($this->progress()->items as $row) {
            if ($row->item->template_key === 'chapter_4_draft') {
                break;
            }
            $this->close($row->item);
        }

        $pod = $this->progress()->items->first(fn ($r) => $r->item->template_key === 'pre_oral_defense');
        $this->assertTrue($pod->locked);
        $this->assertContains('Chapter 4 Draft', $this->team->fresh()->checklistBlockersFor('Pre Oral Defense'));
    }

    public function test_an_older_checklist_gets_capstone_2_once(): void
    {
        $old = $this->emptyChecklist(CapstoneExpert::factory()->create(['approved_status' => true]));

        $this->assertSame(7, MilestoneTemplate::appendCapstone2($old));
        $this->assertSame(0, MilestoneTemplate::appendCapstone2($old));
        $this->assertSame(['Capstone 1', 'System Development'], $old->milestones()->orderBy('position')->take(2)->pluck('name')->all());

        // The department checklist already carries it, so the migration adds nothing there.
        (require base_path('database/migrations/2026_09_24_085415_add_capstone_2_to_existing_checklists.php'))->up();
        $this->assertSame(39, $this->checklist->deliverables()->count());
        $this->assertSame(1, $old->deliverables()->where('deliverables.template_key', 'final_oral_defense')->count());
    }
}
