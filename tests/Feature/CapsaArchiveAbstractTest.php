<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CaPSA/TSA's archive opens any capstone's full abstract in a modal, with
 * its title, proponents, program, school year and date.
 */
class CapsaArchiveAbstractTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function capstone(array $attributes): ArchivedDocument
    {
        $team = Team::create(['team_name' => 'Team '.uniqid(), 'team_status' => 'Active']);

        return ArchivedDocument::create($attributes + [
            'team_id' => $team->team_id, 'program' => 'BSIT', 'file_path' => 'archives/manuscript.pdf',
        ]);
    }

    public function test_each_capstone_can_open_its_full_abstract_with_school_year_and_date(): void
    {
        $study = $this->capstone([
            'title' => 'Smart Parking Finder', 'authors' => 'Juan Dela Cruz, Maria Santos',
            'abstract' => "First paragraph of the study.\n\nSecond paragraph that goes well past the card preview.",
            'academic_year' => 2025,
            'submission_status' => ArchivedDocument::PUBLISHED, 'submitted_at' => '2026-03-15 09:00:00',
        ]);

        $response = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.archive'))->assertOk();

        $response->assertSee('id="abstract-modal"', false)
            ->assertSee('openAbstract('.$study->archived_id.')', false)
            ->assertSee('S.Y. 2025–2026');

        // The whole abstract and its details reach the page, not a shortened preview.
        $response->assertSee('Second paragraph that goes well past the card preview.')
            ->assertSee('March 15, 2026')
            ->assertSee('Juan Dela Cruz, Maria Santos');
    }

    public function test_a_pending_manuscript_can_be_read_before_it_is_published(): void
    {
        $submission = $this->capstone([
            'title' => 'Barangay Health Records', 'abstract' => 'A records system for barangay health workers.',
            'academic_year' => 2026,
            'submission_status' => ArchivedDocument::PENDING, 'submitted_at' => now(),
        ]);

        $this->actingAs($this->capsa(), 'expert')->get(route('capsa.archive'))
            ->assertOk()
            ->assertSee('openAbstract('.$submission->archived_id.')', false)
            ->assertSee('S.Y. 2026–2027');
    }

    public function test_the_study_count_matches_the_published_capstones(): void
    {
        foreach (['First Study', 'Second Study'] as $title) {
            $this->capstone(['title' => $title, 'academic_year' => 2025, 'submission_status' => ArchivedDocument::PUBLISHED]);
        }
        $this->capstone(['title' => 'Still Pending', 'academic_year' => 2025, 'submission_status' => ArchivedDocument::PENDING, 'submitted_at' => now()]);

        $this->actingAs($this->capsa(), 'expert')->get(route('capsa.archive'))
            ->assertOk()
            ->assertSee('Showing 2 studies')
            ->assertDontSee('Showing 8 studies');
    }

    /** The abstract is whatever a team typed: it reaches the page as data, never as markup. */
    public function test_an_abstract_is_never_rendered_as_html(): void
    {
        $this->capstone([
            'title' => 'Escaping Study', 'abstract' => '<script>alert(1)</script> and more text',
            'academic_year' => 2026, 'submission_status' => ArchivedDocument::PUBLISHED,
        ]);

        $this->actingAs($this->capsa(), 'expert')->get(route('capsa.archive'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
