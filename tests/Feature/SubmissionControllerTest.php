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
            'team_status' => 'Active',
        ]);

        $student = Student::factory()->withCompleteProfile()->create();

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
        ]);

        $adviserRoleId = $this->makeRole('adviser');
        $adviserId = $this->makeExpert(['email' => 'adviser@gmail.com', 'first_name' => 'Ada', 'last_name' => 'Adviser']);
        DB::table('expert_roles')->insert(['expert_id' => $adviserId, 'role_id' => $adviserRoleId]);
        $expertRoleId = DB::table('expert_roles')
            ->where('expert_id', $adviserId)->where('role_id', $adviserRoleId)
            ->value('expert_role_id');
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id,
            'expert_id' => $adviserId,
            // The role this team engaged them as, which is what the resolver
            // reads - not every role the person happens to hold.
            'expert_role_id' => $expertRoleId,
            'status' => 'approved',
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

    /**
     * A file for no checklist line is not the CaPSA's to read: what they want
     * to check, they add as a line. Even with a CaPSA for the section, asking
     * for them sends it to nobody.
     */
    public function test_a_file_for_no_line_is_not_sent_to_the_capsa(): void
    {
        Storage::fake('local');

        $team = Team::create([
            'team_name' => 'Team Beta',
            'project_title' => 'Beta Project',
            'team_status' => 'Active',
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
            // withCompleteProfile() puts the student in BSCS-4A.
            'sections' => json_encode(['BSCS-4A']),
        ]);
        DB::table('expert_roles')->insert(['expert_id' => $adminId, 'role_id' => $capstoneAdviserRoleId]);

        $file = UploadedFile::fake()->create('report.docx', 50);

        $response = $this->actingAs($student)->post(route('student.submission.store'), [
            'file' => $file,
            'recipients' => ['subject_adviser'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Pick at least one person to send this to.']);

        $this->assertSame(0, CapstoneDocumentReview::where('expert_id', $adminId)->count());
        $this->assertSame(0, CapstoneDocument::count());
    }

    public function test_subject_adviser_folders_endpoint_returns_empty_when_program_is_unset(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $response = $this->actingAs($student)->get(route('student.submission.subject-adviser-folders'));

        $response->assertOk();
        $response->assertJson(['folders' => []]);
    }

    public function test_version_only_increments_for_resubmissions_of_the_same_named_file(): void
    {
        Storage::fake('local');

        $team = Team::create([
            'team_name' => 'Team Gamma',
            'project_title' => 'Gamma Project',
            'team_status' => 'Active',
        ]);

        $student = Student::factory()->withCompleteProfile()->create();

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
        ]);

        $adviserRoleId = $this->makeRole('Adviser');
        $adviserId = $this->makeExpert(['email' => 'adviser2@gmail.com', 'first_name' => 'Ada', 'last_name' => 'Adviser']);
        DB::table('expert_roles')->insert(['expert_id' => $adviserId, 'role_id' => $adviserRoleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id,
            'expert_id' => $adviserId,
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $adviserId)->value('expert_role_id'),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submit = fn (string $fileName) => $this->actingAs($student)->post(route('student.submission.store'), [
            'file' => UploadedFile::fake()->create($fileName, 50, 'application/pdf'),
            'recipients' => ['adviser'],
        ]);

        // Neither name contains a "Chapter N" reference, so grouping falls
        // back to exact file name matching.
        $submit('My Report.pdf')->assertOk();
        $submit('My Report.pdf')->assertOk();

        // A differently-named file: unrelated document, starts at version 1
        // rather than continuing from My Report's version 2.
        $submit('Other File.pdf')->assertOk();

        $reportVersions = CapstoneDocument::where('file_name', 'My Report')->orderBy('file_version')->pluck('file_version')->all();
        $this->assertSame([1, 2], $reportVersions);
        $this->assertNull(CapstoneDocument::where('file_name', 'My Report')->first()->chapter);

        $otherFileVersion = CapstoneDocument::where('file_name', 'Other File')->value('file_version');
        $this->assertSame(1, $otherFileVersion);
    }

    public function test_version_persists_across_a_changing_date_suffix_when_chapter_is_detected(): void
    {
        Storage::fake('local');

        $team = Team::create([
            'team_name' => 'Team Epsilon',
            'project_title' => 'Epsilon Project',
            'team_status' => 'Active',
        ]);

        $student = Student::factory()->withCompleteProfile()->create();

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
        ]);

        $adviserRoleId = $this->makeRole('Adviser');
        $adviserId = $this->makeExpert(['email' => 'adviser4@gmail.com', 'first_name' => 'Ada', 'last_name' => 'Adviser']);
        DB::table('expert_roles')->insert(['expert_id' => $adviserId, 'role_id' => $adviserRoleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id,
            'expert_id' => $adviserId,
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $adviserId)->value('expert_role_id'),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submit = fn (string $fileName) => $this->actingAs($student)->post(route('student.submission.store'), [
            'file' => UploadedFile::fake()->create($fileName, 50, 'application/pdf'),
            'recipients' => ['adviser'],
        ]);

        // Different file names, but both reference "Chapter 1" - the date
        // suffix changing between them shouldn't reset the version count.
        $submit('Chapter1_2026-08-12.pdf')->assertOk();
        $submit('Ch_1_2026-08-13.pdf')->assertOk();

        // A different chapter reference: separate document, own version 1.
        $submit('Chapter2_2026-08-12.pdf')->assertOk();

        $chapterOneVersions = CapstoneDocument::where('chapter', 'Chapter 1')->orderBy('file_version')->pluck('file_version')->all();
        $this->assertSame([1, 2], $chapterOneVersions);

        $chapterTwoVersion = CapstoneDocument::where('chapter', 'Chapter 2')->value('file_version');
        $this->assertSame(1, $chapterTwoVersion);
    }

    public function test_next_version_endpoint_reflects_matching_filename_or_detected_chapter(): void
    {
        Storage::fake('local');

        $team = Team::create([
            'team_name' => 'Team Delta',
            'project_title' => 'Delta Project',
            'team_status' => 'Active',
        ]);

        $student = Student::factory()->withCompleteProfile()->create();

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
        ]);

        $adviserRoleId = $this->makeRole('Adviser');
        $adviserId = $this->makeExpert(['email' => 'adviser3@gmail.com', 'first_name' => 'Ada', 'last_name' => 'Adviser']);
        DB::table('expert_roles')->insert(['expert_id' => $adviserId, 'role_id' => $adviserRoleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id,
            'expert_id' => $adviserId,
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $adviserId)->value('expert_role_id'),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student)->post(route('student.submission.store'), [
            'file' => UploadedFile::fake()->create('Final Draft.pdf', 50, 'application/pdf'),
            'recipients' => ['adviser'],
        ])->assertOk();

        $this->actingAs($student)
            ->get(route('student.submission.next-version', ['title' => 'Final Draft']))
            ->assertOk()
            ->assertJson(['version' => 2, 'chapter' => null]);

        $this->actingAs($student)
            ->get(route('student.submission.next-version', ['title' => 'Something Else']))
            ->assertOk()
            ->assertJson(['version' => 1, 'chapter' => null]);

        $this->actingAs($student)->post(route('student.submission.store'), [
            'file' => UploadedFile::fake()->create('Chapter3_2026-01-01.pdf', 50, 'application/pdf'),
            'recipients' => ['adviser'],
        ])->assertOk();

        // A differently-dated file for the same detected chapter should
        // preview as version 2, not version 1.
        $this->actingAs($student)
            ->get(route('student.submission.next-version', ['title' => 'Chapter3_2026-02-14']))
            ->assertOk()
            ->assertJson(['version' => 2, 'chapter' => 'Chapter 3']);
    }
}
