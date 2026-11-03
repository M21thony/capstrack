<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Student;
use App\Models\Team;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * An upload that names its deliverable, and what that deliverable then says:
 * who is holding it, how many times it was sent back, and whether it was late.
 */
class MilestoneChecklistLinkTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $student;

    private CapstoneExpert $adviser;

    private Checklist $checklist;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->student] = $this->teamInSection('Link Team');
        $this->adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->checklist = $this->emptyChecklist($this->capsaFor());
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

    private function state(Deliverable $deliverable): object
    {
        return $this->progressOf($this->checklist, $this->team, $deliverable);
    }

    public function test_an_upload_naming_a_line_hands_it_in_whatever_the_file_is_called(): void
    {
        $bpa = $this->addDeliverable($this->checklist, ['title' => 'Business Process Analysis', 'kind' => Deliverable::DOCUMENT]);

        $this->upload($this->student, $bpa, path: 'submissions/final_na_final.pdf');

        $this->assertSame(MilestoneProgress::HANDED_IN, $this->state($bpa)->state);
    }

    public function test_an_upload_for_feedback_only_moves_nothing(): void
    {
        $bpa = $this->addDeliverable($this->checklist, ['title' => 'Business Process Analysis', 'kind' => Deliverable::DOCUMENT]);
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed Final Project Title Form']);

        $this->upload($this->student, null);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state($bpa)->state);
        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state($form)->state);
    }

    public function test_the_advisers_approval_of_a_draft_moves_only_that_chapters_clf(): void
    {
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '1']);
        $clf = $this->addDeliverable($this->checklist, ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser', 'chapter' => '1']);
        $other = $this->addDeliverable($this->checklist, ['title' => 'Signed CLF Approving Chapter 2', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser', 'chapter' => '2']);

        $this->review($this->upload($this->student, $draft, now()->subDay()->toDateTimeString()), CapstoneDocumentReview::APPROVED, now()->toDateTimeString());

        $this->assertSame(MilestoneProgress::WAITING_ON_SIGNED_COPY, $this->state($clf)->waiting_on);
        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state($other)->state);
    }

    public function test_changes_requested_waits_on_the_team_counts_the_revision_and_is_never_overdue(): void
    {
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 2 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '2']);
        $clf = $this->addDeliverable($this->checklist, [
            'title' => 'Signed CLF Approving Chapter 2', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser', 'chapter' => '2',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->review($this->upload($this->student, $draft, now()->subDays(10)->toDateTimeString()), CapstoneDocumentReview::NEEDS_REVISION, now()->subDays(8)->toDateTimeString());

        $state = $this->state($clf);
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state, 'past due, but they did hand the draft in');
        $this->assertSame(MilestoneProgress::WAITING_ON_TEAM, $state->waiting_on);
        $this->assertTrue($state->changes_requested);
        $this->assertSame(1, $state->revision);

        // The 1st revision goes in: back with the adviser.
        $this->review($this->upload($this->student, $draft, now()->subDays(5)->toDateTimeString()));

        $state = $this->state($clf);
        $this->assertSame('adviser', $state->waiting_on);
        $this->assertFalse($state->changes_requested);
    }

    public function test_late_is_judged_on_the_first_submission(): void
    {
        $draft = $this->addDeliverable($this->checklist, [
            'title' => 'Chapter 3 Draft', 'kind' => Deliverable::DOCUMENT, 'due_date' => now()->subDays(6)->toDateString(),
        ]);

        $file = $this->upload($this->student, $draft, now()->subDays(2)->toDateTimeString());
        $this->review($file, CapstoneDocumentReview::APPROVED, now()->toDateTimeString());
        $this->review($file, CapstoneDocumentReview::APPROVED, now()->toDateTimeString(), 'consultant');
        DeliverableCheck::create(['deliverable_id' => $draft->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);

        $state = $this->state($draft);
        $this->assertSame(MilestoneProgress::DONE_LATE, $state->state);
        $this->assertSame(4, $state->days_late);
    }

    public function test_a_scanned_form_waits_for_the_capsa_to_check_it(): void
    {
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed Title Defense Hearing Notice']);

        $file = $this->upload($this->student, $form, path: 'submissions/notice.jpg');

        $state = $this->state($form);
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state);
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $state->waiting_on);
        $this->assertSame($file, (int) $state->file_id);
    }
}
