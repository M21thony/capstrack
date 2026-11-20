<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentReviewTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, CapstoneExpert> */
    private array $reviewers = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Same drift as PdfAnnotationTest: these tables only have `id()` +
        // timestamps in their base migrations, with the columns the app
        // actually relies on added outside of migrations in the real DB.
        Schema::table('capstone_documents', function ($table) {
            foreach ([
                'file_id' => fn ($t) => $t->unsignedBigInteger('file_id')->nullable(),
                'student_id' => fn ($t) => $t->unsignedBigInteger('student_id')->nullable(),
                'file_name' => fn ($t) => $t->string('file_name')->nullable(),
                'file_path' => fn ($t) => $t->string('file_path')->nullable(),
                'file_version' => fn ($t) => $t->unsignedInteger('file_version')->nullable(),
                'document_status' => fn ($t) => $t->string('document_status')->nullable(),
                'chapter' => fn ($t) => $t->string('chapter')->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('capstone_documents', $column)) {
                    $add($table);
                }
            }
        });

        Schema::table('capstone_document_review', function ($table) {
            foreach ([
                'review_id' => fn ($t) => $t->unsignedBigInteger('review_id')->nullable(),
                'file_id' => fn ($t) => $t->unsignedBigInteger('file_id')->nullable(),
                'expert_id' => fn ($t) => $t->unsignedBigInteger('expert_id')->nullable(),
                'role' => fn ($t) => $t->string('role')->nullable(),
                'review_status' => fn ($t) => $t->string('review_status')->nullable(),
                'reviewed_at' => fn ($t) => $t->timestamp('reviewed_at')->nullable(),
                'drive_file_id' => fn ($t) => $t->string('drive_file_id')->nullable(),
                'drive_web_view_link' => fn ($t) => $t->string('drive_web_view_link')->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('capstone_document_review', $column)) {
                    $add($table);
                }
            }
        });
    }

    /**
     * A real expert to own a fixture review - capstone_document_review has a
     * foreign key to capstone_experts, so a hardcoded id matches no row.
     */
    private function reviewerId(string $email = 'reviewer@gmail.com'): int
    {
        if (! isset($this->reviewers[$email])) {
            $this->reviewers[$email] = CapstoneExpert::create([
                'email' => $email,
                'first_name' => 'Review',
                'last_name' => 'Expert',
                'is_admin' => false,
                'approved_status' => true,
            ]);
        }

        return $this->reviewers[$email]->expert_id;
    }

    private function makeDocument(int $id, int $studentId, string $name, string $chapter): int
    {
        return DB::table('capstone_documents')->insertGetId([
            'file_id' => $id,
            'student_id' => $studentId,
            'file_name' => $name,
            'file_path' => 'submissions/'.$id.'.pdf',
            'file_version' => 1,
            'document_status' => 'Reviewed',
            'chapter' => $chapter,
        ]);
    }

    public function test_review_page_requires_auth(): void
    {
        $response = $this->get(route('student.review'));
        $response->assertRedirect(route('login'));
    }

    public function test_shows_empty_state_when_nothing_turned_in(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'nouploads@my.cspc.edu.ph',
        ]);

        // The Review Viewer is part of Documents now.
        $this->actingAs($student, 'web')->get(route('student.review'))->assertRedirect(route('student.documents'));

        $response = $this->actingAs($student, 'web')->get(route('student.documents'));

        $response->assertOk();
        // No team either, so the empty page says why.
        $response->assertSee('Documents opens once you are in a team');
        $response->assertDontSee('Reply to Expert');
    }

    public function test_shows_a_turned_in_review_with_drive_link_and_excludes_pending_ones(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'hasreview@my.cspc.edu.ph',
        ]);

        $docId = $this->makeDocument(1, $student->student_id, 'Chapter 3 Final', 'Chapter 3');

        $reviewedId = DB::table('capstone_document_review')->insertGetId([
            'review_id' => 1,
            'file_id' => $docId,
            'expert_id' => $this->reviewerId(),
            'role' => 'adviser',
            'review_status' => 'Reviewed',
            'reviewed_at' => now(),
            'drive_file_id' => 'DRIVE_FILE_ABC',
            'drive_web_view_link' => 'https://drive.google.com/file/d/DRIVE_FILE_ABC/view',
        ]);

        // A second, still-pending review on the same document should not show up.
        DB::table('capstone_document_review')->insertGetId([
            'review_id' => 2,
            'file_id' => $docId,
            'expert_id' => $this->reviewerId('second.reviewer@gmail.com'),
            'role' => 'grammarian',
            'review_status' => 'To Review',
        ]);

        // A link to the review opens that file on Documents, with its remarks.
        $this->actingAs($student, 'web')->get(route('student.review', ['review_id' => $reviewedId]))
            ->assertRedirect(route('student.documents', ['file' => $docId, 'review' => $reviewedId]));

        $response = $this->actingAs($student, 'web')->get(route('student.documents', ['file' => $docId]));

        $response->assertOk();
        $response->assertSee('Chapter 3 Final');
        $response->assertSee('Download Annotated PDF');
        $response->assertSee('https://drive.google.com/file/d/DRIVE_FILE_ABC/view');
        // The inline preview must use the clean local copy, never the baked
        // Drive export — same rule the annotator itself follows.
        $response->assertSee('id="student-pdf-viewer-root"', false);
        $response->assertSee(route('student.review.pdf', $reviewedId));
        $response->assertSee(route('student.review.remarks', $reviewedId));
        $response->assertDontSee('drive.google.com/file/d/DRIVE_FILE_ABC/preview');
        $response->assertDontSee('Reply to Expert');

        // The one turned in is read; the pending one only says it is not opened yet.
        $this->assertSame($reviewedId, $response->viewData('reader')->review->review_id);
        $response->assertSee('not opened yet');
    }

    public function test_does_not_show_another_students_reviews(): void
    {
        $me = Student::factory()->withCompleteProfile()->create(['email' => 'me@my.cspc.edu.ph']);
        $other = Student::factory()->withCompleteProfile()->create(['email' => 'other@my.cspc.edu.ph']);

        $otherDocId = $this->makeDocument(1, $other->student_id, 'Someone Elses Paper', 'Chapter 1');

        DB::table('capstone_document_review')->insertGetId([
            'review_id' => 1,
            'file_id' => $otherDocId,
            'expert_id' => $this->reviewerId(),
            'role' => 'adviser',
            'review_status' => 'Reviewed',
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($me, 'web')->get(route('student.documents'));

        $response->assertOk();
        $response->assertDontSee('Someone Elses Paper');

        // Nor through a link to it: it opens the list, not their review.
        $this->actingAs($me, 'web')->get(route('student.review', ['review_id' => 1]))->assertRedirect(route('student.documents'));
        $this->actingAs($me, 'web')->get(route('student.documents', ['file' => $otherDocId]))
            ->assertOk()->assertDontSee('Someone Elses Paper');
    }

    public function test_pdf_and_remarks_endpoints_reject_another_students_review(): void
    {
        $me = Student::factory()->withCompleteProfile()->create(['email' => 'me2@my.cspc.edu.ph']);
        $other = Student::factory()->withCompleteProfile()->create(['email' => 'other2@my.cspc.edu.ph']);

        $otherDocId = $this->makeDocument(1, $other->student_id, 'Not Mine', 'Chapter 1');

        $reviewId = DB::table('capstone_document_review')->insertGetId([
            'review_id' => 1,
            'file_id' => $otherDocId,
            'expert_id' => $this->reviewerId(),
            'role' => 'adviser',
            'review_status' => 'Reviewed',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($me, 'web')->get(route('student.review.pdf', $reviewId))->assertForbidden();
        $this->actingAs($me, 'web')->get(route('student.review.remarks', $reviewId))->assertForbidden();
        $this->actingAs($me, 'web')->get(route('student.review.clf', $reviewId))->assertForbidden();
    }

    public function test_student_can_download_clf_for_own_review(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'clf@my.cspc.edu.ph']);
        $docId = $this->makeDocument(1, $student->student_id, 'Chapter 5', 'Chapter 5');

        $reviewId = DB::table('capstone_document_review')->insertGetId([
            'review_id' => 1,
            'file_id' => $docId,
            'expert_id' => $this->reviewerId(),
            'role' => 'adviser',
            'review_status' => 'Reviewed',
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($student, 'web')->get(route('student.review.clf', $reviewId));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_remarks_endpoint_returns_only_page_anchored_comments(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'remarks@my.cspc.edu.ph']);
        $docId = $this->makeDocument(1, $student->student_id, 'Chapter 4', 'Chapter 4');

        $reviewId = DB::table('capstone_document_review')->insertGetId([
            'review_id' => 1,
            'file_id' => $docId,
            'expert_id' => $this->reviewerId(),
            'role' => 'adviser',
            'review_status' => 'Reviewed',
            'reviewed_at' => now(),
        ]);

        DB::table('pdf_remarks')->insert([
            'review_id' => $reviewId,
            'is_general' => false,
            'page_number' => 2,
            'top_position' => 0.1,
            'left_position' => 0.2,
            'highlight_width' => 0.5,
            'highlight_height' => 0.05,
            'color' => 'yellow',
            'comment' => 'Fix this sentence.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pdf_remarks')->insert([
            'review_id' => $reviewId,
            'is_general' => true,
            'page_number' => 0,
            'comment' => 'Overall good work.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student, 'web')->get(route('student.review.remarks', $reviewId));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['comment' => 'Fix this sentence.']);
        $response->assertJsonMissing(['comment' => 'Overall good work.']);
    }

    public function test_student_replies_to_a_remark_and_the_reviewer_sees_it(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'reply@my.cspc.edu.ph', 'f_name' => 'Ana', 'l_name' => 'Cruz']);
        $outsider = Student::factory()->withCompleteProfile()->create(['email' => 'outsider@my.cspc.edu.ph']);
        $docId = $this->makeDocument(1, $student->student_id, 'Chapter 2', 'Chapter 2');
        $expertId = $this->reviewerId();

        $reviewId = DB::table('capstone_document_review')->insertGetId([
            'review_id' => 1, 'file_id' => $docId, 'expert_id' => $expertId,
            'role' => 'adviser', 'review_status' => 'Reviewed', 'reviewed_at' => now(),
        ]);
        $otherReviewId = DB::table('capstone_document_review')->insertGetId([
            'review_id' => 2, 'file_id' => $docId, 'expert_id' => $expertId,
            'role' => 'adviser', 'review_status' => 'Reviewed', 'reviewed_at' => now(),
        ]);
        $remark = fn (int $review) => DB::table('pdf_remarks')->insertGetId([
            'review_id' => $review, 'is_general' => false, 'page_number' => 1,
            'top_position' => 0.1, 'left_position' => 0.1, 'highlight_width' => 0.2, 'highlight_height' => 0.05,
            'color' => 'yellow', 'comment' => 'Cite this.', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $remarkId = $remark($reviewId);
        $foreignRemarkId = $remark($otherReviewId);
        $url = fn (int $review, int $remark) => route('student.review.remarks.replies', [$review, $remark]);

        $this->actingAs($student, 'web')->postJson($url($reviewId, $remarkId), ['body' => '  Added the citation.  '])
            ->assertCreated()->assertJsonFragment(['who' => 'Ana Cruz', 'body' => 'Added the citation.']);

        $this->assertSame(1, DB::table('notifications')->where('expert_id', $expertId)->where('type', 'remark_reply')->count());

        // Both sides read the reply on its remark.
        $this->actingAs($student, 'web')->getJson(route('student.review.remarks', $reviewId))
            ->assertJsonPath('0.replies.0.body', 'Added the citation.');
        $expert = CapstoneExpert::find($expertId);
        $this->actingAs($expert, 'expert')->getJson(route('reviews.remarks.index', $reviewId))
            ->assertOk()->assertJsonPath('0.replies.0.who', 'Ana Cruz');

        // A remark of another review, a student outside the team, and an empty reply are refused.
        $this->actingAs($student, 'web')->postJson($url($reviewId, $foreignRemarkId), ['body' => 'x'])->assertNotFound();
        $this->actingAs($outsider, 'web')->postJson($url($reviewId, $remarkId), ['body' => 'x'])->assertForbidden();
        $this->actingAs($student, 'web')->postJson($url($reviewId, $remarkId), ['body' => '   '])->assertStatus(422);
        $this->assertSame(1, DB::table('pdf_remark_replies')->count());
    }
}
