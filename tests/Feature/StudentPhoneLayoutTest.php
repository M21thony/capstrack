<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The student portal on a phone: every page fits whatever phone it is on,
 * and a field being typed into stays above the on-screen keyboard with its
 * label. How it behaves in a browser is held by
 * tests/e2e/student-phone-forms.spec.js; these hold the pieces it rests on.
 */
class StudentPhoneLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        $team = Team::create(['team_name' => 'Phone Team', 'project_title' => 'Phones', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        return $student;
    }

    /** Android Chrome shrinks the page for its keyboard only when a page asks it to. */
    public function test_every_student_page_asks_the_page_to_shrink_for_the_keyboard(): void
    {
        $pages = collect(File::allFiles(resource_path('views/student')))
            ->filter(fn ($file) => str_contains($file->getContents(), 'name="viewport"'));

        $this->assertGreaterThan(10, $pages->count());

        foreach ($pages as $page) {
            $this->assertStringContainsString(
                'interactive-widget=resizes-content',
                $page->getContents(),
                $page->getRelativePathname().' does not ask the page to shrink for the keyboard.'
            );
        }
    }

    public function test_student_pages_carry_the_keyboard_handling(): void
    {
        $student = $this->student();

        foreach (['student.dashboard', 'student.team', 'student.experts', 'student.consultation'] as $page) {
            $html = $this->actingAs($student)->get(route($page))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'html.kb-open .mtab{display:none!important}'), $page.' should hide the tab bar while typing, once.');
            $this->assertStringContainsString('function popUpAround(el)', $html);
            $this->assertStringContainsString("vv.addEventListener('resize', soon);", $html);
        }
    }

    /**
     * Older phone browsers (Chrome before 108, Samsung Internet before 21,
     * iOS before 15.4) drop a whole declaration they do not understand. A
     * lone 100dvh then left a pop-up with no height limit at all, running off
     * the bottom of the screen with its buttons out of reach.
     */
    public function test_every_dvh_height_on_the_student_side_has_a_vh_fallback_first(): void
    {
        $files = [
            resource_path('student-ui/Styles/team.css'),
            resource_path('views/student/partials/join-request-popup.blade.php'),
            resource_path('views/student/partials/sidebar.blade.php'),
            resource_path('views/student/partials/notification-dropdown.blade.php'),
            resource_path('views/student/archive.blade.php'),
            resource_path('views/partials/mobile-tabbar.blade.php'),
            public_path('css/submission-drawer.css'),
        ];

        foreach ($files as $file) {
            preg_match_all('/([a-z-]+)(\s*:\s*)([^;{}]*\d(?:\.\d+)?dvh[^;{}]*)/', File::get($file), $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            $source = File::get($file);

            foreach ($matches as $match) {
                [$declaration, $offset] = $match[0];
                $fallback = $match[1][0].$match[2][0].preg_replace('/(\d(?:\.\d+)?)dvh/', '$1vh', $match[3][0]);
                $before = substr($source, max(0, $offset - strlen($fallback) - 4), strlen($fallback) + 4);

                $this->assertStringContainsString($fallback, $before, basename($file).": \"{$declaration}\" has no vh fallback before it.");
            }
        }
    }

    /**
     * A phone's bottom sheet used to stay 24px lower than it should: an
     * !important translate outranked the slide-up animation meant to undo it,
     * so the buttons at the bottom of the recruitment post and the leave
     * request sat off the screen.
     */
    public function test_team_pop_ups_are_not_pushed_below_the_screen_on_a_phone(): void
    {
        $css = File::get(resource_path('student-ui/Styles/team.css'));

        $this->assertDoesNotMatchRegularExpression('/\.modal-overlay>\.card\{[^}]*transform:translateY\(\d+px\)!important/', $css);
    }

    /** On a phone the recruitment post showed both Short Introduction boxes at once. */
    public function test_the_recruitment_post_shows_only_the_intro_for_the_chosen_goal_on_a_phone(): void
    {
        $css = File::get(resource_path('student-ui/Styles/team.css'));

        $this->assertStringNotContainsString('.modal-card--recruitment #individual-fields{display:flex!important', $css);
        $this->assertStringContainsString('.modal-card--recruitment #individual-fields:not([style*="none"]){display:flex!important', $css);
    }

    public function test_typing_on_an_iphone_does_not_zoom_the_page_and_textareas_use_the_page_font(): void
    {
        $html = $this->actingAs($this->student())->get(route('student.team'))->assertOk()->getContent();

        $this->assertStringContainsString('textarea,input,select,button{font-family:inherit}', $html);
        $this->assertStringContainsString('@supports (-webkit-touch-callout:none)', $html);
        $this->assertStringContainsString('textarea,select{font-size:16px!important}', $html);
    }

    /**
     * The upload sheet came up on an iPhone as a header, a sliver and its
     * buttons: WebKit measured its flex:1 body (a 0 basis) as 0 tall inside a
     * pop-up sized by its content. The body's basis is its content now.
     */
    public function test_the_upload_sheet_is_as_tall_as_what_it_holds_on_an_iphone(): void
    {
        $css = File::get(public_path('css/submission-drawer.css'));

        $this->assertStringContainsString(".up-body{\n  flex:1 1 auto;min-height:0;overflow-y:auto;", $css);
        $this->assertStringContainsString('.up-grab,.up-head,.up-steps,.up-foot{flex-shrink:0}', $css);
        $this->assertStringContainsString("margin:auto 0 0;top:auto;\n    /* Held to the bottom, so its own content sets its height. */\n    height:auto;", $css);
    }

    /** Every student page on a phone: the dashboard's top bar and side margins, not a desktop's 26px. */
    public function test_every_student_page_shares_one_phone_top_bar_and_margin(): void
    {
        foreach (['styles.css', 'Styles/consultation.css'] as $sheet) {
            $css = File::get(resource_path('student-ui/'.$sheet));

            $this->assertStringContainsString('.topbar{padding:0 12px!important}', $css, $sheet);
            $this->assertStringContainsString('.topbar .page-title{font-size:15px!important}', $css, $sheet);
            $this->assertStringContainsString('.content{padding:12px!important}', $css, $sheet);
            $this->assertStringContainsString('.content{padding:10px!important}', $css, $sheet);
        }

        foreach (['team.css', 'experts.css', 'dashboard.css'] as $sheet) {
            $this->assertStringNotContainsString('.topbar .page-title{font-size:14px!important}', File::get(resource_path('student-ui/Styles/'.$sheet)), $sheet);
        }
    }

    /** The booking card ran 90px off the right of a 320px phone: a grid column is as wide as its widest line. */
    public function test_consultation_fits_a_small_phone(): void
    {
        $css = File::get(resource_path('student-ui/Styles/consultation.css'));
        $this->assertStringContainsString('.consultation-container{grid-template-columns:minmax(0,1fr)!important}', $css);
        $this->assertStringContainsString('.consultation-container>*{min-width:0}', $css);

        $this->actingAs($this->student())->get(route('student.consultation'))
            ->assertOk()
            ->assertSee('.sched-cal .calendar-grid{grid-template-columns:repeat(7,minmax(0,1fr))}', false);
    }

    /** A section stored the long way ("4th Year · BSCS-4A") read "BSCS - 4BSCS-4A" under the student's name. */
    public function test_the_sidebar_reads_a_long_section_as_its_letter(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Computer Science', 'year_section' => '4th Year · BSCS-4A',
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('BSCS - 4A')
            ->assertDontSee('4BSCS-4A');
    }

    /** A team called "Team Omega" read "Team Team Omega" on Financial Records. */
    public function test_financial_records_does_not_say_team_twice(): void
    {
        foreach (['Team Omega' => 'Team Omega', 'Omega' => 'Team Omega'] as $name => $shown) {
            $team = Team::create(['team_name' => $name, 'project_title' => 'Fees', 'team_status' => 'Active']);
            $student = Student::factory()->withCompleteProfile()->create([
                'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
            ]);
            StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

            $html = $this->actingAs($student)->get(route('student.financial'))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<p class="fx-who">\s*'.preg_quote($shown, '/').'\b/', $html, $name);
            $this->assertStringNotContainsString('Team Team', $html);
        }
    }

    /** The profile form has no sidebar or tab bar, so editing has its own way out. */
    public function test_editing_the_profile_has_a_way_back(): void
    {
        $this->actingAs($this->student())->get(route('student.personal-info.edit'))
            ->assertOk()
            ->assertSee('href="'.route('student.dashboard').'" class="pi-leave"', false)
            ->assertSee('Back to dashboard');
    }

    /** A closed join-request pop-up was see-through but still reachable with Tab and by screen readers. */
    public function test_closed_join_request_pop_ups_are_out_of_reach(): void
    {
        $this->actingAs($this->student())->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s,visibility .2s}', false)
            ->assertSee('#jrp-overlay.active,#jrp-confirm-overlay.active{opacity:1;visibility:visible;pointer-events:auto}', false);
    }
}
