<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Milestone;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Moving every team's own checklist copy onto its CaPSA's one checklist,
 * without losing a tick or an upload's link, and without doing it twice.
 */
class ChecklistConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]);
    }

    /** @return array{0: Team, 1: Student} */
    private function team(string $name, string $course = 'Bachelor of Science in Information Technology', string $yearSection = '3rd · A'): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => strtolower($name).'@my.cspc.edu.ph',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team = Team::create(['team_name' => $name, 'team_status' => 'Active', 'leader_id' => $leader->student_id, 'is_confirmed' => true]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return [$team, $leader];
    }

    private function copyFor(Team $team): Milestone
    {
        $copy = Milestone::create(['team_id' => $team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
        MilestoneTemplate::applyTo($copy, MilestoneTemplate::CAPSTONE_1);

        return $copy->load('items');
    }

    private function tick(Milestone $copy, string $templateKey, string $on): void
    {
        $copy->items->firstWhere('template_key', $templateKey)->update(['completed_at' => $on]);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        [$team] = $this->team('CapsTrack');
        $this->tick($this->copyFor($team), 'team_assignment_form', '2026-08-28');

        $this->artisan('checklists:consolidate')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, Checklist::count());
        $this->assertSame(0, DeliverableCheck::count());
    }

    public function test_apply_builds_one_checklist_and_keeps_every_teams_ticks_and_uploads(): void
    {
        [$capstrack, $leader] = $this->team('CapsTrack');
        [$sitesense] = $this->team('SiteSense');
        $copyA = $this->copyFor($capstrack);
        $copyB = $this->copyFor($sitesense);
        $this->tick($copyA, 'team_assignment_form', '2026-08-28');
        $this->tick($copyB, 'td_hearing_notice', '2026-11-17');

        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $leader->student_id, 'file_name' => 'Chapter1', 'file_path' => 'submissions/ch1.pdf', 'file_version' => 1,
            'milestone_item_id' => $copyA->items->firstWhere('template_key', 'chapter_1_draft')->id,
            'document_status' => 'Pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('checklists:consolidate', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, Checklist::count());
        $checklist = Checklist::first();
        $this->assertSame($this->capsa->expert_id, (int) $checklist->capsa_id);
        $this->assertSame('BSIT', $checklist->program);
        $this->assertSame(
            ['Pre-proposal Defense', 'Title Defense'],
            $checklist->milestones->pluck('name')->all(),
        );
        $this->assertSame(19, $checklist->deliverables()->count());

        $check = DeliverableCheck::where('team_id', $capstrack->team_id)->sole();
        $this->assertSame('team_assignment_form', $check->deliverable->template_key);
        $this->assertSame(DeliverableCheck::COMPLETE, $check->status);
        $this->assertSame('2026-08-28', $check->checked_at->toDateString());
        $this->assertSame('td_hearing_notice', DeliverableCheck::where('team_id', $sitesense->team_id)->sole()->deliverable->template_key);

        $linked = DB::table('capstone_documents')->where('file_id', $fileId)->value('deliverable_id');
        $this->assertSame('chapter_1_draft', Deliverable::find($linked)->template_key);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        [$team] = $this->team('CapsTrack');
        $this->tick($this->copyFor($team), 'team_assignment_form', '2026-08-28');

        $this->artisan('checklists:consolidate', ['--apply' => true])->assertSuccessful();
        $this->artisan('checklists:consolidate', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, Checklist::count());
        $this->assertSame(2, ChecklistMilestone::count());
        $this->assertSame(19, Deliverable::count());
        $this->assertSame(1, DeliverableCheck::count());
    }

    public function test_a_team_no_capsa_covers_is_reported_and_left_alone(): void
    {
        [$team] = $this->team('MediTrack', 'Bachelor of Science in Information Systems', '4th · B');
        $this->tick($this->copyFor($team), 'team_assignment_form', '2026-08-28');

        $this->artisan('checklists:consolidate', ['--apply' => true])
            ->expectsOutputToContain('no CaPSA covers section BSIS-4B')
            ->assertSuccessful();

        $this->assertSame(0, Checklist::count());
    }

    public function test_a_new_checklist_can_start_from_the_department_one(): void
    {
        $checklist = MilestoneTemplate::createChecklist($this->capsa->expert_id, 'BSIT');

        $this->assertSame('Capstone 1 Checklist', $checklist->title);
        // Capstone 1's two milestones, and Capstone 2's four after them, the last the Archive Entry.
        $this->assertSame(6, $checklist->milestones()->count());
        $this->assertSame(48, $checklist->deliverables()->count());
        $this->assertSame('team_assignment_form', $checklist->milestones->first()->deliverables->first()->template_key);
    }

    public function test_archived_and_removed_rows_are_not_active(): void
    {
        $checklist = MilestoneTemplate::createChecklist($this->capsa->expert_id, 'BSIT');
        [$preProposal, $titleDefense, $preOral] = $checklist->milestones->all();

        $preProposal->update(['archived_at' => now()]);             // hides its 7 deliverables too
        $titleDefense->deliverables->first()->delete();             // removed
        $preOral->deliverables->first()->update(['archived_at' => now()]);

        $this->assertSame(5, ChecklistMilestone::active()->count());
        $this->assertSame(48 - 7 - 1 - 1, Deliverable::active()->count());
    }
}
