<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The dashboard tiles and the profile on the staff portals: a tile that is a
 * link goes where it says, the expert's tiles are counts only, and the
 * profile opens whole on a phone from every page.
 */
class StaffProfileAndDashboardTilesTest extends TestCase
{
    use RefreshDatabase;

    private function adviser(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => 4]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    private function capsa(bool $alsoAdviser = false): CapstoneExpert
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create([
            'approved_status' => true,
            'sections' => ['BSIT-4B'],
        ]));

        if ($alsoAdviser) {
            $capsa->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);
        }

        return $capsa->fresh();
    }

    public function test_the_expert_dashboard_tiles_are_counts_not_links(): void
    {
        $html = $this->actingAs($this->adviser(), 'expert')->get(route('instructor.dashboard'))->assertOk()->getContent();

        // Four summary tiles, then the three that are waiting on the expert. All counts.
        $this->assertSame(7, preg_match_all('#<div\s+class="stat-chip has-icon">#', $html));
        $this->assertDoesNotMatchRegularExpression('#<a\s[^>]*class="stat-chip#', $html);
        foreach (['Teams You Advise', 'Documents Reviewed', 'Consultations Held', 'Defenses Graded', 'Team Requests', 'Documents to Review', 'Grades to Submit'] as $label) {
            $this->assertStringContainsString('<div class="stat-l">'.$label.'</div>', $html);
        }
    }

    /**
     * Built as one escaped string, the address came out as href=&quot;...&quot;
     * and every tile opened ".../%22http://...%22", a page that is not there.
     */
    public function test_a_tile_that_is_a_link_carries_a_real_address(): void
    {
        $html = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href=&quot;', $html);
        $this->assertMatchesRegularExpression('#<a\s+href="'.preg_quote(route('capsa.team_management'), '#').'"\s+class="stat-chip#', $html);
        $this->assertMatchesRegularExpression('#<a\s+href="'.preg_quote(route('capsa.financial'), '#').'"\s+class="stat-chip#', $html);
    }

    public function test_the_section_field_upper_cases_as_it_is_typed(): void
    {
        $student = Student::factory()->create(['email' => 'new.student@my.cspc.edu.ph']);

        $html = $this->actingAs($student)->get(route('student.personal-info'))->assertOk()->getContent();

        $this->assertStringNotContainsString('oninput=&quot;', $html);
        $this->assertStringContainsString('oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, \'\')"', $html);
    }

    /** The profile is a page of its own now, not a 300px drawer beside the sidebar. */
    public function test_the_expert_card_opens_the_profile_page(): void
    {
        $adviser = $this->adviser();
        $link = '#<a class="sidebar-user" id="ep-trigger" href="'.preg_quote(route('instructor.profile'), '#').'" aria-label="My profile" style="color:inherit;text-decoration:none"';

        $html = $this->actingAs($adviser, 'expert')->get(route('instructor.progress'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression($link.'\s*>#', $html);
        $this->assertStringNotContainsString('id="ep-overlay"', $html);

        $html = $this->actingAs($adviser, 'expert')->get(route('instructor.profile'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression($link.'\s+aria-current="page"\s*>#', $html);

        $this->assertFileDoesNotExist(resource_path('views/instructor/partials/expert-profile.blade.php'));
        $stillIncluding = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_contains($file->getContents(), "@include('instructor.partials.expert-profile')"))
            ->map(fn ($file) => $file->getFilename())
            ->values()
            ->all();

        $this->assertSame([], $stillIncluding);
    }

    /** A CaPSA who also advises opens the same expert profile page, inside their own portal. */
    public function test_a_capsa_who_also_advises_opens_the_expert_profile_page(): void
    {
        $capsa = $this->capsa(alsoAdviser: true);

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="sidebar-user sidebar-user-link" href="'.preg_quote(route('instructor.profile'), '#').'" aria-label="My profile"\s+id="ep-trigger"\s*>#', $html);
        $this->assertStringNotContainsString('id="ep-overlay"', $html);

        // Their name and sections stay on the CaPSA profile, one link away.
        $this->actingAs($capsa, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('Team slots')
            ->assertSee('<a class="pp-id-edit" href="'.route('capsa.profile').'">', false);
    }

    public function test_a_capsa_card_opens_the_capsa_profile_page(): void
    {
        $capsa = $this->capsa();

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="sidebar-user sidebar-user-link" href="'.preg_quote(route('capsa.profile'), '#').'" aria-label="My profile"\s*>#', $html);

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.profile'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="sidebar-user sidebar-user-link" href="'.preg_quote(route('capsa.profile'), '#').'" aria-label="My profile"\s+aria-current="page"\s*>#', $html);
    }

    /** Over every page's own dimming layer, which Grading Rubrics keeps at 999. */
    public function test_the_tablet_menu_sits_over_the_pages_own_dimming_layer(): void
    {
        foreach (['admin.css', 'expert.css', 'schedule.css'] as $sheet) {
            $css = File::get(public_path('css/'.$sheet));

            $this->assertMatchesRegularExpression('#@media \(min-width:769px\) and \(max-width:1024px\)\{.*?\.sidebar\{display:flex;position:fixed;left:0;top:0;height:100vh;height:100dvh;z-index:1000;#s', $css, $sheet);
        }
    }
}
