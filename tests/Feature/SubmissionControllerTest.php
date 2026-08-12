<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(array $overrides = []): int
    {
        return DB::table('capstone_experts')->insertGetId(array_merge([
            'email' => 'expert'.uniqid().'@gmail.com',
            'first_name' => 'Test',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => true,
            'created_at' => now(),
        ], $overrides));
    }

    private function makeRole(string $name): int
    {
        return DB::table('roles')->insertGetId([
            'role_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_submission_with_just_a_file_derives_title_from_filename_and_needs_no_form_fields(): void
    {
        Storage::fake('local');

        $team = Team::create([
            'team_name' => 'Team Alpha',
            'project_title' => 'Alpha Project',
            'team_status' => 'on-going',
        ]);

        $student = Student::factory()->withCompleteProfile()->create();

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
        ]);

        // RecipientResolver::resolveTeamRoleExpert matches against the
        // capitalized role_name values ('Adviser'/'Consultant'/'Grammarian').
        $adviserRoleId = $this->makeRole('Adviser');
        $adviserId = $this->makeExpert(['email' => 'adviser@gmail.com', 'first_name' => 'Ada', 'last_name' => 'Adviser']);
        DB::table('expert_roles')->insert(['expert_id' => $adviserId, 'role_id' => $adviserRoleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id,
            'expert_id' => $adviserId,
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('My Thesis Draft.pdf', 100, 'application/pdf');

        $response = $this->actingAs($student)->post(route('student.submission.store'), [
            'file' => $file,
            'recipients' => ['adviser'],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $document = CapstoneDocument::first();
        $this->assertNotNull($document);
        $this->assertSame('My Thesis Draft', $document->file_name);
        $this->assertNull($document->chapter);

        $review = CapstoneDocumentReview::first();
        $this->assertSame($adviserId, $review->expert_id);
        $this->assertSame('adviser', $review->role);
        // No Google Drive token connected for the adviser in this test, so
        // delivery should be gracefully skipped rather than erroring out.
        $this->assertSame('not_connected', $review->drive_status);
    }

    public function test_subject_adviser_recipient_resolves_via_matching_admin_department(): void
    {
        Storage::fake('local');

        $team = Team::create([
            'team_name' => 'Team Beta',
            'project_title' => 'Beta Project',
            'team_status' => 'on-going',
        ]);

        $student = Student::factory()->withCompleteProfile()->create();

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSIT',
        ]);

        $capstoneAdviserRoleId = $this->makeRole('capstone_adviser');
        $adminId = $this->makeExpert([
            'email' => 'admin@gmail.com',
            'first_name' => 'Capstone',
            'last_name' => 'Admin',
            'is_admin' => true,
            'department' => 'BSIT',
        ]);
        DB::table('expert_roles')->insert(['expert_id' => $adminId, 'role_id' => $capstoneAdviserRoleId]);

        $file = UploadedFile::fake()->create('report.docx', 50);

        $response = $this->actingAs($student)->post(route('student.submission.store'), [
            'file' => $file,
            'recipients' => ['subject_adviser'],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonFragment(['role' => 'subject_adviser']);

        $review = CapstoneDocumentReview::first();
        $this->assertNotNull($review, 'A review row should have been created once the subject adviser resolved.');
        $this->assertSame($adminId, $review->expert_id);
        $this->assertSame('not_connected', $review->drive_status);
    }

    public function test_subject_adviser_folders_endpoint_returns_empty_when_program_is_unset(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $response = $this->actingAs($student)->get(route('student.submission.subject-adviser-folders'));

        $response->assertOk();
        $response->assertJson(['folders' => []]);
    }
}
