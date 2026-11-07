<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneExpert;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Notification;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/** A team hands in a Bluebook line as a PDF or a Drive video link; it then waits on the dean. */
class BluebookTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_a_pdf_or_a_drive_link_goes_to_the_dean(): void
    {
        Storage::fake('local');
        $capsa = $this->capsaFor();
        [$team, $student] = $this->teamInSection('CapsTrack');
        $checklist = $this->emptyChecklist($capsa, milestone: 'Bluebook Clearance');
        $paper = $this->addDeliverable($checklist, ['title' => 'Approval Sheet', 'kind' => Deliverable::BLUEBOOK]);
        $video = $this->addDeliverable($checklist, ['title' => 'System Demo Video', 'kind' => Deliverable::BLUEBOOK]);
        $this->addDeliverable($checklist, ['title' => 'Bluebook Clearance', 'kind' => Deliverable::BLUEBOOK, 'template_key' => Deliverable::BLUEBOOK_CLEARANCE]);
        $this->actingAs($student);

        $this->get(route('student.bluebook'))->assertOk()->assertSee('Approval Sheet');

        $this->post(route('student.bluebook.store', $paper), ['file' => UploadedFile::fake()->create('sheet.pdf', 10, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $this->assertSame(MilestoneProgress::WAITING_ON_DEAN, $this->progressOf($checklist, $team, $paper)->waiting_on);

        $this->post(route('student.bluebook.store', $video), [])->assertSessionHasErrors(['file' => 'Choose a file or paste a Drive link.']);
        $this->post(route('student.bluebook.store', $video), ['link' => 'https://youtube.com/watch?v=abc'])->assertSessionHasErrors('link');
        $this->post(route('student.bluebook.store', $video), ['link' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWx/view?usp=sharing'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWx/view', CapstoneDocument::where('deliverable_id', $video->id)->value('file_path'));

        $file = CapstoneDocument::where('deliverable_id', $paper->id)->first();
        $this->get(route('student.bluebook.file', $file))->assertOk();
    }

    public function test_the_dean_accepts_sends_back_then_clears_the_team(): void
    {
        Storage::fake('local');
        $capsa = $this->capsaFor();
        [$team, $student] = $this->teamInSection('CapsTrack');
        $checklist = $this->emptyChecklist($capsa, milestone: 'Bluebook Clearance');
        $paper = $this->addDeliverable($checklist, ['title' => 'Approval Sheet', 'kind' => Deliverable::BLUEBOOK]);
        $video = $this->addDeliverable($checklist, ['title' => 'System Demo Video', 'kind' => Deliverable::BLUEBOOK]);
        $this->addDeliverable($checklist, ['title' => 'Bluebook Clearance', 'kind' => Deliverable::BLUEBOOK, 'template_key' => Deliverable::BLUEBOOK_CLEARANCE]);
        $this->actingAs($student)->post(route('student.bluebook.store', $paper), ['file' => UploadedFile::fake()->create('sheet.pdf', 10, 'application/pdf')]);
        $this->post(route('student.bluebook.store', $video), ['link' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWx/view']);

        $dean = CapstoneExpert::factory()->create(['is_admin' => true, 'approved_status' => true]);
        $this->actingAs($dean, 'expert');
        $this->get(route('admin.bluebook'))->assertOk()->assertSee('CapsTrack');
        $this->get(route('admin.bluebook.show', $team))->assertOk()->assertSee('Approval Sheet');

        // Sending back needs a reason, and the team is told it.
        $this->put(route('admin.bluebook.check', [$team, $paper]), ['status' => DeliverableCheck::SENT_BACK])->assertSessionHasErrors('note');
        $this->put(route('admin.bluebook.check', [$team, $paper]), ['status' => DeliverableCheck::SENT_BACK, 'note' => 'Unsigned.']);
        $this->assertSame(MilestoneProgress::WAITING_ON_TEAM, $this->progressOf($checklist, $team, $paper)->waiting_on);
        $this->assertTrue(Notification::where('type', 'paper_sent_back')->exists());

        $this->delete(route('admin.bluebook.undo', [$team, $paper]));
        $this->assertSame(MilestoneProgress::WAITING_ON_DEAN, $this->progressOf($checklist, $team, $paper)->waiting_on);

        // Clearing waits for every line, and makes the slip.
        $this->put(route('admin.bluebook.check', [$team, $paper]), ['status' => DeliverableCheck::COMPLETE]);
        $this->post(route('admin.bluebook.clear', $team))->assertStatus(422);
        $this->get(route('admin.bluebook.slip', $team))->assertNotFound();
        $this->put(route('admin.bluebook.check', [$team, $video]), ['status' => DeliverableCheck::COMPLETE]);
        $this->post(route('admin.bluebook.clear', $team))->assertRedirect(route('admin.bluebook.show', [$team, 'slip' => 1]));
        $this->get(route('admin.bluebook.slip', $team))->assertOk()->assertHeader('content-type', 'application/pdf');

        // A cleared slip is final.
        $this->delete(route('admin.bluebook.undo', [$team, $paper]))->assertStatus(422);
        $this->post(route('admin.bluebook.clear', $team))->assertStatus(422);
    }
}
