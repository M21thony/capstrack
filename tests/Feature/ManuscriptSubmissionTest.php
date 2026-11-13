<?php

namespace Tests\Feature;

use App\Models\ArchivedDocTag;
use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\DuplicationCheck;
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

    /** The archive decisions are made as a signed-in CaPSA/TSA. */
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
        $this->assertSame('We built a tracker. It tracks.', $row->abstract);

        // Title, abstract and tags only - no PDF is uploaded.
        $this->assertNull($row->file_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** The team's approved proposal, carrying the title and tags the form fills in. */
    private function approvedProposal(Team $team, array $tags): CapstonePreProposal
    {
        $sy = StudentSchoolYear::where('team_id', $team->team_id)->firstOrFail();
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'checks/final.pdf', 'concepts' => ['title' => 'Final'],
            'top_score' => 20, 'verdict' => 'Low Similarity', 'matches' => [], 'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => 'Charizard: A Fire Safety Monitoring System', 'sdg_goals' => 'SDG 11',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile', 'tags' => $tags, 'approved_status' => true, 'submitted_at' => now(),
        ]);
    }

    public function test_the_leader_gets_a_submit_button_with_title_tags_and_school_year_filled_in(): void
    {
        [$leader, $team] = $this->finishedTeam();
        StudentSchoolYear::where('team_id', $team->team_id)->update(['school_year' => '2026-2027']);
        $this->approvedProposal($team, ['IoT', 'Fire Safety']);

        $this->actingAs($leader, 'web')->get(route('student.archive'))
            ->assertOk()
            ->assertSee('Submit to Archive')
            ->assertSee('id="manuscript-modal"', false)
            ->assertSee('value="Charizard: A Fire Safety Monitoring System"', false)
            ->assertSee('["IoT","Fire Safety"]', false)
            ->assertSee('S.Y. 2026–2027')
            ->assertSee(now()->format('F j, Y'));
    }

    public function test_a_team_that_has_not_finished_gets_no_submit_button(): void
    {
        [$leader, $team] = $this->finishedTeam();
        DefenseSchedule::where('team_id', $team->team_id)->delete();

        $this->actingAs($leader, 'web')->get(route('student.archive'))
            ->assertOk()
            ->assertDontSee('id="open-manuscript"', false)
            ->assertDontSee('id="manuscript-modal"', false);
    }

    public function test_the_tags_are_saved_and_new_ones_join_the_archive_categories(): void
    {
        Storage::fake('public');
        [$leader, $team] = $this->finishedTeam();
        // The archive's categories already hold the project tags, IoT among them.
        $existing = ArchivedDocTag::firstOrCreate(['tag_name' => 'IoT']);
        $before = ArchivedDocTag::count();

        $this->submitManuscript($leader, ['tags' => ['iot', 'Fire Safety', '  fire   safety ', '']])
            ->assertSessionHas('success');

        $row = ArchivedDocument::where('team_id', $team->team_id)->firstOrFail();

        // "iot" reuses the IoT category; "Fire Safety" is new, and kept once.
        $this->assertEqualsCanonicalizing(['IoT', 'Fire Safety'], $row->tags->pluck('tag_name')->all());
        $this->assertSame($before + 1, ArchivedDocTag::count());
        $this->assertTrue($row->tags->contains('tag_id', $existing->tag_id));
    }

    public function test_a_manuscript_can_be_submitted_without_tags(): void
    {
        Storage::fake('public');
        [$leader, $team] = $this->finishedTeam();

        $this->submitManuscript($leader)->assertSessionHas('success');

        $this->assertCount(0, ArchivedDocument::where('team_id', $team->team_id)->firstOrFail()->tags);
    }

    public function test_at_most_ten_tags(): void
    {
        Storage::fake('public');
        [$leader] = $this->finishedTeam();

        $this->submitManuscript($leader, ['tags' => array_map(fn ($i) => 'Tag '.$i, range(1, 11))])
            ->assertSessionHasErrors('tags');

        $this->assertSame(0, ArchivedDocument::count());
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

    /** The form has no file field any more; a file sent anyway is not stored. */
    public function test_no_pdf_is_needed_and_a_file_sent_anyway_is_ignored(): void
    {
        Storage::fake('public');
        [$leader, $team] = $this->finishedTeam();

        $this->submitManuscript($leader, [
            'file' => UploadedFile::fake()->create('manuscript.pdf', 500, 'application/pdf'),
        ])->assertSessionHas('success');

        $this->assertNull(ArchivedDocument::where('team_id', $team->team_id)->value('file_path'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_abstract_is_required(): void
    {
        [$leader] = $this->finishedTeam();

        $this->submitManuscript($leader, ['abstract' => ''])->assertSessionHasErrors('abstract');

        $this->assertSame(0, ArchivedDocument::count());
    }

    public function test_the_page_asks_for_no_pdf(): void
    {
        [$leader] = $this->finishedTeam();

        $this->actingAs($leader, 'web')->get(route('student.archive'))
            ->assertOk()
            ->assertDontSee('name="file"', false)
            ->assertDontSee('Manuscript (PDF)');
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

    public function test_declining_removes_the_submission(): void
    {
        [$leader, $team] = $this->finishedTeam();
        $this->submitManuscript($leader);
        $row = ArchivedDocument::where('team_id', $team->team_id)->firstOrFail();
        $this->actingAsCapsa();

        $this->patchJson(route('api.admin.archive.submission.update', $row), ['decision' => 'declined'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull($row->fresh());
    }

    /** A submission from before the PDF was dropped still takes its file with it. */
    public function test_declining_an_older_submission_removes_its_file(): void
    {
        Storage::fake('public');
        [$leader, $team] = $this->finishedTeam();
        Storage::disk('public')->put('archived-manuscripts/old.pdf', 'pdf');
        $row = ArchivedDocument::create([
            'team_id' => $team->team_id, 'title' => 'Older Submission', 'abstract' => 'An abstract.',
            'academic_year' => 2026, 'program' => 'BSIT', 'file_path' => 'archived-manuscripts/old.pdf',
            'submitted_by' => $leader->student_id, 'submitted_at' => now(),
            'submission_status' => ArchivedDocument::PENDING,
        ]);
        $this->actingAsCapsa();

        $this->patchJson(route('api.admin.archive.submission.update', $row), ['decision' => 'declined'])->assertOk();

        $this->assertNull($row->fresh());
        Storage::disk('public')->assertMissing('archived-manuscripts/old.pdf');
    }
}
