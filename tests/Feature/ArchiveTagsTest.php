<?php

namespace Tests\Feature;

use App\Models\ArchivedDocTag;
use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\ArchiveTagGuesser;
use App\Support\DefenseAnalytics;
use App\Support\ProjectTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Archived capstones carry the same project tags a team picks on its
 * pre-proposal, so Analytics can count the archive by kind of project.
 */
class ArchiveTagsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCapsa(): void
    {
        $this->actingAs($this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true])), 'expert');
    }

    private function archived(?Team $team = null, string $status = ArchivedDocument::PUBLISHED): ArchivedDocument
    {
        return ArchivedDocument::create([
            'team_id' => $team?->team_id, 'title' => 'Crop Yield Tracker', 'academic_year' => '2025',
            'program' => 'BSIT', 'submission_status' => $status,
        ]);
    }

    public function test_the_archive_has_every_project_tag_to_pick_from(): void
    {
        $this->assertEqualsCanonicalizing(ProjectTags::OPTIONS, ArchivedDocTag::pluck('tag_name')->all());
    }

    public function test_a_capstones_tags_can_be_set_and_are_counted_in_analytics(): void
    {
        $archive = $this->archived();
        $this->actingAsCapsa();
        $ids = ArchivedDocTag::whereIn('tag_name', ['IoT', 'Web-based'])->pluck('tag_id')->all();

        $this->patchJson(route('api.admin.archive.tags.update', $archive), ['tag_ids' => $ids])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEqualsCanonicalizing(['IoT', 'Web-based'], $archive->tags()->pluck('tag_name')->all());

        $tags = (new \ReflectionMethod(DefenseAnalytics::class, 'archive'))->invoke(app(DefenseAnalytics::class))['tags'];
        $this->assertSame(1, $tags['IoT']);
        $this->assertSame(1, $tags['Web-based']);
    }

    /** Added by hand, with its tags, and no team or PDF behind it. */
    public function test_a_capstone_added_by_hand_is_saved_with_its_tags(): void
    {
        $this->actingAsCapsa();
        $ids = ArchivedDocTag::whereIn('tag_name', ['Mobile App'])->pluck('tag_id')->all();

        $this->postJson(route('api.admin.archive.store'), [
            'title' => 'Barangay Health Records', 'authors' => 'Dela Cruz, J.', 'academic_year' => '2024',
            'program' => 'BSIT', 'abstract' => null, 'tag_ids' => $ids,
        ])->assertCreated();

        $this->assertSame(['Mobile App'], ArchivedDocument::where('title', 'Barangay Health Records')->sole()->tags()->pluck('tag_name')->all());
    }

    /** Every capstone ends up with a tag: the library's list is tagged from its titles. */
    public function test_the_migration_leaves_no_capstone_untagged(): void
    {
        $iot = ArchivedDocument::create(['title' => 'RFID-BASED PARKING MANAGEMENT SYSTEM FOR CSPC', 'academic_year' => '2024', 'program' => 'BSIT']);
        $plain = ArchivedDocument::create(['title' => 'Strategic Plan for CSPC Leons Utilization', 'academic_year' => '2020', 'program' => 'BSIT']);

        (require base_path('database/migrations/2026_09_24_071729_fill_archive_tags_from_project_tags.php'))->up();

        $this->assertEqualsCanonicalizing(['IoT', 'Management System'], $iot->tags()->pluck('tag_name')->all());
        $this->assertSame([ArchiveTagGuesser::FALLBACK], $plain->tags()->pluck('tag_name')->all());
        $this->assertSame(0, ArchivedDocument::doesntHave('tags')->count());
    }

    public function test_the_guesser_reads_the_kind_of_project_from_the_words_used(): void
    {
        $this->assertSame(['Web-based', 'Management System'], ArchiveTagGuesser::guess('Web-Based Sangguniang Kabataan Management System'));
        $this->assertContains('Computer Vision', ArchiveTagGuesser::guess('Cacao Disease Detection using VGG16 Architecture'));
        $this->assertContains('AR / VR', ArchiveTagGuesser::guess('Augmented Reality Mobile Application for the Center for Rinconada Culture'));
        $this->assertLessThanOrEqual(ProjectTags::MAX, count(ArchiveTagGuesser::guess('AI chatbot mobile web-based IoT RFID blockchain GIS game e-commerce analytics')));
    }

    public function test_a_capstone_added_without_tags_gets_suggested_ones_and_tags_cannot_be_emptied(): void
    {
        $this->actingAsCapsa();

        $this->postJson(route('api.admin.archive.store'), ['title' => 'Online Marketplace for Bamboo Crafts', 'academic_year' => '2024', 'program' => 'BSIT'])
            ->assertCreated();
        $archive = ArchivedDocument::where('title', 'Online Marketplace for Bamboo Crafts')->sole();
        $this->assertContains('E-commerce', $archive->tags()->pluck('tag_name')->all());

        $this->patchJson(route('api.admin.archive.tags.update', $archive), ['tag_ids' => []])->assertStatus(422);
        $this->assertNotSame(0, $archive->tags()->count());
    }

    public function test_no_more_than_the_limit(): void
    {
        $archive = $this->archived();
        $this->actingAsCapsa();

        $this->patchJson(route('api.admin.archive.tags.update', $archive), [
            'tag_ids' => ArchivedDocTag::take(ProjectTags::MAX + 1)->pluck('tag_id')->all(),
        ])->assertStatus(422);

        $this->assertSame(0, $archive->tags()->count());
    }

    /** Capstones already in the archive from a team take its chosen title's tags when the migration runs. */
    public function test_the_migration_tags_capstones_already_handed_in(): void
    {
        [$team] = $this->teamWithChosenTitle(['Game Development']);
        $archive = $this->archived($team);
        $untouched = $this->archived();

        (require base_path('database/migrations/2026_09_24_071729_fill_archive_tags_from_project_tags.php'))->up();

        $this->assertSame(['Game Development'], $archive->tags()->pluck('tag_name')->all());
        // No team behind it: tagged from its title instead ("Crop Yield Tracker" is a tracking system).
        $this->assertSame(['Management System'], $untouched->tags()->pluck('tag_name')->all());
    }

    /** @return array{0: Team} */
    private function teamWithChosenTitle(array $tags): array
    {
        $leader = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Team '.uniqid(), 'team_status' => 'Active', 'leader_id' => $leader->student_id]);
        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'checks/1.pdf', 'concepts' => [],
            'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $chosen = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => 'Crop Yield Tracker',
            'sdg_goals' => 'SDG 2', 'research_agenda' => ['x'], 'problem_statement' => 'p', 'proposed_solution' => 's',
            'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile', 'tags' => $tags,
        ]);
        $team->update(['selected_pre_proposal_id' => $chosen->id]);

        return [$team];
    }

    public function test_publishing_a_teams_manuscript_takes_the_tags_of_its_chosen_title(): void
    {
        $leader = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Charizard', 'team_status' => 'Active', 'leader_id' => $leader->student_id]);
        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'checks/1.pdf', 'concepts' => [],
            'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $chosen = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => 'Crop Yield Tracker',
            'sdg_goals' => 'SDG 2', 'research_agenda' => ['x'], 'problem_statement' => 'p', 'proposed_solution' => 's',
            'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile', 'tags' => ['Mobile App', 'IoT'],
        ]);
        $team->update(['selected_pre_proposal_id' => $chosen->id]);
        $archive = $this->archived($team, ArchivedDocument::PENDING);
        $this->actingAsCapsa();

        $this->patchJson(route('api.admin.archive.submission.update', $archive), ['decision' => 'published'])->assertOk();

        $this->assertEqualsCanonicalizing(['Mobile App', 'IoT'], $archive->tags()->pluck('tag_name')->all());
    }
}
