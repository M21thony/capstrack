<?php

namespace Tests\Feature;

use App\Mail\ReviewReminderMail;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Every day, an adviser or consultant who still has a checklist document to
 * review gets one email listing all of them, most urgent first.
 */
class ReviewReminderTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Student $student;

    private Checklist $checklist;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        [, $this->student] = $this->teamInSection('CapsTrack');
        $this->checklist = $this->emptyChecklist($this->capsaFor());
    }

    private function waitingReview(CapstoneExpert $expert, ?Deliverable $deliverable, string $status = CapstoneDocumentReview::AWAITING): void
    {
        CapstoneDocumentReview::create([
            'file_id' => $this->upload($this->student, $deliverable),
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => $status,
        ]);
    }

    public function test_each_reviewer_gets_one_email_listing_everything_waiting_on_them(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $later = $this->addDeliverable($this->checklist, ['title' => 'Chapter 2 Draft', 'kind' => Deliverable::DOCUMENT, 'due_date' => now()->addDays(9)->toDateString()]);
        $overdue = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'due_date' => now()->subDay()->toDateString()]);

        $this->waitingReview($adviser, $later);
        $this->waitingReview($adviser, $overdue, CapstoneDocumentReview::IN_PROGRESS);

        $this->artisan('milestones:send-review-reminders')->assertSuccessful();

        Mail::assertQueued(ReviewReminderMail::class, 1);
        Mail::assertQueued(ReviewReminderMail::class, function (ReviewReminderMail $mail) use ($adviser) {
            return $mail->hasTo($adviser->email)
                && $mail->waiting->count() === 2
                && $mail->waiting->first()->title === 'Chapter 1 Draft'
                && $mail->waiting->first()->team_name === 'CapsTrack';
        });
    }

    public function test_finished_reviews_feedback_only_uploads_and_removed_deliverables_are_not_chased(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT]);
        $removed = $this->addDeliverable($this->checklist, ['title' => 'Dropped Draft', 'kind' => Deliverable::DOCUMENT]);

        $this->waitingReview($adviser, $draft, CapstoneDocumentReview::REVIEWED);
        $this->waitingReview($adviser, null);
        $this->waitingReview($adviser, $removed);
        $removed->delete();

        $this->artisan('milestones:send-review-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
