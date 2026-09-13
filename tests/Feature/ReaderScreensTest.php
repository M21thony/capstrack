<?php

namespace Tests\Feature;

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
 * The annotator and the Review Viewer say what the document is - whose, for
 * which requirement, which round - and the viewer tells the team what the
 * reviewer decided and what to send next.
 */
class ReaderScreensTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private CapstoneExpert $adviser;

    private MilestoneItem $item;

    private int $reviewId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'CapsTrack', 'team_status' => 'Active', 'leader_id' => $this->student->student_id, 'is_confirmed' => true]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $team->team_id]);

        $milestone = Milestone::create(['team_id' => $team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
        $this->item = $milestone->items()->create(['title' => 'Chapter 2 Draft', 'kind' => MilestoneItem::DOCUMENT, 'due_date' => '2026-10-17']);

        $this->adviser = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Ana', 'last_name' => 'Reyes']);

        // The team's second upload of this requirement: its 1st revision.
        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $this->student->student_id, 'file_name' => 'Chapter2_final', 'file_path' => 'submissions/ch2.pdf',
            'file_version' => 2, 'milestone_item_id' => $this->item->id, 'document_status' => 'Pending',
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

    public function test_the_team_sees_the_decision_and_is_sent_to_submit_the_next_revision(): void
    {
        $this->actingAs($this->student, 'web')
            ->get(route('student.review'))
            ->assertOk()
            ->assertSee('Chapter2_final')
            ->assertSee('Needs revision')
            ->assertSee('Submit 2nd revision')
            ->assertSee(route('student.submission', ['item' => $this->item->id]))
            ->assertSee('Add three local studies.')
            ->assertSee('Consultant · still reviewing');
    }

    public function test_remarks_carry_their_section_to_the_viewer(): void
    {
        $this->actingAs($this->student, 'web')
            ->get(route('student.review.remarks', $this->reviewId))
            ->assertOk()
            ->assertJsonFragment(['section' => '2.2 Related Studies', 'color' => 'pink']);
    }
}
