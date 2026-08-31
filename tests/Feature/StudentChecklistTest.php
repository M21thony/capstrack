<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What a team sees of its own checklist.
 *
 * The page used to read announcements - it found every announcement whose
 * title began "Milestone Reminder:" and matched milestones by that title
 * string - so a team saw whichever announcements had been posted rather than
 * the deliverables anyone was tracking.
 */
class StudentChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::factory()->withCompleteProfile()->create([
            'email' => 'member@my.cspc.edu.ph',
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

        $this->actingAs($this->student, 'web');
    }

    private function milestoneWithChecklist(): Milestone
    {
        $milestone = Milestone::create([
            'team_id' => $this->team->team_id,
            'title' => 'Capstone 1',
            'due_date' => now()->addWeeks(3)->toDateString(),
            'status' => 'todo',
        ]);

        MilestoneTemplate::applyTo($milestone, MilestoneTemplate::CAPSTONE_1);

        return $milestone;
    }

    public function test_a_team_sees_its_checklist(): void
    {
        $this->milestoneWithChecklist();

        $response = $this->get('/student/progress');

        $response->assertOk();
        $response->assertSee('Capstone 1');
        $response->assertSee('0 of 19 complete');
        $response->assertSee('Signed Project Team Assignment Form');
        $response->assertSee('Chapter 1 Draft');
    }

    /** The CaPSA ticking a form is what the team sees change. */
    public function test_a_form_the_capsa_ticked_shows_as_done(): void
    {
        $milestone = $this->milestoneWithChecklist();
        $milestone->items()
            ->where('template_key', 'team_assignment_form')
            ->update(['completed_at' => now()->subDays(3)]);

        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('1 of 19 complete');
    }

    /**
     * The question a team actually has is who is holding a thing up, so an
     * approval waiting on the adviser says so rather than just "pending".
     */
    public function test_an_approval_names_who_is_holding_it(): void
    {
        $milestone = $this->milestoneWithChecklist();
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);

        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $this->student->student_id,
            'file_name' => 'ch1.pdf',
            'file_path' => 'docs/ch1.pdf',
            'chapter' => '1',
            'document_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CapstoneDocumentReview::create([
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => CapstoneDocumentReview::REVIEWED,
        ]);

        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('With your adviser')
            // and the draft itself closed on the upload
            ->assertSee('1 of 19 complete');
    }

    public function test_a_team_with_no_checklist_is_told_so(): void
    {
        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('Your CaPSA has not set up a checklist for your team yet.');
    }

    /**
     * Nothing on the checklist is the team's to close - the CaPSA sights a
     * form, an upload closes a draft, the adviser approves a CLF - so there is
     * nothing here to press.
     */
    public function test_the_checklist_is_read_only(): void
    {
        $this->milestoneWithChecklist();

        $response = $this->get('/student/progress');

        $response->assertOk();
        $response->assertDontSee('Mark as Done');
        $response->assertDontSee('openUploadModal');
    }
}
