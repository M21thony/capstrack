<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\PdfRemark;
use App\Models\Student;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfAnnotationTest extends TestCase
{
    use RefreshDatabase;

    private ?Student $documentOwner = null;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('capstone_documents', function ($table) {
            if (! Schema::hasColumn('capstone_documents', 'file_id')) {
                $table->unsignedBigInteger('file_id')->nullable();
            }
            if (! Schema::hasColumn('capstone_documents', 'file_path')) {
                $table->string('file_path')->nullable();
            }
            if (! Schema::hasColumn('capstone_documents', 'file_name')) {
                $table->string('file_name')->nullable();
            }
        });

        Schema::table('capstone_document_review', function ($table) {
            if (! Schema::hasColumn('capstone_document_review', 'review_id')) {
                $table->string('review_id')->nullable();
            }
            if (! Schema::hasColumn('capstone_document_review', 'file_id')) {
                $table->unsignedBigInteger('file_id')->nullable();
            }
            if (! Schema::hasColumn('capstone_document_review', 'expert_id')) {
                $table->unsignedBigInteger('expert_id')->nullable();
            }
            foreach (['role', 'review_status', 'drive_file_id', 'drive_status'] as $col) {
                if (! Schema::hasColumn('capstone_document_review', $col)) {
                    $table->string($col)->nullable();
                }
            }
        });

        if (! Schema::hasTable('google_drive_tokens')) {
            Schema::create('google_drive_tokens', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * A real student to own the fixture documents - capstone_documents
     * requires one, and these tests only care that the document belongs to
     * somebody, not to whom.
     */
    private function documentOwnerId(): int
    {
        if ($this->documentOwner === null) {
            $this->documentOwner = Student::create([
                'email' => 'doc.owner@my.cspc.edu.ph',
                'f_name' => 'Doc',
                'l_name' => 'Owner',
            ]);
        }

        return $this->documentOwner->student_id;
    }

    private function loginAsApprovedExpert(): CapstoneExpert
    {
        $expert = CapstoneExpert::create([
            'email' => 'drive.expert@gmail.com',
            'first_name' => 'Drive',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => true,
        ]);

        // Authenticate directly on the expert guard rather than replaying the
        // Google OAuth mock - these tests are about the annotation endpoints,
        // and the mock's route path had drifted, silently logging nobody in
        // and turning every assertion into a redirect to /login.
        $this->actingAs($expert, 'expert');

        return $expert;
    }

    // The annotator must always be handed the clean local PDF, never the
    // Google Drive copy - that copy is a flattened export produced by
    // the old Drive sync for viewing elsewhere, and reading it back would burn the
    // annotations into the page pixels and then draw the overlay on top of
    // them again on every save. This test used to assert the opposite (that
    // an uploaded Drive copy is streamed), which is the behaviour the
    // controller deliberately dropped.
    public function test_always_serves_the_local_copy_even_when_a_drive_copy_exists(): void
    {
        $expert = $this->loginAsApprovedExpert();

        \DB::table('google_drive_tokens')->insert([
            'user_id' => $expert->expert_id,
            'access_token' => json_encode(['access_token' => 'fake']),
            'refresh_token' => 'fake-refresh',
            'expires_at' => now()->addHour(),
        ]);

        Storage::disk('local')->put('submissions/clean-copy.pdf', '%PDF-1.4 local-content-marker');

        $fileId = \DB::table('capstone_documents')->insertGetId([
            'file_id' => 1,
            'student_id' => $this->documentOwnerId(),
            'file_path' => 'submissions/clean-copy.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'review_id' => 9,
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => 'To Review',
            'drive_file_id' => 'DRIVE_FILE_123',
            'drive_status' => 'uploaded',
        ]);

        // Drive must not be touched at all, however healthy the copy looks.
        $mock = $this->createMock(GoogleDriveService::class);
        $mock->expects($this->never())->method('downloadFile');
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->get("/reviews/{$reviewId}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('local-content-marker', $response->streamedContent());
    }

    public function test_falls_back_to_local_storage_when_no_drive_copy(): void
    {
        $expert = $this->loginAsApprovedExpert();

        Storage::disk('local')->put('submissions/local-only.pdf', '%PDF-1.4 local-content-marker');

        $fileId = \DB::table('capstone_documents')->insertGetId([
            'file_id' => 2,
            'student_id' => $this->documentOwnerId(),
            'file_path' => 'submissions/local-only.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'review_id' => 10,
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => 'pending',
            'drive_status' => 'failed',
        ]);

        $response = $this->get("/reviews/{$reviewId}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_returns_remarks_as_a_json_array(): void
    {
        $expert = $this->loginAsApprovedExpert();

        $fileId = \DB::table('capstone_documents')->insertGetId([
            'file_id' => 3,
            'student_id' => $this->documentOwnerId(),
            'file_path' => 'submissions/remarks.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'review_id' => 11,
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => 'pending',
            'drive_status' => 'failed',
        ]);

        $response = $this->get("/reviews/{$reviewId}/remarks");

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    public function test_stores_selected_text_with_remarks(): void
    {
        $expert = $this->loginAsApprovedExpert();

        $fileId = \DB::table('capstone_documents')->insertGetId([
            'file_id' => 4,
            'student_id' => $this->documentOwnerId(),
            'file_path' => 'submissions/remarks-selected.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'review_id' => 12,
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => 'pending',
            'drive_status' => 'failed',
        ]);

        $payload = [
            'comments' => [
                [
                    'page_number' => 1,
                    'text' => 'Fix this grammar.',
                    'selectedText' => 'The students are working hard.',
                    'color' => 'yellow',
                    'highlightRects' => [
                        [
                            'top_position' => 0.1,
                            'left_position' => 0.2,
                            'highlight_width' => 0.5,
                            'highlight_height' => 0.05,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson("/reviews/{$reviewId}/remarks", $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('pdf_remarks', [
            'review_id' => $reviewId,
            'comment' => 'Fix this grammar.',
            'selected_text' => 'The students are working hard.',
        ]);
    }

    public function test_stores_and_lists_general_comments(): void
    {
        $expert = $this->loginAsApprovedExpert();

        $fileId = \DB::table('capstone_documents')->insertGetId([
            'file_id' => 5,
            'student_id' => $this->documentOwnerId(),
            'file_path' => 'submissions/general.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'review_id' => 13,
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => 'pending',
            'drive_status' => 'failed',
        ]);

        $response = $this->postJson("/reviews/{$reviewId}/general-remarks", [
            'comment' => 'Overall the paper needs a stronger conclusion.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('pdf_remarks', [
            'review_id' => $reviewId,
            'comment' => 'Overall the paper needs a stronger conclusion.',
            'is_general' => true,
        ]);

        $index = $this->getJson("/reviews/{$reviewId}/general-remarks");
        $index->assertStatus(200);
        $index->assertJsonCount(1);
    }

    public function test_deletes_a_general_comment(): void
    {
        $expert = $this->loginAsApprovedExpert();

        $fileId = \DB::table('capstone_documents')->insertGetId([
            'file_id' => 6,
            'student_id' => $this->documentOwnerId(),
            'file_path' => 'submissions/general-delete.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'review_id' => 14,
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => 'pending',
            'drive_status' => 'failed',
        ]);

        $remarkId = \DB::table('pdf_remarks')->insertGetId([
            'review_id' => $reviewId,
            'is_general' => true,
            'page_number' => 0,
            'comment' => 'Please clarify the methodology.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/reviews/{$reviewId}/general-remarks/{$remarkId}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('pdf_remarks', ['id' => $remarkId]);
    }

    private function makeReview(int $id, int $expertId, string $status): int
    {
        // A review points at a real document - hardcoding file_id 1 with no
        // matching row left this failing the foreign key.
        $fileId = \DB::table('capstone_documents')->insertGetId([
            'file_id' => $id,
            'student_id' => $this->documentOwnerId(),
            'file_path' => 'submissions/review-'.$id.'.pdf',
            'file_name' => 'doc.pdf',
        ]);

        return \DB::table('capstone_document_review')->insertGetId([
            'review_id' => $id,
            'file_id' => $fileId,
            'expert_id' => $expertId,
            'role' => 'adviser',
            'review_status' => $status,
            'drive_status' => 'failed',
        ]);
    }

    public function test_can_update_status_to_reviewing(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(20, $expert->expert_id, 'To Review');

        $response = $this->post("/reviews/{$reviewId}/status", ['review_status' => 'Reviewing']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'review_status' => 'Reviewing']);
        $this->assertDatabaseHas('capstone_document_review', ['review_id' => 20, 'review_status' => 'Reviewing']);
    }

    public function test_can_update_status_to_reviewed(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(21, $expert->expert_id, 'To Review');

        $response = $this->post("/reviews/{$reviewId}/status", [
            'review_status' => 'Reviewed',
            'verdict' => CapstoneDocumentReview::APPROVED,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'review_status' => 'Reviewed']);
        $this->assertDatabaseHas('capstone_document_review', ['review_id' => 21, 'review_status' => 'Reviewed']);
    }

    /**
     * reviewed_at was only ever read, never written, so every row held null -
     * including the ones marked Reviewed. Two orderings sorted on nothing and
     * the dashboard's "Oldest:" label rendered blank.
     */
    public function test_finishing_a_review_stamps_when_it_was_finished(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(23, $expert->expert_id, 'To Review');

        $this->assertNull(CapstoneDocumentReview::find($reviewId)->reviewed_at);

        $this->post("/reviews/{$reviewId}/status", [
            'review_status' => 'Reviewed',
            'verdict' => CapstoneDocumentReview::APPROVED,
        ])->assertStatus(200);

        $this->assertNotNull(CapstoneDocumentReview::find($reviewId)->reviewed_at);
    }

    /** Undoing it must not leave a finish time behind on an unfinished review. */
    public function test_moving_a_review_back_clears_the_finish_time(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(24, $expert->expert_id, 'To Review');

        $this->post("/reviews/{$reviewId}/status", [
            'review_status' => 'Reviewed',
            'verdict' => CapstoneDocumentReview::APPROVED,
        ]);
        $this->assertNotNull(CapstoneDocumentReview::find($reviewId)->reviewed_at);

        $this->post("/reviews/{$reviewId}/status", ['review_status' => 'Reviewing']);
        $this->assertNull(CapstoneDocumentReview::find($reviewId)->reviewed_at);
    }

    /**
     * The scopes have to match what the screen actually writes. Four queries
     * filtered on 'pending' and 'approved' - values nothing has ever written -
     * so they could never match a row.
     */
    public function test_the_scopes_match_the_statuses_the_screen_writes(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $this->makeReview(25, $expert->expert_id, CapstoneDocumentReview::AWAITING);
        $this->makeReview(26, $expert->expert_id, CapstoneDocumentReview::IN_PROGRESS);
        $this->makeReview(27, $expert->expert_id, CapstoneDocumentReview::REVIEWED);

        $this->assertSame(2, CapstoneDocumentReview::awaitingReview()->count());
        $this->assertSame(1, CapstoneDocumentReview::reviewed()->count());

        // The values that used to be filtered on match nothing, which is the bug.
        $this->assertSame(0, CapstoneDocumentReview::where('review_status', 'pending')->count());
        $this->assertSame(0, CapstoneDocumentReview::where('review_status', 'approved')->count());
    }

    /**
     * The point of the whole change: a reviewer cannot finish without saying
     * what they decided. Before this, "Reviewed" meant they stopped reading,
     * and whether they were happy went into a remark nothing could query.
     */
    public function test_a_review_cannot_be_turned_in_without_a_verdict(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(30, $expert->expert_id, 'Reviewing');

        $this->post("/reviews/{$reviewId}/status", ['review_status' => 'Reviewed'], [
            'Accept' => 'application/json',
        ])->assertStatus(422);

        // and nothing moved
        $review = CapstoneDocumentReview::find($reviewId);
        $this->assertSame('Reviewing', $review->review_status);
        $this->assertNull($review->verdict);
    }

    public function test_a_made_up_verdict_is_refused(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(31, $expert->expert_id, 'Reviewing');

        $this->post("/reviews/{$reviewId}/status", [
            'review_status' => 'Reviewed',
            'verdict' => 'looks_fine_i_guess',
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertNull(CapstoneDocumentReview::find($reviewId)->verdict);
    }

    public function test_turning_in_records_the_verdict_and_when(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(32, $expert->expert_id, 'Reviewing');

        $this->post("/reviews/{$reviewId}/status", [
            'review_status' => 'Reviewed',
            'verdict' => CapstoneDocumentReview::NEEDS_REVISION,
        ])->assertStatus(200)->assertJson(['verdict' => 'needs_revision']);

        $review = CapstoneDocumentReview::find($reviewId);
        $this->assertSame(CapstoneDocumentReview::NEEDS_REVISION, $review->verdict);
        $this->assertNotNull($review->verdict_at);
    }

    /** Reopening a review withdraws the decision rather than leaving it standing. */
    public function test_reopening_a_review_clears_the_verdict(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(33, $expert->expert_id, 'Reviewing');

        $this->post("/reviews/{$reviewId}/status", [
            'review_status' => 'Reviewed',
            'verdict' => CapstoneDocumentReview::APPROVED,
        ]);
        $this->assertSame(CapstoneDocumentReview::APPROVED, CapstoneDocumentReview::find($reviewId)->verdict);

        $this->post("/reviews/{$reviewId}/status", ['review_status' => 'Reviewing']);

        $review = CapstoneDocumentReview::find($reviewId);
        $this->assertNull($review->verdict);
        $this->assertNull($review->verdict_at);
    }

    /**
     * The checklist wants Chapter 1 approved by the adviser AND the
     * consultant, as two separate lines. role is already on the review, so
     * the two are told apart without a join.
     */
    public function test_adviser_and_consultant_approvals_are_told_apart(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $adviserReview = $this->makeReview(34, $expert->expert_id, 'Reviewing');
        $consultantReview = $this->makeReview(35, $expert->expert_id, 'Reviewing');

        CapstoneDocumentReview::whereKey($adviserReview)->update(['role' => 'adviser']);
        CapstoneDocumentReview::whereKey($consultantReview)->update(['role' => 'consultant']);

        $this->post("/reviews/{$adviserReview}/status", [
            'review_status' => 'Reviewed',
            'verdict' => CapstoneDocumentReview::APPROVED,
        ]);

        $this->assertSame(1, CapstoneDocumentReview::approvedAs('adviser')->count());
        $this->assertSame(0, CapstoneDocumentReview::approvedAs('consultant')->count());

        $this->post("/reviews/{$consultantReview}/status", [
            'review_status' => 'Reviewed',
            'verdict' => CapstoneDocumentReview::APPROVED,
        ]);

        $this->assertSame(1, CapstoneDocumentReview::approvedAs('consultant')->count());
    }

    public function test_rejects_invalid_status_value(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(22, $expert->expert_id, 'To Review');

        $response = $this->post("/reviews/{$reviewId}/status", ['review_status' => 'Bogus'], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    public function test_blocks_access_to_another_experts_review(): void
    {
        $this->loginAsApprovedExpert();

        $otherExpert = CapstoneExpert::create([
            'email' => 'other.expert@gmail.com',
            'first_name' => 'Other',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => true,
        ]);

        $reviewId = $this->makeReview(23, $otherExpert->expert_id, 'To Review');

        $response = $this->get("/reviews/{$reviewId}/status");

        $response->assertStatus(403);
    }

    public function test_stores_a_box_remark_with_every_box_and_its_figure(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(30, $expert->expert_id, 'Reviewing');

        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [[
            'type' => 'box',
            'page_number' => 2,
            'text' => 'Foreign key is not shown.',
            'color' => 'pink',
            'figure_label' => 'Figure 3.2',
            'diagram_type' => 'ERD',
            'highlightRects' => [
                ['top_position' => 0.2, 'left_position' => 0.1, 'highlight_width' => 0.3, 'highlight_height' => 0.1],
                ['top_position' => 0.4, 'left_position' => 0.5, 'highlight_width' => 0.2, 'highlight_height' => 0.1],
            ],
        ]]])->assertOk();

        $remark = PdfRemark::where('review_id', $reviewId)->sole();
        $this->assertSame('box', $remark->mark_type);
        $this->assertSame('Figure 3.2', $remark->figure_label);
        $this->assertSame('ERD', $remark->diagram_type);
        // Both boxes come back as two boxes...
        $this->assertCount(2, $remark->rects);
        // ...and the four position columns hold the box around both of them.
        $this->assertEqualsWithDelta(0.2, (float) $remark->top_position, 1e-6);
        $this->assertEqualsWithDelta(0.1, (float) $remark->left_position, 1e-6);
        $this->assertEqualsWithDelta(0.6, (float) $remark->highlight_width, 1e-6);
        $this->assertEqualsWithDelta(0.3, (float) $remark->highlight_height, 1e-6);

        $this->getJson("/reviews/{$reviewId}/remarks")
            ->assertOk()
            ->assertJsonPath('0.mark_type', 'box')
            ->assertJsonPath('0.figure_label', 'Figure 3.2')
            ->assertJsonCount(2, '0.rects');
    }

    public function test_stores_a_pin_as_a_point(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(31, $expert->expert_id, 'Reviewing');

        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [[
            'type' => 'pin',
            'page_number' => 1,
            'text' => 'Label this data flow.',
            'color' => 'yellow',
            'figure_label' => 'Figure 3.1',
            'diagram_type' => 'DFD',
            'highlightRects' => [],
            'x_position' => 0.35,
            'y_position' => 0.62,
        ]]])->assertOk();

        $remark = PdfRemark::where('review_id', $reviewId)->sole();
        $this->assertSame('pin', $remark->mark_type);
        $this->assertEqualsWithDelta(0.35, (float) $remark->left_position, 1e-6);
        $this->assertEqualsWithDelta(0.62, (float) $remark->top_position, 1e-6);
        $this->assertEqualsWithDelta(0.0, (float) $remark->highlight_width, 1e-6);
        $this->assertNull($remark->rects);
    }

    public function test_stores_a_shape_remark_with_its_cells_and_boxes(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(35, $expert->expert_id, 'Reviewing');

        $shape = [
            'file' => 'erd.drawio',
            'page' => 'Page-1',
            'cells' => [
                ['id' => 'login', 'kind' => 'vertex', 'text' => 'Validate login', 'role' => 'Process'],
                ['id' => 'e7', 'kind' => 'edge', 'from' => 'User', 'to' => 'Validate login'],
            ],
        ];

        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [[
            'type' => 'shape',
            'page_number' => 4,
            'text' => 'Name the data on this flow.',
            'figure_label' => 'Box: Validate login',
            'shape_ref' => $shape,
            'highlightRects' => [
                ['top_position' => 0.3, 'left_position' => 0.4, 'highlight_width' => 0.2, 'highlight_height' => 0.1],
                ['top_position' => 0.2, 'left_position' => 0.1, 'highlight_width' => 0.3, 'highlight_height' => 0.15],
            ],
        ]]])->assertOk();

        $remark = PdfRemark::where('review_id', $reviewId)->sole();
        $this->assertSame('shape', $remark->mark_type);
        $this->assertSame($shape, $remark->shape_ref);
        // Each cell's box is kept, so everything that draws a box still draws it.
        $this->assertCount(2, $remark->rects);
        $this->assertEqualsWithDelta(0.2, (float) $remark->top_position, 1e-6);
        $this->assertEqualsWithDelta(0.5, (float) $remark->highlight_width, 1e-6);

        $this->getJson("/reviews/{$reviewId}/remarks")
            ->assertOk()
            ->assertJsonPath('0.mark_type', 'shape')
            ->assertJsonPath('0.shape_ref.cells.1.to', 'Validate login');
    }

    public function test_a_shape_remark_needs_a_cell_and_other_marks_keep_no_shape(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(36, $expert->expert_id, 'Reviewing');

        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [[
            'type' => 'shape', 'page_number' => 1, 'text' => 'x',
            'shape_ref' => ['file' => 'a.drawio', 'cells' => [['id' => 'c1', 'kind' => 'circle']]],
        ]]])->assertStatus(422)->assertJsonValidationErrors('comments.0.shape_ref.cells.0.kind');

        // A box sent with a shape_ref by mistake does not keep it.
        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [[
            'type' => 'box', 'page_number' => 1, 'text' => 'x',
            'shape_ref' => ['cells' => [['id' => 'c1', 'kind' => 'vertex']]],
            'highlightRects' => [['top_position' => 0.1, 'left_position' => 0.1, 'highlight_width' => 0.2, 'highlight_height' => 0.2]],
        ]]])->assertOk();

        $this->assertNull(PdfRemark::where('review_id', $reviewId)->sole()->shape_ref);
    }

    public function test_a_text_remark_keeps_no_figure_number(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(32, $expert->expert_id, 'Reviewing');

        // A remark sent without a type is a text remark, as every remark was before.
        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [[
            'page_number' => 1,
            'text' => 'Reword this.',
            'selectedText' => 'the words',
            'figure_label' => 'Figure 9',
            'highlightRects' => [
                ['top_position' => 0.1, 'left_position' => 0.1, 'highlight_width' => 0.4, 'highlight_height' => 0.02],
                ['top_position' => 0.12, 'left_position' => 0.3, 'highlight_width' => 0.1, 'highlight_height' => 0.02],
            ],
        ]]])->assertOk();

        $remark = PdfRemark::where('review_id', $reviewId)->sole();
        $this->assertSame('text', $remark->mark_type);
        $this->assertNull($remark->figure_label);
        $this->assertSame('the words', $remark->selected_text);
        // Each line's highlight is kept, not one block around both lines.
        $this->assertCount(2, $remark->rects);
    }

    public function test_refuses_an_unknown_mark_or_diagram_type(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(33, $expert->expert_id, 'Reviewing');

        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [[
            'type' => 'circle', 'page_number' => 1, 'text' => 'x',
        ]]])->assertStatus(422)->assertJsonValidationErrors('comments.0.type');

        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [[
            'type' => 'box', 'page_number' => 1, 'text' => 'x', 'diagram_type' => 'Venn',
        ]]])->assertStatus(422)->assertJsonValidationErrors('comments.0.diagram_type');
    }

    public function test_saves_the_figures_the_reviewer_checked(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(34, $expert->expert_id, 'Reviewing');

        $this->getJson("/reviews/{$reviewId}/status")->assertJsonPath('figures_checked', []);

        $this->postJson("/reviews/{$reviewId}/figures-checked", ['figures' => ['Figure 3.1', 'Figure 3.4', 'Figure 3.1']])
            ->assertOk()
            ->assertJsonPath('figures_checked', ['Figure 3.1', 'Figure 3.4']);

        $this->getJson("/reviews/{$reviewId}/status")->assertJsonPath('figures_checked', ['Figure 3.1', 'Figure 3.4']);
    }

    public function test_cannot_check_figures_on_another_experts_review(): void
    {
        $this->loginAsApprovedExpert();

        $otherExpert = CapstoneExpert::create([
            'email' => 'figures.other@gmail.com',
            'first_name' => 'Other',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => true,
        ]);
        $reviewId = $this->makeReview(35, $otherExpert->expert_id, 'Reviewing');

        $this->postJson("/reviews/{$reviewId}/figures-checked", ['figures' => ['Figure 1']])->assertForbidden();
        $this->assertNull(CapstoneDocumentReview::find($reviewId)->figures_checked);
    }

    public function test_the_clf_says_which_figure_a_remark_is_about(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(36, $expert->expert_id, 'Reviewed');

        $this->postJson("/reviews/{$reviewId}/remarks", ['comments' => [
            [
                'type' => 'box', 'page_number' => 2, 'text' => 'Foreign key is not shown.', 'section' => '3.3 Data Design',
                'figure_label' => 'Figure 3.2', 'diagram_type' => 'ERD',
                'highlightRects' => [['top_position' => 0.2, 'left_position' => 0.1, 'highlight_width' => 0.3, 'highlight_height' => 0.1]],
            ],
            [
                'page_number' => 1, 'text' => 'Name the Agile framework.', 'selectedText' => 'Agile model',
                'highlightRects' => [['top_position' => 0.1, 'left_position' => 0.1, 'highlight_width' => 0.2, 'highlight_height' => 0.02]],
            ],
        ]])->assertOk();

        $grouped = PdfRemark::groupedForReview($reviewId);
        $html = view('instructor.pdf.consultation-log', [
            'projectTitle' => 'BarangayConnect',
            'alias' => 'Team BarangayConnect',
            'proponents' => collect(['Juan Dela Cruz']),
            'chapter' => 'Chapter 3',
            'consultationDate' => now(),
            'deadline' => null,
            'approvedAt' => null,
            'remarksBySection' => $grouped['bySection'],
            'remarks' => $grouped['remarks'],
            'generalRemarks' => $grouped['general'],
        ])->render();

        // Just the figure number in front - no brackets, nothing highlighted.
        $this->assertStringContainsString('• Figure 3.2: Foreign key is not shown.', $html);
        // A remark on words prints as it always has.
        $this->assertStringContainsString('• Name the Agile framework.', $html);
        $this->assertStringNotContainsString('Figure 3.2: Name', $html);
    }
}
