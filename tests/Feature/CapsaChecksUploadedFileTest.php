<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Before a signed paper or an approval counts, the CaPSA opens what the team
 * uploaded and looks for herself. Only the CaPSA whose section the team is in.
 */
class CapsaChecksUploadedFileTest extends TestCase
{
    use RefreshDatabase;

    private int $fileId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Team Alpha', 'team_status' => 'Active', 'leader_id' => $student->student_id, 'is_confirmed' => true]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        Storage::disk('local')->put('submissions/notice.jpg', 'jpeg-bytes');
        $this->fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $student->student_id, 'file_name' => 'notice', 'file_path' => 'submissions/notice.jpg',
            'document_status' => 'Pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function capsaFor(array $sections): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => $sections]);
        $this->makeCapsa($capsa);

        return $capsa;
    }

    public function test_the_teams_capsa_can_open_the_upload(): void
    {
        $this->actingAs($this->capsaFor(['BSIT-3A']), 'expert')
            ->get(route('capsa.documents.file', $this->fileId))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_a_capsa_of_another_section_cannot(): void
    {
        $this->actingAs($this->capsaFor(['BSIS-4B']), 'expert')
            ->get(route('capsa.documents.file', $this->fileId))
            ->assertForbidden();
    }

    public function test_a_file_missing_from_storage_is_not_found(): void
    {
        Storage::disk('local')->delete('submissions/notice.jpg');

        $this->actingAs($this->capsaFor(['BSIT-3A']), 'expert')
            ->get(route('capsa.documents.file', $this->fileId))
            ->assertNotFound();
    }
}
