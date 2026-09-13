<?php

namespace Tests\Feature;

use App\Mail\ReviewReminderMail;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every day, an adviser or consultant who still has a checklist document to
 * review gets one email listing all of them, most urgent first.
 */
class ReviewReminderTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private Milestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'CapsTrack', 'team_status' => 'Active', 'leader_id' => $this->student->student_id, 'is_confirmed' => true]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $team->team_id]);
        $this->milestone = Milestone::create(['team_id' => $team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
    }

    private function waitingReview(CapstoneExpert $expert, ?MilestoneItem $item, string $status = CapstoneDocumentReview::AWAITING): void
    {
        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $this->student->student_id, 'file_name' => 'draft', 'file_path' => 'submissions/draft.pdf',
            'milestone_item_id' => $item?->id, 'document_status' => 'Pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        CapstoneDocumentReview::create(['file_id' => $fileId, 'expert_id' => $expert->expert_id, 'role' => 'adviser', 'review_status' => $status]);
    }

    public function test_each_reviewer_gets_one_email_listing_everything_waiting_on_them(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $later = $this->milestone->items()->create(['title' => 'Chapter 2 Draft', 'kind' => MilestoneItem::DOCUMENT, 'due_date' => now()->addDays(9)->toDateString()]);
        $overdue = $this->milestone->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT, 'due_date' => now()->subDay()->toDateString()]);

        $this->waitingReview($adviser, $later);
        $this->waitingReview($adviser, $overdue, CapstoneDocumentReview::IN_PROGRESS);

        $this->artisan('milestones:send-review-reminders')->assertSuccessful();

        Mail::assertQueued(ReviewReminderMail::class, 1);
        Mail::assertQueued(ReviewReminderMail::class, function (ReviewReminderMail $mail) use ($adviser) {
            return $mail->hasTo($adviser->email)
                && $mail->waiting->count() === 2
                && $mail->waiting->first()->title === 'Chapter 1 Draft';
        });
    }

    public function test_finished_reviews_and_feedback_only_uploads_are_not_chased(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $item = $this->milestone->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT]);

        $this->waitingReview($adviser, $item, CapstoneDocumentReview::REVIEWED);
        $this->waitingReview($adviser, null);

        $this->artisan('milestones:send-review-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
