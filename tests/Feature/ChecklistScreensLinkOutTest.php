<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\Student;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The tracker shows where things stand and sends people to where the work
 * happens: a student to Document Submission, the CaPSA to the uploaded scan.
 */
class ChecklistScreensLinkOutTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Student $student;

    private Team $team;

    private CapstoneExpert $capsa;

    private Checklist $checklist;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->student] = $this->teamInSection('Linked Team');
        $this->capsa = $this->capsaFor();
        $this->checklist = $this->emptyChecklist($this->capsa);
    }

    public function test_a_student_uploads_an_open_deliverable_from_the_tracker(): void
    {
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT]);
        $defense = $this->addDeliverable($this->checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Title Defense']);

        $this->actingAs($this->student)->get('/student/progress')
            ->assertOk()
            ->assertSee("openSubmissionDrawer('{$draft->id}')", false)
            ->assertDontSee("openSubmissionDrawer('{$defense->id}')", false);
    }

    /** Changes are asked for on the draft, so the revision goes up against the draft. */
    public function test_a_clf_sent_back_for_changes_says_which_revision_is_owed(): void
    {
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '1']);
        $this->addDeliverable($this->checklist, ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser', 'chapter' => '1']);

        CapstoneDocumentReview::create([
            'file_id' => $this->upload($this->student, $draft, now()->subDay()->toDateTimeString()), 'role' => 'adviser',
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'review_status' => CapstoneDocumentReview::REVIEWED,
            'verdict' => CapstoneDocumentReview::NEEDS_REVISION, 'verdict_at' => now(),
        ]);

        $this->actingAs($this->student)->get('/student/progress')
            ->assertOk()
            ->assertSee('1st revision requested')
            ->assertSee('Upload 1st revision')
            ->assertSee("openSubmissionDrawer('{$draft->id}')", false);
    }

    public function test_the_capsa_opens_an_uploaded_scan_from_the_tracker(): void
    {
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed Final Project Title Form']);
        $fileId = $this->upload($this->student, $form, path: 'submissions/title.jpg');

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.team_management', ['tab' => 'milestones']))
            ->assertOk()
            ->assertSee('id="ms-tracker"', false)
            ->assertSee(route('capsa.documents.file', $fileId), false)
            ->assertSee(route('capsa.deliverables.checks.update', [$form->id, $this->team->team_id]), false);
    }
}
