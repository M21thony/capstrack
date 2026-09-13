<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The tracker shows where things stand and sends people to where the work
 * happens: a student to Document Submission, the CaPSA to the uploaded file.
 */
class ChecklistScreensLinkOutTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private Team $team;

    private Milestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        $this->team = Team::create([
            'team_name' => 'Linked Team', 'team_status' => 'Active', 'leader_id' => $this->student->student_id,
            'is_confirmed' => true, 'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $this->team->team_id]);
        $this->milestone = Milestone::create(['team_id' => $this->team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
    }

    private function upload(MilestoneItem $item): int
    {
        return DB::table('capstone_documents')->insertGetId([
            'student_id' => $this->student->student_id, 'file_name' => 'x', 'file_path' => 'submissions/x.pdf',
            'milestone_item_id' => $item->id, 'document_status' => 'Pending', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
    }

    public function test_a_student_submits_an_open_line_from_the_tracker(): void
    {
        $draft = $this->milestone->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT]);
        $defense = $this->milestone->items()->create(['title' => 'Title Defense', 'kind' => MilestoneItem::DEFENSE, 'defense_type' => 'Title Defense']);

        $this->actingAs($this->student)->get('/student/progress')
            ->assertOk()
            ->assertSee(route('student.submission', ['item' => $draft->id]), false)
            ->assertDontSee(route('student.submission', ['item' => $defense->id]), false);
    }

    public function test_a_line_sent_back_says_which_revision_is_owed(): void
    {
        $clf = $this->milestone->items()->create(['title' => 'Signed CLF Approving Chapter 1', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser']);
        CapstoneDocumentReview::create([
            'file_id' => $this->upload($clf), 'role' => 'adviser',
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'review_status' => CapstoneDocumentReview::REVIEWED,
            'verdict' => CapstoneDocumentReview::NEEDS_REVISION, 'verdict_at' => now(),
        ]);

        $this->actingAs($this->student)->get('/student/progress')
            ->assertOk()
            ->assertSee('1st revision requested')
            ->assertSee('Submit 1st revision');
    }

    /** An approval the CaPSA asked to see is hers to check only after the reviewer approved it. */
    public function test_the_capsa_cannot_check_an_approval_nobody_approved(): void
    {
        $clf = $this->milestone->items()->create([
            'title' => 'CLF Recommending for Title Defense', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser', 'needs_check' => true,
        ]);
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));
        $check = fn () => $this->actingAs($capsa, 'expert')->putJson(route('capsa.milestones.items.done', $clf), ['done' => true]);

        $check()->assertStatus(422);
        $this->assertNull($clf->fresh()->completed_at);

        CapstoneDocumentReview::create([
            'file_id' => $this->upload($clf), 'role' => 'adviser',
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'review_status' => CapstoneDocumentReview::REVIEWED,
            'verdict' => CapstoneDocumentReview::APPROVED, 'verdict_at' => now(),
        ]);

        $check()->assertOk();
        $this->assertNotNull($clf->fresh()->completed_at);
    }

    public function test_the_capsa_opens_a_scanned_form_from_the_grid_and_checks_it(): void
    {
        $form = $this->milestone->items()->create(['title' => 'Signed Final Project Title Form', 'kind' => MilestoneItem::FORM]);
        $fileId = $this->upload($form);

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management', ['tab' => 'milestones']))
            ->assertOk()
            ->assertSee(route('capsa.documents.file', $fileId), false)
            ->assertSee('Mark as checked')
            ->assertDontSee('sighted');
    }
}
