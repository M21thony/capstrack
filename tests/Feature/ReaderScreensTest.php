<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The annotator and the Review Viewer say what the document is - whose, for
 * which requirement, which round - and the viewer tells the team what the
 * reviewer decided and what to send next.
 */
class ReaderScreensTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private CapstoneExpert $adviser;

    private Deliverable $item;

    private int $reviewId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'CapsTrack', 'team_status' => 'Active', 'leader_id' => $this->student->student_id, 'is_confirmed' => true]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $team->team_id]);

        $this->item = Checklist::create(['capsa_id' => CapstoneExpert::factory()->create()->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1 Checklist'])
            ->milestones()->create(['name' => 'Chapter 2', 'position' => 0])
            ->deliverables()->create(['title' => 'Chapter 2 Draft', 'kind' => Deliverable::DOCUMENT, 'due_date' => '2026-10-17']);

        $this->adviser = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Ana', 'last_name' => 'Reyes']);

        // The team's second upload of this requirement: its 1st revision.
        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $this->student->student_id, 'file_name' => 'Chapter2_final', 'file_path' => 'submissions/ch2.pdf',
            'file_version' => 2, 'deliverable_id' => $this->item->id, 'document_status' => 'Pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->reviewId = DB::table('capstone_document_review')->insertGetId([
            'file_id' => $fileId, 'expert_id' => $this->adviser->expert_id, 'role' => 'adviser',
            'review_status' => 'Reviewed', 'verdict' => 'needs_revision', 'verdict_at' => now(), 'reviewed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('capstone_document_review')->insert([
            'file_id' => $fileId, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => 'consultant', 'review_status' => 'To Review', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('pdf_remarks')->insert([
            'review_id' => $this->reviewId, 'is_general' => true, 'page_number' => 0, 'comment' => 'Add three local studies.',
            'section' => 'Whole chapter', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pdf_remarks')->insert([
            'review_id' => $this->reviewId, 'is_general' => false, 'page_number' => 3, 'top_position' => 0.2, 'left_position' => 0.1,
            'highlight_width' => 0.4, 'highlight_height' => 0.02, 'color' => 'pink', 'comment' => 'Cite this.',
            'section' => '2.2 Related Studies', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_annotator_says_whose_document_it_is_and_which_round(): void
    {
        $this->actingAs($this->adviser, 'expert')
            ->get(route('instructor.docreview.annotate', $this->reviewId))
            ->assertOk()
            ->assertSee('id="pdf-annotator-root"', false)
            ->assertSee('CapsTrack')
            ->assertSee('Chapter 2 Draft')
            ->assertSee('1st revision')
            ->assertSee('2nd revision')
            ->assertSee('Oct 17');
    }

    public function test_back_returns_where_the_reviewer_came_from_else_the_to_do(): void
    {
        $annotator = route('instructor.docreview.annotate', $this->reviewId);
        $backUrl = fn ($from) => $this->actingAs($this->adviser, 'expert')
            ->from($from)->get($annotator)->assertOk()->viewData('context')['backUrl'];
        $todo = route('instructor.teams', ['tab' => 'todo']);

        $team = url('/capsa/team-management/1');
        $this->assertSame($team, $backUrl($team));
        // Another site, or the annotator itself after a reload, goes to the To do.
        $this->assertSame($todo, $backUrl('https://evil.example/x'));
        $this->assertSame($todo, $backUrl($annotator));

        // Opened from an email: no Referer, and the session's last GET was a JSON fetch.
        $this->flushHeaders();
        $this->assertSame($todo, $this->actingAs($this->adviser, 'expert')
            ->withSession(['_previous' => ['url' => url('/instructor/schedule/slots')]])
            ->get($annotator)->assertOk()->viewData('context')['backUrl']);
    }

    public function test_the_team_sees_the_decision_and_is_sent_to_submit_the_next_revision(): void
    {
        $fileId = DB::table('capstone_documents')->value('file_id');

        // On the list, the file says what is asked of it.
        $this->actingAs($this->student, 'web')
            ->get(route('student.documents'))
            ->assertOk()
            ->assertSee('Needs Revision')
            ->assertSee('Submit 2nd revision')
            ->assertSee("openSubmission('{$this->item->id}', this, {$fileId})", false);

        // Opened, it reads with the reviewer's decision and remarks.
        $this->actingAs($this->student, 'web')
            ->get(route('student.documents', ['file' => $fileId]))
            ->assertOk()
            ->assertSee('Chapter2_final')
            ->assertSee('Needs revision')
            ->assertSee('Submit 2nd revision')
            ->assertSee("openSubmission('{$this->item->id}', this, {$fileId})", false)
            ->assertSee('Add three local studies.')
            ->assertSeeText('Consultant · not opened yet');
    }

    public function test_remarks_carry_their_section_to_the_viewer(): void
    {
        $this->actingAs($this->student, 'web')
            ->get(route('student.review.remarks', $this->reviewId))
            ->assertOk()
            ->assertJsonFragment(['section' => '2.2 Related Studies', 'color' => 'pink']);
    }
}
