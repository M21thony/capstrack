<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
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

    public function test_the_expert_dashboard_tiles_open_the_page_behind_them(): void
    {
        $html = $this->actingAs($this->adviser(), 'expert')->get(route('instructor.dashboard'))->assertOk()->getContent();

        // Four tiles, each opening the page behind it.
        $this->assertSame(4, preg_match_all('#<a\s+class="db-tile #', $html));
        foreach (['Assigned Teams', 'Pending Reviews', 'Defenses Graded', 'Upcoming Consultations'] as $label) {
            $this->assertStringContainsString('<span class="lab">'.$label.'</span>', $html);
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
        $this->assertStringContainsString('class="db-tile tile-gold" href="'.route('capsa.team_management', ['tab' => 'teams']).'"', $html);
        $this->assertStringContainsString('class="db-tile tile-slate" href="'.route('capsa.sections').'"', $html);
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

    /** A CaPSA/TSA who also advises opens the same expert profile page, inside their own portal. */
    public function test_a_capsa_who_also_advises_opens_the_expert_profile_page(): void
    {
        $capsa = $this->capsa(alsoAdviser: true);

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="sidebar-user sidebar-user-link" href="'.preg_quote(route('instructor.profile'), '#').'" aria-label="My profile"\s+id="ep-trigger"\s*>#', $html);
        $this->assertStringNotContainsString('id="ep-overlay"', $html);

        // One profile: their name and sections are on it too, and the old
        // CaPSA/TSA profile sends them there.
        $this->actingAs($capsa, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('Team capacity')
            ->assertSee('id="pp-name-btn"', false)
            ->assertSeeInOrder(['CaPSA/TSA of', 'BSIT-4B'])
            ->assertSee('Roles and sections are set by the administrator')
            ->assertDontSee('id="pp-folders-h"', false);
        $this->actingAs($capsa, 'expert')->get(route('capsa.profile'))->assertRedirect(route('instructor.profile'));
    }

    /** A CaPSA/TSA who advises edits their own name on the one profile; an ordinary expert only their middle name. */
    public function test_only_a_capsa_or_admin_edits_their_name_on_the_profile(): void
    {
        $capsa = $this->capsa(alsoAdviser: true);
        $capsa->update(['program_slots' => '2 - BSIT', 'max_teams' => 2]);
        $payload = fn (string $first) => [
            'program_slots' => ['BSIT' => 2], 'expertise_by_side' => ['adviser' => [], 'panel' => []],
            'name' => ['first_name' => $first, 'middle_name' => null, 'last_name' => 'Balilla', 'suffix' => 'Jr.'],
        ];

        $this->actingAs($capsa, 'expert')->putJson(route('instructor.profile.update'), $payload('Mark'))->assertOk();
        $this->assertSame(['Mark', 'Balilla', 'Jr.'], [$capsa->fresh()->first_name, $capsa->fresh()->last_name, $capsa->fresh()->suffix]);

        $this->actingAs($capsa, 'expert')->putJson(route('instructor.profile.update'), $payload(''))->assertStatus(422);

        $adviser = $this->adviser();
        $name = [$adviser->first_name, $adviser->last_name];
        $this->actingAs($adviser, 'expert')->putJson(route('instructor.profile.update'), $payload('Someone'))->assertOk();
        $this->assertSame($name, [$adviser->fresh()->first_name, $adviser->fresh()->last_name]);

        $this->actingAs($adviser, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('id="pp-last" autocomplete="family-name" maxlength="50" readonly', false)
            ->assertDontSee('CaPSA/TSA of')
            ->assertDontSee('id="pp-folders-h"', false);
    }

    public function test_a_capsa_card_opens_the_capsa_profile_page(): void
    {
        $capsa = $this->capsa();

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="sidebar-user sidebar-user-link" href="'.preg_quote(route('capsa.profile'), '#').'" aria-label="My profile"\s*>#', $html);

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.profile'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="sidebar-user sidebar-user-link" href="'.preg_quote(route('capsa.profile'), '#').'" aria-label="My profile"\s+aria-current="page"\s*>#', $html);
    }

    public function test_a_plain_admin_has_no_profile_page(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $this->assertFalse(Route::has('admin.profile'));

        $html = $this->actingAs($admin, 'expert')->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('sidebar-user-link', $html);
        $this->assertStringNotContainsString('Choose what you get notified about', $html);
        $this->assertStringContainsString('<a href="'.route('admin.activitylog').'">Recent activity</a>', $html);
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
