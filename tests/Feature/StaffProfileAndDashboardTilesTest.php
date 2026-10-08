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

        $stillIncluding = collect(File::files(resource_path('views/instructor')))
            ->filter(fn ($file) => str_contains($file->getContents(), "@include('instructor.partials.expert-profile')"))
            ->map(fn ($file) => $file->getFilename())
            ->values()
            ->all();

        $this->assertSame([], $stillIncluding);
    }

    /** A CaPSA who also advises keeps the drawer, and on a phone it rises from the bottom over everything. */
    public function test_on_a_phone_the_profile_panel_rises_from_the_bottom_over_everything(): void
    {
        $html = $this->actingAs($this->capsa(alsoAdviser: true), 'expert')->get(route('capsa.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#@media \(max-width:768px\)\{\s*\.ep-overlay\{z-index:1100!important\}\s*\.ep-overlay\.open\{background:rgba\(15,17,23,\.45\);pointer-events:all\}\s*\.ep-modal\{\s*left:0!important;right:0;top:auto!important;bottom:0;\s*width:100%!important#', $html);
        // The floating bar steps out of the way while it is open.
        $this->assertStringContainsString("'.modal-overlay, .upload-scrim, .ct-modal-overlay, .profile-overlay, .ep-overlay,", $html);
    }

    public function test_a_capsa_card_opens_the_capsa_profile_page(): void
    {
        $capsa = $this->capsa();

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="sidebar-user sidebar-user-link" href="'.preg_quote(route('capsa.profile'), '#').'" aria-label="My profile"\s*>#', $html);

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.profile'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="sidebar-user sidebar-user-link" href="'.preg_quote(route('capsa.profile'), '#').'" aria-label="My profile"\s+aria-current="page"\s*>#', $html);
    }

    public function test_a_capsa_who_also_advises_still_opens_the_expert_profile_panel(): void
    {
        $html = $this->actingAs($this->capsa(alsoAdviser: true), 'expert')->get(route('capsa.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="sidebar-user" id="ep-trigger" onclick="openExpertProfile()" style="cursor:pointer">', $html);
        $this->assertStringNotContainsString('sidebar-user-link', $html);
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
