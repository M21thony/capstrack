<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\DeliverableCheck;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * When the CaPSA/TSA sends something back, nothing the student sent is removed:
 * they are told what to change, and replace the file with a corrected copy.
 */
class SentBackKeepsTheFileTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_a_paper_sent_back_keeps_the_file_and_tells_the_team(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $checklist = $this->departmentChecklist($capsa);
        [$team, $leader] = $this->teamInSection('Bulbasaur');
        $mate = Student::factory()->withCompleteProfile()->withTourCompleted()->create(['course' => $leader->course, 'year_section' => $leader->year_section]);
        StudentSchoolYear::create(['student_id' => $mate->student_id, 'team_id' => $team->team_id]);
        $form = $this->line($checklist, 'team_assignment_form');
        $fileId = $this->upload($leader, $form, null, null, 'submissions/team-form.pdf');

        $this->actingAs($capsa, 'expert')
            ->putJson(route('capsa.deliverables.checks.update', [$form->id, $team->team_id]), ['status' => DeliverableCheck::SENT_BACK, 'note' => 'The adviser did not sign it.'])
            ->assertOk();

        $this->assertNotNull(CapstoneDocument::find($fileId), 'the file they sent is still there');
        foreach ([$leader, $mate] as $member) {
            $this->assertTrue(Notification::where('student_id', $member->student_id)->where('type', 'paper_sent_back')->exists());
        }

        $row = $this->progressOf($checklist, $team, $form);
        $this->assertSame(MilestoneProgress::WAITING_ON_TEAM, $row->waiting_on);
        $this->assertSame($fileId, $row->file_id);

        $this->actingAs($leader->fresh(), 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Replace file')
            ->assertSee('The file you sent is kept. Replace it with a corrected copy.')
            ->assertSee('The adviser did not sign it.')
            // Not listed on Documents, so the row is where the team opens what it sent.
            ->assertSee(route('student.documents.file', $fileId), false)
            ->assertSee('View the file you sent');

        // The corrected copy goes back to the CaPSA/TSA to check; both versions are kept.
        $this->upload($leader, $form, now()->addMinute()->toDateTimeString(), null, 'submissions/team-form-2.pdf');
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $this->progressOf($checklist, $team, $form)->waiting_on);
        $this->assertSame(2, CapstoneDocument::where('deliverable_id', $form->id)->count());
    }

    public function test_a_receipt_sent_back_stays_on_record_and_the_student_is_told(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$team, $student] = $this->teamInSection('Bulbasaur');
        $post = ExpensePost::create(['admin_id' => $capsa->expert_id, 'title' => 'Title Defense Fee', 'course' => 'BSIT', 'amount' => 300]);
        $record = FinancialRecord::create([
            'student_id' => $student->student_id, 'expense_post_id' => $post->id, 'receipt_image' => 'receipts/r.jpg',
            'receipt_number' => 'OR 001', 'f_record_status' => 'pending', 'payment_for' => $post->title, 'amount' => 300, 'uploaded_at' => now(),
        ]);

        $this->app['auth']->guard('expert')->setUser($capsa);
        $this->patchJson(route('api.admin.financial.update', $record->id), ['status' => 'rejected', 'rejection_reason' => 'The photo is blurry.'])
            ->assertOk();

        $record->refresh();
        $this->assertSame('rejected', $record->f_record_status);
        $this->assertSame('receipts/r.jpg', $record->receipt_image, 'the photo stays on record');
        $notice = Notification::where('student_id', $student->student_id)->where('type', 'receipt_sent_back')->firstOrFail();
        $this->assertStringContainsString('The photo is blurry.', $notice->message);
    }
}
