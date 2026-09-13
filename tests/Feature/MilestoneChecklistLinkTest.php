<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An upload that names its checklist line, and what that line then says:
 * who is holding it, how many times it was sent back, and whether it was late.
 */
class MilestoneChecklistLinkTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Student $student;

    private CapstoneExpert $adviser;

    private Milestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::create(['email' => 'member@my.cspc.edu.ph', 'f_name' => 'Team', 'l_name' => 'Member']);
        $this->team = Team::create([
            'team_name' => 'Link Team', 'team_status' => 'Active', 'leader_id' => $this->student->student_id,
            'is_confirmed' => true, 'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $this->team->team_id]);
        $this->adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->milestone = Milestone::create(['team_id' => $this->team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
    }

    private function upload(?MilestoneItem $item, string $at, ?string $chapter = null): int
    {
        return DB::table('capstone_documents')->insertGetId([
            'student_id' => $this->student->student_id,
            'file_name' => 'final_na_final',
            'file_path' => 'submissions/final.pdf',
            'chapter' => $chapter,
            'milestone_item_id' => $item?->id,
            'document_status' => 'Pending',
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function review(int $fileId, ?string $verdict = null, ?string $at = null, string $role = 'adviser'): void
    {
        CapstoneDocumentReview::create([
            'file_id' => $fileId,
            'expert_id' => $this->adviser->expert_id,
            'role' => $role,
            'review_status' => $verdict ? CapstoneDocumentReview::REVIEWED : CapstoneDocumentReview::AWAITING,
            'verdict' => $verdict,
            'verdict_at' => $at,
        ]);
    }

    private function state(MilestoneItem $item): object
    {
        return $this->milestone->fresh()->progress()->items->firstWhere('item.id', $item->id);
    }

    public function test_an_upload_naming_a_line_closes_it_whatever_the_file_is_called(): void
    {
        $item = $this->milestone->items()->create(['title' => 'Business Process Analysis', 'kind' => MilestoneItem::DOCUMENT]);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state($item)->state);

        $this->upload($item, now()->toDateTimeString());

        $this->assertSame(MilestoneProgress::DONE, $this->state($item)->state);
    }

    public function test_an_upload_for_feedback_only_moves_nothing(): void
    {
        $item = $this->milestone->items()->create(['title' => 'Business Process Analysis', 'kind' => MilestoneItem::DOCUMENT]);
        $form = $this->milestone->items()->create(['title' => 'Signed Final Project Title Form', 'kind' => MilestoneItem::FORM]);

        $this->upload(null, now()->toDateTimeString());

        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state($item)->state);
        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state($form)->state);
    }

    /** Uploads from before a file could name its line say "Chapter 1"; the line says "1". */
    public function test_old_uploads_still_match_by_chapter(): void
    {
        $item = $this->milestone->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT, 'chapter' => '1']);

        $this->upload(null, now()->toDateTimeString(), 'Chapter 1');

        $this->assertSame(MilestoneProgress::DONE, $this->state($item)->state);
    }

    public function test_the_advisers_approval_of_a_chapter_draft_closes_that_chapters_clf(): void
    {
        $draft = $this->milestone->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT, 'chapter' => '1']);
        $clf = $this->milestone->items()->create(['title' => 'Signed CLF Approving Chapter 1', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser', 'chapter' => '1']);
        $other = $this->milestone->items()->create(['title' => 'Signed CLF Approving Chapter 2', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser', 'chapter' => '2']);

        $file = $this->upload($draft, now()->subDay()->toDateTimeString());
        $this->review($file, CapstoneDocumentReview::APPROVED, now()->toDateTimeString());

        $this->assertSame(MilestoneProgress::DONE, $this->state($clf)->state);
        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state($other)->state);
    }

    public function test_changes_requested_waits_on_the_team_counts_the_revision_and_is_never_overdue(): void
    {
        $clf = $this->milestone->items()->create([
            'title' => 'Signed CLF Approving Chapter 2', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $first = $this->upload($clf, now()->subDays(10)->toDateTimeString());
        $this->review($first, CapstoneDocumentReview::NEEDS_REVISION, now()->subDays(8)->toDateTimeString());

        $state = $this->state($clf);
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state, 'past due, but they did hand it in');
        $this->assertSame(MilestoneProgress::WAITING_ON_TEAM, $state->waiting_on);
        $this->assertTrue($state->changes_requested);
        $this->assertSame(1, $state->revision);

        // The 1st revision goes in: back with the adviser.
        $second = $this->upload($clf, now()->subDays(5)->toDateTimeString());
        $this->review($second);

        $state = $this->state($clf);
        $this->assertSame('adviser', $state->waiting_on);
        $this->assertFalse($state->changes_requested);

        CapstoneDocumentReview::where('file_id', $second)->update(['verdict' => CapstoneDocumentReview::APPROVED, 'verdict_at' => now()]);

        $state = $this->state($clf);
        $this->assertSame(MilestoneProgress::DONE, $state->state, 'first handed in before the due date, so not late');
        $this->assertSame(1, $state->revision, 'approved on the 1st revision');
    }

    public function test_late_is_judged_on_the_first_submission(): void
    {
        $draft = $this->milestone->items()->create([
            'title' => 'Chapter 3 Draft', 'kind' => MilestoneItem::DOCUMENT, 'due_date' => now()->subDays(6)->toDateString(),
        ]);

        $this->upload($draft, now()->subDays(2)->toDateTimeString());

        $state = $this->state($draft);
        $this->assertSame(MilestoneProgress::DONE_LATE, $state->state);
        $this->assertSame(4, $state->days_late);
    }

    public function test_a_scanned_form_waits_for_the_capsa_to_check_it(): void
    {
        $form = $this->milestone->items()->create(['title' => 'Signed Title Defense Hearing Notice', 'kind' => MilestoneItem::FORM]);

        $file = $this->upload($form, now()->toDateTimeString());

        $state = $this->state($form);
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state);
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $state->waiting_on);
        $this->assertSame($file, (int) $state->file_id);

        $form->update(['completed_at' => now()]);

        $this->assertSame(MilestoneProgress::DONE, $this->state($form)->state);
    }

    public function test_an_approval_the_capsa_wants_to_see_waits_on_them_after_the_verdict(): void
    {
        $clf = $this->milestone->items()->create([
            'title' => 'CLF Recommending for Title Defense', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser', 'needs_check' => true,
        ]);
        $this->assertTrue($clf->isCheckedByCapsa());

        $file = $this->upload($clf, now()->subDay()->toDateTimeString());
        $this->review($file, CapstoneDocumentReview::APPROVED, now()->toDateTimeString());

        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $this->state($clf)->waiting_on);

        $clf->update(['completed_at' => now()]);

        $this->assertSame(MilestoneProgress::DONE, $this->state($clf)->state);
    }
}
