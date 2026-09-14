<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every expert page still renders.
 *
 * The four pages below are being redesigned together, and a redesign breaks
 * a page in one of two ways: a Blade error, or a template that renders but
 * has lost the control the page existed for. The first is what this catches,
 * cheaply, on every page at once - so a change to one of them cannot quietly
 * take out the other three.
 */
class ExpertPagesStillRenderTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $expert;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false,
        ]);
        $this->expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);
    }

    public static function pages(): array
    {
        return [
            'dashboard' => ['/instructor/dashboard'],
            'teams' => ['/instructor/teams'],
            'document review' => ['/instructor/docreview'],
            'assignments' => ['/instructor/assignments'],
            'schedule' => ['/instructor/schedule'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_page_renders(string $url): void
    {
        $this->actingAs($this->expert, 'expert')->get($url)->assertOk();
    }

    /** Milestone progress is the CaPSA's tracker, read-only, and Team Overview links to it. */
    public function test_milestone_progress_renders_and_is_linked_from_team_overview(): void
    {
        $this->actingAs($this->expert, 'expert')
            ->get('/instructor/progress')
            ->assertOk()
            ->assertSee('id="ms-tracker"', false);

        $this->actingAs($this->expert, 'expert')
            ->get('/instructor/teams')
            ->assertSee(route('instructor.progress'), false);
    }

    /** And the team's own numbers arrived on the page that took it over. */
    public function test_team_overview_carries_the_progress_numbers(): void
    {
        $html = $this->actingAs($this->expert, 'expert')->get('/instructor/teams')->content();

        // The tiles became one summary line, so these are its words now.
        $this->assertStringContainsString('average completion', $html);
        $this->assertStringContainsString('overdue', $html);
        $this->assertStringContainsString('teams-summary', $html);
    }

    /** The controls the redesign must not lose. */
    public function test_document_review_keeps_its_actions(): void
    {
        $html = $this->actingAs($this->expert, 'expert')->get('/instructor/docreview')->content();

        $this->assertStringContainsString('Search documents or teams', $html);
        $this->assertStringContainsString('Waiting on you', $html);
        $this->assertStringContainsString('Over 5 days', $html);
    }
}
