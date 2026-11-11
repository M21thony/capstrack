<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Resource;
use App\Models\Role;
use App\Services\GoogleDriveService;
use Google\Service\Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Taking on files a CaPSA/TSA already has in Drive.
 *
 * Nothing is uploaded here, so what is worth checking is the bookkeeping: a
 * row per file pointing at where it lives, the section rules that stop a
 * CaPSA/TSA filing into someone else's section, and not importing the same file
 * twice into two rows students would see as two documents.
 */
class DriveImportTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections = ['BSIT-4A', 'BSIT-4B']): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => false,
            'sections' => $sections,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $expert;
    }

    /** @param  array<string, array{name: string, mime: string, size: ?int}>  $files */
    private function fakeDrive(array $files): void
    {
        $drive = Mockery::mock(GoogleDriveService::class);

        $drive->shouldReceive('importFile')->andReturnUsing(function ($userId, $fileId) use ($files) {
            $meta = $files[$fileId] ?? ['name' => 'Unknown.pdf', 'mime' => 'application/pdf', 'size' => 1];

            return [
                'id' => $fileId,
                'name' => $meta['name'],
                'mime_type' => $meta['mime'],
                'size' => $meta['size'],
                'web_view_link' => 'https://drive.example/'.$fileId,
            ];
        });

        $this->instance(GoogleDriveService::class, $drive);
    }

    public function test_a_picked_file_becomes_a_resource_pointing_at_drive(): void
    {
        $this->fakeDrive([
            'file-1' => ['name' => 'Chapter_1-3 Template.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => 24000],
        ]);
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), [
            'files' => [
                ['drive_file_id' => 'file-1', 'category' => 'ch1-3', 'sections' => ['BSIT-4A']],
            ],
        ])->assertOk()->assertJson(['success' => true, 'skipped' => 0]);

        $resource = Resource::first();

        $this->assertSame('Chapter 1 3 Template', $resource->title);
        $this->assertSame('file-1', $resource->drive_file_id);
        $this->assertSame('docx', $resource->file_type);
        $this->assertSame('https://drive.example/file-1', $resource->drive_web_view_link);
        // Nothing was copied, so there is no local path to record.
        $this->assertNull($resource->file_path);
        $this->assertSame(['BSIT-4A'], $resource->targetSections());
    }

    public function test_the_same_file_is_not_taken_on_twice(): void
    {
        $this->fakeDrive(['file-1' => ['name' => 'Rubric.pdf', 'mime' => 'application/pdf', 'size' => 900]]);
        $capsa = $this->capsa();

        $payload = ['files' => [['drive_file_id' => 'file-1', 'category' => 'forms', 'sections' => ['BSIT-4A']]]];

        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), $payload)->assertOk();

        $second = $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), $payload);

        $second->assertOk()->assertJson(['skipped' => 1]);
        $this->assertCount(0, $second->json('resources'));
        $this->assertSame(1, Resource::count());
    }

    public function test_a_native_google_doc_is_recorded_but_not_offered_as_a_download(): void
    {
        $this->fakeDrive([
            'doc-1' => ['name' => 'Defense Checklist', 'mime' => 'application/vnd.google-apps.document', 'size' => null],
        ]);
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), [
            'files' => [['drive_file_id' => 'doc-1', 'category' => 'forms', 'sections' => ['BSIT-4A']]],
        ])->assertOk();

        $resource = Resource::first();

        // No extension and no byte size on a native Doc - the link is all of it.
        $this->assertSame('gdoc', $resource->file_type);
        $this->assertNull($resource->file_size);
        $this->assertFalse((bool) $resource->is_downloadable);
    }

    public function test_a_section_the_capsa_does_not_handle_is_refused(): void
    {
        $this->fakeDrive(['file-1' => ['name' => 'One.pdf', 'mime' => 'application/pdf', 'size' => 10]]);
        $capsa = $this->capsa(['BSIT-4A']);

        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), [
            'files' => [['drive_file_id' => 'file-1', 'category' => 'forms', 'sections' => ['BSIT-4B']]],
        ])->assertStatus(422);

        $this->assertSame(0, Resource::count());
    }

    public function test_each_file_keeps_its_own_category_and_sections(): void
    {
        $this->fakeDrive([
            'a' => ['name' => 'Template.docx', 'mime' => 'application/pdf', 'size' => 10],
            'b' => ['name' => 'Rubric.pdf', 'mime' => 'application/pdf', 'size' => 10],
        ]);
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), [
            'files' => [
                ['drive_file_id' => 'a', 'category' => 'ch1-3', 'sections' => ['BSIT-4A']],
                ['drive_file_id' => 'b', 'category' => 'forms', 'sections' => ['BSIT-4B']],
            ],
        ])->assertOk();

        $this->assertSame(['BSIT-4A'], Resource::where('drive_file_id', 'a')->first()->targetSections());
        $this->assertSame('forms', Resource::where('drive_file_id', 'b')->first()->category);
    }

    public function test_a_folder_is_not_stored_as_a_resource(): void
    {
        // A folder in Drive is a file that holds others. Before this guard the
        // Picker handed back a folder id and the import wrote a row for it -
        // students got a resource that opened a folder listing.
        $this->fakeDrive([
            'folder-1' => ['name' => 'CLF Template', 'mime' => 'application/vnd.google-apps.folder', 'size' => null],
        ]);
        $capsa = $this->capsa();

        $response = $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), [
            'files' => [['drive_file_id' => 'folder-1', 'category' => 'forms', 'sections' => ['BSIT-4A']]],
        ]);

        $response->assertOk()->assertJson(['skipped' => 1]);
        $this->assertSame(0, Resource::count());
    }

    public function test_each_picked_file_keeps_the_category_it_was_given(): void
    {
        $this->fakeDrive([
            'a' => ['name' => 'Pre-Proposal Form.docx', 'mime' => 'application/pdf', 'size' => 10],
            'b' => ['name' => 'Chapter 1-3 Template.docx', 'mime' => 'application/pdf', 'size' => 10],
            'c' => ['name' => 'Defense Rubric.pdf', 'mime' => 'application/pdf', 'size' => 10],
        ]);
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), [
            'files' => [
                ['drive_file_id' => 'a', 'category' => 'pre-proposal', 'sections' => ['BSIT-4A']],
                ['drive_file_id' => 'b', 'category' => 'ch1-3', 'sections' => ['BSIT-4A']],
                ['drive_file_id' => 'c', 'category' => 'forms', 'sections' => ['BSIT-4A']],
            ],
        ])->assertOk();

        $this->assertSame('pre-proposal', Resource::where('drive_file_id', 'a')->value('category'));
        $this->assertSame('ch1-3', Resource::where('drive_file_id', 'b')->value('category'));
        $this->assertSame('forms', Resource::where('drive_file_id', 'c')->value('category'));
    }

    /** A file Google will not share is named in one plain line, and the rest of the batch still goes in. */
    public function test_a_file_google_refuses_does_not_stop_the_rest(): void
    {
        $drive = Mockery::mock(GoogleDriveService::class);
        $drive->shouldReceive('importFile')->andReturnUsing(function ($userId, $fileId) {
            if ($fileId === 'view-only') {
                throw new Exception('{"error":{"code":403}}', 403, null, [
                    ['message' => 'The user does not have sufficient permissions for this file.', 'reason' => 'insufficientFilePermissions'],
                ]);
            }

            return ['id' => $fileId, 'name' => 'Rubric.pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'web_view_link' => 'https://drive.example/'.$fileId];
        });
        $this->instance(GoogleDriveService::class, $drive);

        $response = $this->actingAs($this->capsa(), 'expert')->postJson(route('gdrive.imports.store'), [
            'files' => [
                ['drive_file_id' => 'view-only', 'category' => 'forms', 'sections' => ['BSIT-4A']],
                ['drive_file_id' => 'mine', 'category' => 'forms', 'sections' => ['BSIT-4A']],
            ],
        ]);

        $response->assertStatus(422)->assertJson([
            'success' => false,
            'message' => 'The user does not have sufficient permissions for this file.',
        ]);
        $this->assertSame(['mine'], Resource::pluck('drive_file_id')->all());
    }

    /** Removing takes it off CapsTrack only - the file in Drive is never touched. */
    public function test_removing_a_resource_leaves_the_drive_file_alone(): void
    {
        $drive = Mockery::mock(GoogleDriveService::class);
        $drive->shouldNotReceive('deleteFile');
        $this->instance(GoogleDriveService::class, $drive);

        $capsa = $this->capsa();
        $resource = Resource::create([
            'resource_type' => 'form', 'category' => 'forms', 'title' => 'Rubric', 'file_name' => 'Rubric.pdf',
            'content_type' => 'file', 'drive_file_id' => 'file-1', 'uploaded_by' => $capsa->expert_id,
        ]);
        $resource->sections()->create(['section' => 'BSIT-4A']);

        $this->actingAs($capsa, 'expert')
            ->deleteJson(route('gdrive.resources.destroy', $resource))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, Resource::count());
        $this->assertSame(0, $resource->sections()->count());
    }

    public function test_more_than_twenty_five_is_refused(): void
    {
        $this->fakeDrive([]);
        $capsa = $this->capsa();

        $files = [];
        for ($i = 0; $i < 26; $i++) {
            $files[] = ['drive_file_id' => "f{$i}", 'category' => 'forms', 'sections' => ['BSIT-4A']];
        }

        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.imports.store'), ['files' => $files])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');
    }
}
