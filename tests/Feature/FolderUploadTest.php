<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Resource;
use App\Models\Role;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

/**
 * A CaPSA can hand over a whole folder instead of one file at a time.
 *
 * A browser cannot send a folder - it sends what is inside one - so the only
 * things worth checking here are that every file becomes its own resource,
 * that each gets a title of its own since nobody typed one, and that the
 * sections chosen once apply to all of them.
 */
class FolderUploadTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections = ['BSIT-4A']): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => false,
            'sections' => $sections,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $expert;
    }

    /** Drive is not reachable from a test, so stand in for it. */
    private function fakeDrive(): void
    {
        $drive = Mockery::mock(GoogleDriveService::class);
        $drive->shouldReceive('uploadFile')->andReturnUsing(function () {
            static $n = 0;
            $n++;

            return [
                'id' => 'drive-file-'.$n,
                'name' => 'whatever',
                'web_view_link' => 'https://drive.example/'.$n,
                'web_content_link' => null,
            ];
        });

        $this->instance(GoogleDriveService::class, $drive);
    }

    public function test_every_file_in_the_folder_becomes_its_own_resource(): void
    {
        $this->fakeDrive();
        $capsa = $this->capsa(['BSIT-4A', 'BSIT-4B']);

        $response = $this->actingAs($capsa, 'expert')->post(route('gdrive.resources.store'), [
            'files' => [
                UploadedFile::fake()->create('Chapter_1-3 Template.docx', 12),
                UploadedFile::fake()->create('Title Defense Rubric.pdf', 8),
            ],
            'categories' => ['ch1-3', 'forms'],
            'sections' => ['BSIT-4A', 'BSIT-4B'],
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertCount(2, $response->json('resources'));
        $this->assertSame(2, Resource::where('uploaded_by', $capsa->expert_id)->count());

        // A folder holds several kinds of file at once, so each keeps the
        // category it was given rather than the folder having one.
        $this->assertSame('ch1-3', Resource::where('file_name', 'Chapter_1-3 Template.docx')->value('category'));
        $this->assertSame('forms', Resource::where('file_name', 'Title Defense Rubric.pdf')->value('category'));
    }

    public function test_each_file_is_titled_from_its_own_name(): void
    {
        $this->fakeDrive();
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')->post(route('gdrive.resources.store'), [
            'files' => [UploadedFile::fake()->create('Chapter_1-3 Template.docx', 12)],
            'category' => 'ch1-3',
            'sections' => ['BSIT-4A'],
        ])->assertOk();

        // Underscores and dashes are separators in a filename, not in a title.
        $this->assertSame('Chapter 1 3 Template', Resource::first()->title);
    }

    public function test_the_sections_chosen_once_apply_to_every_file(): void
    {
        $this->fakeDrive();
        $capsa = $this->capsa(['BSIT-4A', 'BSIT-4B']);

        $this->actingAs($capsa, 'expert')->post(route('gdrive.resources.store'), [
            'files' => [
                UploadedFile::fake()->create('One.pdf', 5),
                UploadedFile::fake()->create('Two.pdf', 5),
            ],
            'category' => 'forms',
            'sections' => ['BSIT-4B'],
        ])->assertOk();

        foreach (Resource::all() as $resource) {
            $this->assertSame(['BSIT-4B'], $resource->targetSections());
        }
    }

    public function test_a_folder_needs_no_title_but_a_single_file_still_does(): void
    {
        $this->fakeDrive();
        $capsa = $this->capsa();

        // No title, one file - rejected.
        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.resources.store'), [
            'file' => UploadedFile::fake()->create('One.pdf', 5),
            'category' => 'forms',
        ])->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_more_than_twenty_five_files_is_refused(): void
    {
        $this->fakeDrive();
        $capsa = $this->capsa();

        $files = [];
        for ($i = 0; $i < 26; $i++) {
            $files[] = UploadedFile::fake()->create("File{$i}.pdf", 1);
        }

        // The cap exists because this runs while the CaPSA waits.
        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.resources.store'), [
            'files' => $files,
            'category' => 'forms',
            'sections' => ['BSIT-4A'],
        ])->assertStatus(422)->assertJsonValidationErrors('files');
    }

    public function test_a_section_the_capsa_does_not_handle_is_refused(): void
    {
        $this->fakeDrive();
        $capsa = $this->capsa(['BSIT-4A']);

        $this->actingAs($capsa, 'expert')->postJson(route('gdrive.resources.store'), [
            'files' => [UploadedFile::fake()->create('One.pdf', 5)],
            'category' => 'forms',
            'sections' => ['BSIT-4B'],
        ])->assertStatus(422);

        $this->assertSame(0, Resource::count());
    }
}
