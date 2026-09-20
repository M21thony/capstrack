<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A team that has finished its capstone handing in the manuscript, and the
 * review that decides whether it becomes an archived capstone.
 */
class ManuscriptSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Student, 1: Team} */
    private function finishedTeam(): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th · A',
        ]);
        $team = Team::create([
            'team_name' => 'Charizard',
            'team_status' => 'Active',
            'is_confirmed' => true,
            'leader_id' => $leader->student_id,
        ]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        // The journey ends at the Final Oral Defense: a pass there is what
        // makes the manuscript submittable, so the fixture records one.
        DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Final Oral Defense',
            'attempt' => 1,
            'outcome' => DefenseOutcome::PASSED,
        ]);

        return [$leader, $team];
    }

    /** The archive decisions are made as a signed-in CaPSA. */
    private function actingAsCapsa(): void
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->makeCapsa($capsa);
        $this->actingAs($capsa, 'expert');
    }

    private function submitManuscript(Student $leader, array $overrides = [])
    {
        return $this->actingAs($leader, 'web')->post(route('student.archive.manuscript.submit'), array_merge([
            'title' => 'A Web-Based Tracker for Capstone Teams',
            'abstract' => 'We built a tracker. It tracks.',
            'file' => UploadedFile::fake()->create('manuscript.pdf', 500, 'application/pdf'),
        ], $overrides));
    }

    public function test_a_team_that_has_not_passed_the_final_oral_defense_cannot_submit(): void
    {
        [$leader, $team] = $this->finishedTeam();
        DefenseSchedule::where('team_id', $team->team_id)->delete();

        $this->submitManuscript($leader)->assertSessionHas('error');

        $this->assertSame(0, ArchivedDocument::count());
    }

    public function test_only_the_leader_can_submit(): void
    {
        [$leader, $team] = $this->finishedTeam();
        $member = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $this->submitManuscript($member)->assertSessionHas('error');

        $this->assertSame(0, ArchivedDocument::count());
    }

    public function test_a_finished_team_submits_its_manuscript_as_pending(): void
    {
        Storage::fake('public');
        [$leader, $team] = $this->finishedTeam();

        $this->submitManuscript($leader)->assertSessionHas('success');

        $row = ArchivedDocument::where('team_id', $team->team_id)->firstOrFail();
        $this->assertSame(ArchivedDocument::PENDING, $row->submission_status);
        $this->assertSame($leader->student_id, $row->submitted_by);
        $this->assertSame('A Web-Based Tracker for Capstone Teams', $row->title);
        $this->assertNotNull($row->file_path);

        Storage::disk('public')->assertExists($row->file_path);
    }

    public function test_a_pending_manuscript_does_not_sit_in_the_public_archive(): void
    {
        Storage::fake('public');
        [$leader, $team] = $this->finishedTeam();
        $this->submitManuscript($leader);

        $this->actingAs($leader, 'web')->get(route('student.archive'))
            ->assertOk()
            ->assertViewHas('archives', fn ($archives) => $archives->isEmpty());
    }

    public function test_a_team_cannot_submit_twice(): void
    {
        Storage::fake('public');
        [$leader] = $this->finishedTeam();

        $this->submitManuscript($leader)->assertSessionHas('success');
        $this->submitManuscript($leader)->assertSessionHas('error');

        $this->assertSame(1, ArchivedDocument::count());
    }

    public function test_the_file_has_to_be_a_pdf(): void
    {
        Storage::fake('public');
        [$leader] = $this->finishedTeam();

        $this->submitManuscript($leader, [
            'file' => UploadedFile::fake()->create('manuscript.docx', 500),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, ArchivedDocument::count());
    }

    public function test_the_admin_publishes_a_submitted_manuscript_into_the_archive(): void
    {
        Storage::fake('public');
        [$leader, $team] = $this->finishedTeam();
        $this->submitManuscript($leader);
        $row = ArchivedDocument::where('team_id', $team->team_id)->firstOrFail();
        $this->actingAsCapsa();

        $this->patchJson(route('api.admin.archive.submission.update', $row), ['decision' => 'published'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(ArchivedDocument::PUBLISHED, $row->fresh()->submission_status);
        $this->actingAs($leader, 'web')->get(route('student.archive'))
            ->assertOk()
            ->assertViewHas('archives', fn ($archives) => $archives->contains($row->archived_id));
    }

    public function test_declining_removes_the_submission_and_its_file(): void
    {
        Storage::fake('public');
        [$leader, $team] = $this->finishedTeam();
        $this->submitManuscript($leader);
        $row = ArchivedDocument::where('team_id', $team->team_id)->firstOrFail();
        $path = $row->file_path;
        $this->actingAsCapsa();

        $this->patchJson(route('api.admin.archive.submission.update', $row), ['decision' => 'declined'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull($row->fresh());
        Storage::disk('public')->assertMissing($path);
    }
}
