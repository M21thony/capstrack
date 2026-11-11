<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\PortalIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
            ->assertSee('class="mtab is-floating"', false)
            ->assertSeeInOrder(['mtab-item', '>Home<', '>Team<', '>Submit<', '>Progress<', '>Menu<'], false)
            // Submit opens the drawer rather than leaving the page.
            ->assertSee('class="mtab-item primary" onclick="window.openSubmissionDrawer', false)
            ->getContent();

        // The page it is on is the one marked, and only that one.
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('student.progress'), '#').'" class="mtab-item on"\s+aria-current="page"#', $html);
        $this->assertSame(1, substr_count($html, 'class="mtab-item on"'));
    }

    /**
     * The student bar floats, as the Facebook app's does on an iPhone: a
     * pill resting above the bottom of the screen that gets out of the way
     * while the page is read downwards and comes back on the way up. Every
     * portal has it.
     */
    public function test_the_student_bar_floats_and_hides_while_reading_down(): void
    {
        $team = Team::create(['team_name' => 'Float Team', 'project_title' => 'Floats', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            // Five places, Home the one you are on: the highlight starts there.
            ->assertSee('<nav class="mtab is-floating" aria-label="Main" style="--n:5;--i:0">', false)
            ->assertSee('<span class="mtab-glide" aria-hidden="true"></span>', false)
            ->assertSee('transform:translateX(calc(var(--i, 0) * 100%));', false)
            // Glides once, on the tap; the page it opens does not play it again.
            ->assertSee("if (i === current || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button) return;\n        select(i);", false)
            ->assertDontSee('mtab-from', false)
            ->assertDontSee('is-arriving', false)
            ->assertSee('.mtab.is-floating .mtab-item.on .lb{opacity:1;transform:none}', false)
            ->assertSee('.mtab.is-floating .mtab-item.primary:active .mtab-ic svg{transform:rotate(90deg)}', false)
            ->assertSee('.mtab.is-floating.is-away,.mtab.is-floating.is-covered{', false)
            // Out of the way of an open pop-up, whose last button it covered.
            ->assertSee("bar.classList.toggle('is-covered', Array.prototype.some.call(document.querySelectorAll(SHELLS), isOpen));", false)
            ->assertSee('if (moved > 6) hide();', false)
            ->assertSee('else if (moved < -6) show();', false)
            ->assertSee("spacer.className = 'mtab-spacer';", false)
            // A small centred pill on a soft grey lip; the middle is a +, the
            // last tab the student's own picture (their initials without one).
            ->assertSee('.mtab.is-floating::before{left:6px;right:6px;top:6px;bottom:-4px;background:rgba(24,25,31,.1)}', false)
            ->assertSee('width:calc(100% - 32px);max-width:340px;margin:0 auto;', false)
            ->assertSee(PortalIcons::paths('add'), false)
            ->assertSee('<span class="mtab-ic mtab-avatar" aria-hidden="true">', false)
            ->assertSee('background:var(--accent-light,#faf7ed)!important;box-shadow:none;', false)
            ->assertSee($student->avatarInitials());

    }

    /** The staff portals float the same bar, with Menu as the person's own face or initials. */
    public function test_every_staff_portal_floats_the_same_bar(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'first_name' => 'Rhea', 'last_name' => 'Santos', 'profile_picture' => null]);

        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('<nav class="mtab is-floating" aria-label="Main" style="--n:4;--i:0">', false)
            ->assertSee('<span class="mtab-glide" aria-hidden="true"></span>', false)
            ->assertSee('<span class="mtab-ic mtab-avatar" aria-hidden="true">', false)
            ->assertSeeInOrder(['mtab-avatar', 'RS', '>Menu<'], false);

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A'], 'profile_picture' => 'https://example.com/me.jpg']));

        $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertSee('class="mtab is-floating"', false)
            ->assertSeeInOrder(['mtab-avatar', '<img src="https://example.com/me.jpg"'], false);

        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $this->actingAs($admin, 'expert')->get(route('admin.students'))
            ->assertOk()
            ->assertSee('<nav class="mtab is-floating" aria-label="Main" style="--n:5;--i:2">', false);
    }

    /**
     * On Financial Records (Team Management's second tab) the page is about a receipt. A big Submit in the
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

        $this->actingAs($student)->get(route('student.team', ['tab' => 'financial']))
            ->assertOk()
            ->assertSee('class="mtab is-floating"', false)
            ->assertSeeInOrder(['>Home<', '>Team<', '>Progress<', '>Menu<'], false)
            ->assertDontSee('>Submit<', false)
            ->assertDontSee('class="mtab-item primary"', false);

        // The Team tab of the same page keeps its Submit.
        $this->actingAs($student)->get(route('student.team'))
            ->assertSee('>Submit<', false);

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

        $this->actingAs($student)->get(route('student.experts'))->assertOk();

        // No fixed screen height to squeeze into: the page's columns wrap to
        // one and it scrolls, and the sessions list comes before the calendar.
        $css = File::get(resource_path('student-ui/Styles/experts.css'));
        $this->assertStringContainsString('.xh-split{display:flex;flex-wrap:wrap;', $css);
        $this->assertStringNotContainsString('100vh', substr($css, 0, strpos($css, '.preview-dialog{')));
        $this->assertStringContainsString('#xh-sessions{order:-1}', $css);
    }

    public function test_a_capsa_gets_their_own_pages_and_no_one_elses(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]));

        $page = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))->assertOk();

        $page->assertSee('class="mtab is-floating"', false)
            ->assertSeeInOrder(['>Home<', '>Teams<', '>Defenses<', '>Menu<'], false)
            ->assertSee('href="'.route('capsa.team_management').'" class="mtab-item', false)
            // Not an admin, so no Students tab.
            ->assertDontSee('<span class="lb">Students</span>', false);
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
            ->assertSee('class="mtab is-floating"', false)
            ->assertSeeInOrder(['>Home<', '>Experts<', '>Students<', '>Analytics<', '>Menu<'], false)
            ->assertSee('href="'.route('admin.dashboard').'" class="mtab-item', false)
            // Not a CaPSA/TSA, so no CaPSA/TSA-only pages in the bar.
            ->assertDontSee('>Defenses<', false)
            // The phone card layout for this page's list.
            ->assertSee('class="table std-table"', false);
    }

    public function test_an_expert_gets_home_teams_grading_and_menu(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->actingAs($expert, 'expert')->get(route('instructor.grading'))
            ->assertOk()
            ->assertSee('class="mtab is-floating"', false)
            ->assertSeeInOrder(['>Home<', '>Teams<', '>Grading<', '>Menu<'], false)
            // Reviewed papers live in Teams' To do now.
            ->assertDontSee('>Reviews<', false);
    }

    /** The bar is phone-only: hidden by default, shown under 768px. */
    public function test_the_bar_is_hidden_on_a_wide_screen(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $html = $this->actingAs($expert, 'expert')->get(route('instructor.grading'))->assertOk()->getContent();

        $this->assertStringContainsString('.mtab{display:none}', $html);
        $this->assertStringContainsString('@media (max-width:768px){', $html);
        $this->assertSame(1, substr_count($html, '<nav class="mtab'), 'one bar per page');

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
