<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\MilestoneProgress;
use App\Support\MilestoneTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A milestone's progress is worked out from its items at read time, never
 * stored. Three of the five kinds are answered by records that already hold
 * the answer, so these tests set up that record and check the item closes -
 * rather than checking a status column somebody remembered to update.
 */
class MilestoneProgressTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::create([
            'email' => 'member@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Member',
        ]);

        $this->team = Team::create([
            'team_name' => 'Checklist Team',
            'team_status' => 'Active',
            'leader_id' => $this->student->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create([
            'student_id' => $this->student->student_id,
            'team_id' => $this->team->team_id,
        ]);
    }

    private function makeMilestone(?string $due = null): Milestone
    {
        return Milestone::create([
            'team_id' => $this->team->team_id,
            'title' => 'Capstone 1',
            'due_date' => $due,
            'status' => 'todo',
        ]);
    }

    private function uploadChapter(string $chapter, ?string $at = null): int
    {
        return DB::table('capstone_documents')->insertGetId([
            'student_id' => $this->student->student_id,
            'file_name' => "chapter-{$chapter}.pdf",
            'file_path' => "docs/chapter-{$chapter}.pdf",
            'chapter' => $chapter,
            'document_status' => 'pending',
            'created_at' => $at ?? now(),
            'updated_at' => $at ?? now(),
        ]);
    }

    public function test_a_form_is_closed_by_being_ticked(): void
    {
        $milestone = $this->makeMilestone();
        $item = $milestone->items()->create([
            'title' => 'Signed Project Team Assignment Form',
            'kind' => MilestoneItem::FORM,
        ]);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $milestone->progress()->items->first()->state);

        $item->update(['completed_at' => now()]);

        $progress = $milestone->fresh()->progress();
        $this->assertSame(MilestoneProgress::DONE, $progress->items->first()->state);
        $this->assertSame(1, $progress->completed);
        $this->assertSame(100, $progress->percent);
    }

    public function test_a_chapter_draft_closes_when_the_team_uploads_it(): void
    {
        $milestone = $this->makeMilestone();
        $milestone->items()->create([
            'title' => 'Chapter 2 Draft',
            'kind' => MilestoneItem::DOCUMENT,
            'chapter' => '2',
        ]);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $milestone->progress()->items->first()->state);

        // Uploading chapter 1 must not close the chapter 2 line.
        $this->uploadChapter('1');
        $this->assertSame(MilestoneProgress::NOT_STARTED, $milestone->fresh()->progress()->items->first()->state);

        $this->uploadChapter('2');
        $this->assertSame(MilestoneProgress::DONE, $milestone->fresh()->progress()->items->first()->state);
    }

    /**
     * The draft and the CLF approving it are separate lines on the paper, so
     * handing a chapter in must not close its approval.
     */
    public function test_an_approval_needs_a_verdict_not_just_a_review(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $milestone = $this->makeMilestone();
        $milestone->items()->create([
            'title' => 'Signed CLF Approving Chapter 1 (CaPA/Adviser)',
            'kind' => MilestoneItem::APPROVAL,
            'role' => 'adviser',
            'chapter' => '1',
        ]);

        $fileId = $this->uploadChapter('1');

        $review = CapstoneDocumentReview::create([
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => CapstoneDocumentReview::REVIEWED,
        ]);

        // Finished reading is not approval - that is the whole distinction.
        $state = $milestone->fresh()->progress()->items->first();
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state);
        $this->assertSame('adviser', $state->waiting_on);

        // Nor is asking for changes.
        $review->update(['verdict' => CapstoneDocumentReview::NEEDS_REVISION, 'verdict_at' => now()]);
        $this->assertSame(MilestoneProgress::HANDED_IN, $milestone->fresh()->progress()->items->first()->state);

        $review->update(['verdict' => CapstoneDocumentReview::APPROVED, 'verdict_at' => now()]);
        $this->assertSame(MilestoneProgress::DONE, $milestone->fresh()->progress()->items->first()->state);
    }

    /** The checklist asks for the adviser and the consultant separately. */
    public function test_the_consultants_approval_does_not_close_the_advisers_line(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $milestone = $this->makeMilestone();
        $milestone->items()->create([
            'title' => 'Signed CLF Recommending for Title Defense (CaPA/Adviser)',
            'kind' => MilestoneItem::APPROVAL,
            'role' => 'adviser',
        ]);

        $fileId = $this->uploadChapter('proposal');

        CapstoneDocumentReview::create([
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'consultant',
            'review_status' => CapstoneDocumentReview::REVIEWED,
            'verdict' => CapstoneDocumentReview::APPROVED,
            'verdict_at' => now(),
        ]);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $milestone->fresh()->progress()->items->first()->state);
    }

    public function test_a_defense_closes_on_the_recorded_outcome(): void
    {
        $milestone = $this->makeMilestone();
        $milestone->items()->create([
            'title' => 'Title Defense',
            'kind' => MilestoneItem::DEFENSE,
            'defense_type' => 'Title Defense',
        ]);

        $defenseId = DB::table('defense_schedule')->insertGetId([
            'team_id' => $this->team->team_id,
            'defense_type' => 'Title Defense',
        ]);

        // Scheduled but undecided is not done.
        $this->assertSame(MilestoneProgress::HANDED_IN, $milestone->fresh()->progress()->items->first()->state);

        DB::table('defense_schedule')->where('defense_id', $defenseId)->update([
            'outcome' => DefenseOutcome::PASSED_WITH_REVISION,
            'outcome_recorded_at' => now(),
        ]);

        // Passed with revision still means they sat it and got through.
        $this->assertSame(MilestoneProgress::DONE, $milestone->fresh()->progress()->items->first()->state);
    }

    public function test_late_and_overdue_are_worked_out_from_the_dates(): void
    {
        $milestone = $this->makeMilestone(now()->subDays(10)->toDateString());
        $onTime = $milestone->items()->create(['title' => 'Early form', 'kind' => MilestoneItem::FORM]);
        $milestone->items()->create(['title' => 'Nothing yet', 'kind' => MilestoneItem::FORM]);

        // Nothing handed in and the date gone.
        $this->assertSame(2, $milestone->progress()->overdue);

        $onTime->update(['completed_at' => now()->subDays(4)]);

        $progress = $milestone->fresh()->progress();
        $states = $progress->items->pluck('state')->all();
        $this->assertContains(MilestoneProgress::DONE_LATE, $states);
        $this->assertContains(MilestoneProgress::OVERDUE, $states);
        $this->assertSame(1, $progress->completed, 'late still counts as complete');
        $this->assertSame(6, $progress->items->firstWhere('state', MilestoneProgress::DONE_LATE)->days_late);

        // Moving the deadline out un-flags both, with nothing to correct.
        $milestone->update(['due_date' => now()->addWeek()->toDateString()]);
        $after = $milestone->fresh()->progress();
        $this->assertSame(0, $after->late);
        $this->assertSame(0, $after->overdue);
        $this->assertSame(MilestoneProgress::DONE, $after->items->firstWhere('item.id', $onTime->id)->state);
    }

    public function test_the_capstone_1_template_writes_the_checklist(): void
    {
        $milestone = $this->makeMilestone();

        $written = MilestoneTemplate::applyTo($milestone, MilestoneTemplate::CAPSTONE_1);

        $this->assertSame(19, $written);

        $items = $milestone->fresh()->items()->orderBy('position')->get();
        $this->assertSame('Signed Project Team Assignment Form', $items->first()->title);
        $this->assertSame('Signed (Noted) TD RSC', $items->last()->title);

        $byKind = $items->groupBy('kind')->map->count();
        $this->assertSame(8, $byKind[MilestoneItem::FORM]);
        $this->assertSame(5, $byKind[MilestoneItem::APPROVAL]);
        $this->assertSame(4, $byKind[MilestoneItem::DOCUMENT]);
        $this->assertSame(2, $byKind[MilestoneItem::DEFENSE]);
    }

    /**
     * Re-applying a revised checklist must not throw away what is already
     * ticked, and must leave a CaPSA's own additions alone.
     */
    public function test_reapplying_the_template_keeps_what_is_already_done(): void
    {
        $milestone = $this->makeMilestone();
        MilestoneTemplate::applyTo($milestone, MilestoneTemplate::CAPSTONE_1);

        $form = $milestone->items()->where('template_key', 'team_assignment_form')->first();
        $form->update(['completed_at' => now()]);

        $ownItem = $milestone->items()->create(['title' => 'Section-specific extra', 'kind' => MilestoneItem::CUSTOM]);

        MilestoneTemplate::applyTo($milestone, MilestoneTemplate::CAPSTONE_1);

        $this->assertSame(20, $milestone->fresh()->items()->count(), 'no duplicates, and the custom item survives');
        $this->assertNotNull($form->fresh()->completed_at, 'the tick survives');
        $this->assertNotNull($ownItem->fresh(), "a CaPSA's own item is untouched");
    }
}
