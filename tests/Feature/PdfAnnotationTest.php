<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfAnnotationTest extends TestCase
{
    use RefreshDatabase;

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

    private function loginAsApprovedExpert(): CapstoneExpert
    {
        $expert = CapstoneExpert::create([
            'email' => 'drive.expert@gmail.com',
            'first_name' => 'Drive',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => true,
        ]);

        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);
        $this->post('/expert/login/google/mock', [
            'state' => $state,
            'email' => 'drive.expert@gmail.com',
        ]);

        return $expert;
    }

    public function test_streams_pdf_from_google_drive_when_uploaded(): void
    {
        $expert = $this->loginAsApprovedExpert();

        \DB::table('google_drive_tokens')->insert([
            'user_id' => $expert->expert_id,
            'access_token' => json_encode(['access_token' => 'fake']),
            'refresh_token' => 'fake-refresh',
            'expires_at' => now()->addHour(),
        ]);

        $fileId = \DB::table('capstone_documents')->insertGetId([
            'id' => 1,
            'file_id' => 1,
            'file_path' => 'submissions/local-fallback.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'id' => 9,
            'review_id' => 9,
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => 'pending',
            'drive_file_id' => 'DRIVE_FILE_123',
            'drive_status' => 'uploaded',
        ]);

        $pdfBytes = '%PDF-1.4 fake-drive-content-marker';
        $mock = $this->createMock(GoogleDriveService::class);
        $mock->expects($this->once())
            ->method('downloadFile')
            ->with($expert->expert_id, 'DRIVE_FILE_123')
            ->willReturn($pdfBytes);
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->get("/reviews/{$reviewId}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('fake-drive-content-marker', $response->getContent());
    }

    public function test_falls_back_to_local_storage_when_no_drive_copy(): void
    {
        $expert = $this->loginAsApprovedExpert();

        Storage::disk('local')->put('submissions/local-only.pdf', '%PDF-1.4 local-content-marker');

        $fileId = \DB::table('capstone_documents')->insertGetId([
            'id' => 2,
            'file_id' => 2,
            'file_path' => 'submissions/local-only.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'id' => 10,
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
            'id' => 3,
            'file_id' => 3,
            'file_path' => 'submissions/remarks.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'id' => 11,
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
            'id' => 4,
            'file_id' => 4,
            'file_path' => 'submissions/remarks-selected.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'id' => 12,
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
            'id' => 5,
            'file_id' => 5,
            'file_path' => 'submissions/general.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'id' => 13,
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
            'id' => 6,
            'file_id' => 6,
            'file_path' => 'submissions/general-delete.pdf',
            'file_name' => 'doc.pdf',
        ]);

        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'id' => 14,
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
        return \DB::table('capstone_document_review')->insertGetId([
            'id' => $id,
            'review_id' => $id,
            'file_id' => 1,
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

        $response = $this->post("/reviews/{$reviewId}/status", ['review_status' => 'Reviewed']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'review_status' => 'Reviewed']);
        $this->assertDatabaseHas('capstone_document_review', ['review_id' => 21, 'review_status' => 'Reviewed']);
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
        $reviewId = $this->makeReview(23, 999, 'To Review');

        $response = $this->get("/reviews/{$reviewId}/status");

        $response->assertStatus(403);
    }

    public function test_syncs_annotated_pdf_to_drive(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = \DB::table('capstone_document_review')->insertGetId([
            'id' => 24,
            'review_id' => 24,
            'file_id' => 1,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => 'To Review',
            'drive_status' => 'uploaded',
            'drive_file_id' => 'DRIVE_SYNC_1',
        ]);

        $mock = $this->createMock(GoogleDriveService::class);
        $mock->expects($this->once())
            ->method('updateFile')
            ->with($expert->expert_id, 'DRIVE_SYNC_1', $this->isType('string'), 'application/pdf');
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->call(
            'POST',
            "/reviews/{$reviewId}/sync-drive",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/pdf'],
            '%PDF-1.4 annotated-bytes'
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_sync_drive_requires_a_linked_drive_file(): void
    {
        $expert = $this->loginAsApprovedExpert();
        $reviewId = $this->makeReview(25, $expert->expert_id, 'To Review');

        $response = $this->call(
            'POST',
            "/reviews/{$reviewId}/sync-drive",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/pdf'],
            '%PDF-1.4 bytes'
        );

        $response->assertStatus(422);
    }
}
