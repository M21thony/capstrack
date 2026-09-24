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
            ->assertSee("openSubmission('{$draft->id}', this)", false)
            ->assertDontSee("openSubmission('{$defense->id}', this)", false);
    }

    /** A line still waiting on an earlier one offers no Upload that could only say "not yet". */
    public function test_a_line_waiting_on_an_earlier_one_has_no_upload(): void
    {
        $checklist = $this->departmentChecklist($this->capsa);
        foreach (['team_assignment_form', 'pre_proposal_title_form', 'role_acceptance_adviser', 'role_acceptance_consultant', 'role_acceptance_grammarian', 'final_title_form'] as $key) {
            DeliverableCheck::create(['deliverable_id' => $this->line($checklist, $key)->id, 'team_id' => $this->team->team_id, 'status' => 'complete', 'checked_at' => now()]);
        }
        DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => 'Pre-proposal Defense', 'attempt' => 1, 'schedule_date' => now(), 'outcome' => 'passed', 'outcome_recorded_at' => now()]);
        $this->checklist->delete();

        $this->actingAs($this->student)->get('/student/progress')
            ->assertOk()
            ->assertSee("openSubmission('{$this->line($checklist, 'chapter_1_draft')->id}', this)", false)
            // The signed CLF waits for the draft it approves.
            ->assertDontSee("openSubmission('{$this->line($checklist, 'clf_chapter_1')->id}', this)", false);
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
            ->assertSee("openSubmission('{$draft->id}', this)", false);
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
