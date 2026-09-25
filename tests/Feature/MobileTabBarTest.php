<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * On a phone each portal has a tab bar along the bottom: its most-used pages
 * and Menu. It is on every page through the portal's sidebar partial, and
 * only links to pages the signed-in account can open.
 */
class MobileTabBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_student_portal_has_home_team_submit_progress_and_menu(): void
    {
        $team = Team::create(['team_name' => 'Tab Team', 'project_title' => 'Tabs', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        $html = $this->actingAs($student)->get(route('student.progress'))
            ->assertOk()
            ->assertSee('class="mtab"', false)
            ->assertSeeInOrder(['mtab-item', '>Home<', '>Team<', '>Submit<', '>Progress<', '>Menu<'], false)
            // Submit opens the drawer rather than leaving the page.
            ->assertSee('class="mtab-item primary" onclick="window.openSubmissionDrawer', false)
            ->getContent();

        // The page it is on is the one marked, and only that one.
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('student.progress'), '#').'" class="mtab-item on"\s+aria-current="page"#', $html);
        $this->assertSame(1, substr_count($html, 'class="mtab-item on"'));
    }

    /**
     * On Financial Records the page is about a receipt. A big Submit in the
     * middle of the bar read as the way to upload one, and opened the
     * document form instead - so the bar there has no Submit.
     */
    public function test_financial_records_has_no_submit_document_button_in_the_bar(): void
    {
        $team = Team::create(['team_name' => 'Fee Team', 'project_title' => 'Fees', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        $this->actingAs($student)->get(route('student.financial'))
            ->assertOk()
            ->assertSee('class="mtab"', false)
            ->assertSeeInOrder(['>Home<', '>Team<', '>Progress<', '>Menu<'], false)
            ->assertDontSee('>Submit<', false)
            ->assertDontSee('class="mtab-item primary"', false);

        // Everywhere else it is still there.
        $this->actingAs($student)->get(route('student.progress'))
            ->assertOk()
            ->assertSee('class="mtab-item primary" onclick="window.openSubmissionDrawer', false);
    }

    /**
     * The Capstone Expert page is one column below 1024px; it has to grow with
     * its content there, or its sections overlap each other on a phone.
     */
    public function test_the_capstone_expert_page_grows_instead_of_overlapping_on_a_phone(): void
    {
        $team = Team::create(['team_name' => 'Expert Team', 'project_title' => 'Experts', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        $this->actingAs($student)->get(route('student.experts'))
            ->assertOk()
            ->assertSee('#real-content.content-grid { height: auto !important; min-height: 0 !important; }', false)
            ->assertSee('#real-content .overview-panel { grid-template-columns: minmax(0, 1fr) !important; }', false);
    }

    public function test_a_capsa_gets_their_own_pages_and_no_one_elses(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]));

        $page = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))->assertOk();

        $page->assertSee('class="mtab"', false)
            ->assertSeeInOrder(['>Home<', '>Teams<', '>Defenses<', '>Menu<'], false)
            ->assertSee('href="'.route('capsa.team_management').'" class="mtab-item', false)
            // Not an admin, so no Students tab.
            ->assertDontSee('>Students<', false);
    }

    /** Team Settings is a bottom sheet on a phone, with a dim that cancels it. */
    public function test_team_settings_has_a_backdrop_for_its_phone_sheet(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]));

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management'))
            ->assertOk()
            ->assertSee('<div class="set-scrim" id="setScrim" hidden></div>', false)
            ->assertSee("document.getElementById('setScrim').onclick", false);

        $css = file_get_contents(public_path('css/team-management.css'));
        $this->assertStringContainsString('.tmx .menu{position:fixed;left:0;right:0;bottom:0;', $css);
    }

    public function test_an_admin_gets_home_experts_students_analytics_and_menu(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $this->actingAs($admin, 'expert')->get(route('admin.students'))
            ->assertOk()
            ->assertSee('class="mtab"', false)
            ->assertSeeInOrder(['>Home<', '>Experts<', '>Students<', '>Analytics<', '>Menu<'], false)
            ->assertSee('href="'.route('admin.dashboard').'" class="mtab-item', false)
            // Not a CaPSA, so no CaPSA-only pages in the bar.
            ->assertDontSee('>Defenses<', false)
            // The phone card layout for this page's list.
            ->assertSee('class="table std-table"', false);
    }

    public function test_an_expert_gets_home_teams_reviews_grading_and_menu(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->actingAs($expert, 'expert')->get(route('instructor.grading'))
            ->assertOk()
            ->assertSee('class="mtab"', false)
            ->assertSeeInOrder(['>Home<', '>Teams<', '>Reviews<', '>Grading<', '>Menu<'], false);
    }

    /** The bar is phone-only: hidden by default, shown under 768px. */
    public function test_the_bar_is_hidden_on_a_wide_screen(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $html = $this->actingAs($expert, 'expert')->get(route('instructor.grading'))->assertOk()->getContent();

        $this->assertStringContainsString('.mtab{display:none}', $html);
        $this->assertStringContainsString('@media (max-width:768px){', $html);
        $this->assertSame(1, substr_count($html, 'class="mtab"'), 'one bar per page');

        // On a phone the top bar's ☰ gives way to Menu in the tab bar, but stays
        // in the page: Menu works by pressing it.
        $this->assertStringContainsString('.topbar .hamburger-btn{display:none!important}', $html);
        $this->assertStringContainsString('class="hamburger-btn"', $html);

        // The bell's panel becomes a phone-width sheet, with a dim behind it.
        $this->assertStringContainsString('.topbar .notif-dropdown{', $html);
        // Always down to the bottom of the screen, however short the list.
        $this->assertStringContainsString('bottom:calc(10px + env(safe-area-inset-bottom))!important;', $html);
        $this->assertStringContainsString("scrim.className = 'mnotif-scrim'", $html);
        $this->assertStringContainsString('id="notif-dropdown"', $html);

        // The developers' credit is added at the foot of every portal page.
        $this->assertStringContainsString('Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole', $html);
        $this->assertStringContainsString("credits.className = 'legal-credits sys-credits';", $html);
        // On a short page it is held at the bottom, not left under the last card,
        // and re-measured as the page loads and changes.
        $this->assertStringContainsString("credits.style.marginTop = (BASE + free) + 'px';", $html);
        // Measured in the box that is held to the screen's height, which on
        // the expert pages is not the page container itself.
        $this->assertStringContainsString('var box = holdingBox();', $html);
        $this->assertStringContainsString('new ResizeObserver(refit).observe(content);', $html);
        $this->assertStringContainsString('}).observe(main, { childList: true, subtree: true', $html);
    }

    /** The two expert status pages have no portal sidebar, so they carry it themselves. */
    public function test_the_expert_status_pages_carry_the_credits_too(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->actingAs($expert, 'expert')->get(route('instructor.pending'))
            ->assertOk()
            ->assertSee('System Developers')
            ->assertSee('Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole');
    }
}
