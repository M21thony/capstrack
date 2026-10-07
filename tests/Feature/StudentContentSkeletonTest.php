<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Student pages load without a flash.
 *
 * Right after signing in, the first time each page is opened, the content
 * area comes up as its skeleton (student/partials/content-skeleton): solid
 * blocks drawn from the page's own cards, words, pictures and buttons, in
 * their exact places, which then fade over the page already drawn beneath. A
 * page already opened comes straight up. And every page holds its first
 * paint until it has been read in full (student/partials/render-whole), so
 * the skeleton is taken from the finished layout.
 */
class StudentContentSkeletonTest extends TestCase
{
    use RefreshDatabase;

    private function studentPage(): string
    {
        $student = Student::factory()->withCompleteProfile()->create();

        return $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk()->getContent();
    }

    /** It covers the content area only; the sidebar and the top bar are drawn as they are. */
    public function test_the_skeleton_covers_the_content_area_not_the_sidebar(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString('html.csk-hide .main > .content { opacity: 0 !important; }', $html);
        $this->assertStringContainsString("var content = document.querySelector('.main > .content');", $html);
    }

    /** A real skeleton: solid blocks over an opaque cover, not the page faded behind it. */
    public function test_the_skeleton_is_solid_blocks_not_the_page_faded(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString('#csk .csk-bone { background: #dde1e7; overflow: hidden; }', $html);
        $this->assertStringContainsString("sheet.style.cssText = 'top:' + top + 'px;width:' + wide + 'px;height:' + (bottom - top) + 'px;background:' + surface;", $html);
        $this->assertStringNotContainsString('.main > .content { opacity: .', $html);
    }

    /** Once per page per sign-in: "opened" is kept under this sign-in's session. */
    public function test_the_skeleton_is_shown_once_per_page_per_sign_in(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString("var key = 'csk:".substr(sha1(session()->getId()), 0, 12).":' + ", $html);
        $this->assertStringContainsString('if (!firstOpening()) return;', $html);
    }

    /** A tab that switches in place gets its own first-time skeleton, like a page. */
    public function test_a_tab_switched_in_place_gets_its_own_first_time_skeleton(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString('if (!firstOpening(view)) return;', $html);
        $this->assertStringContainsString('if (active) active();', $html);
        $this->assertStringContainsString('seen: function (view) { firstOpening(view); },', $html);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function pagesWithTabs(): array
    {
        return [
            'team management' => ['student/team.blade.php', 'window.contentSkeleton.show(which);'],
            'experts' => ['student/experts.blade.php', 'window.contentSkeleton.show(tab);'],
        ];
    }

    /** Each page whose tabs switch in place hands every switch to the skeleton, and the tab it opened on. */
    #[DataProvider('pagesWithTabs')]
    public function test_the_page_hands_its_tab_switches_to_the_skeleton(string $view, string $show): void
    {
        $source = file_get_contents(resource_path('views/'.$view));

        $this->assertStringContainsString($show, $source);
        $this->assertStringContainsString('window.contentSkeleton.seen(', $source);
    }

    /**
     * The whole page is drawn, down to its end, in bands: what is in view
     * gives way first, and a band further down stays a skeleton, still
     * loading, until it is scrolled into view.
     */
    public function test_the_whole_page_is_drawn_and_can_be_scrolled_while_it_loads(): void
    {
        $html = $this->studentPage();

        $this->assertStringNotContainsString('var reach', $html);
        $this->assertStringContainsString('if (!sheet.done && sheet.due === null && inView(sheet))', $html);
        $this->assertStringContainsString('function onScroll() { follow(); schedule(); }', $html);
        $this->assertStringContainsString('return seen > 0 && seen >= Math.min((sheet.bottom - sheet.top) * 0.5, content.clientHeight * 0.4);', $html);
        $this->assertStringContainsString("cover.addEventListener('wheel', function (event) {", $html);
        $this->assertStringContainsString("cover.addEventListener('touchmove', function (event) {", $html);
    }

    /** Table cells are never solid blocks: the words in them are lines, as anywhere else. */
    public function test_table_cells_are_drawn_as_lines_not_blocks(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString("if (TABLE[tag] && tag !== 'TABLE') {", $html);
    }

    /** It is never a still picture: a streak runs across every block, already moving on the first frame. */
    public function test_the_skeleton_shimmers_from_its_first_frame(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString('animation: csk-wave 1s ease-in-out -.25s infinite;', $html);
        $this->assertStringContainsString('#csk .csk-bone::after {', $html);
        $this->assertStringContainsString('sheet.due = Math.max(now, shown) + 900;', $html);
        $this->assertStringContainsString('#csk .csk-bone { animation: csk-breathe 1.2s ease-in-out infinite; }', $html);
    }

    /** Whatever happens, the content never stays hidden. */
    public function test_the_content_is_never_left_hidden(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString("try { build(); } catch (e) { root.classList.remove('csk-hide'); }", $html);
        $this->assertStringContainsString("setTimeout(function () { root.classList.remove('csk-hide'); }, 3000);", $html);
    }

    /** The old click-time outline is gone: one skeleton, not two. */
    public function test_only_one_skeleton_is_on_the_page(): void
    {
        $html = $this->studentPage();

        $this->assertStringNotContainsString('__navSkeleton', $html);
        $this->assertSame(1, substr_count($html, 'window.__contentSkeleton = true;'));
    }

    /** Every page holds its first paint until it has been read in full. */
    public function test_a_student_page_holds_its_first_paint_until_read_in_full(): void
    {
        $this->assertMatchesRegularExpression(
            '/<link rel="expect" href="#student-page-read" blocking="render">\s*<\/head>/',
            $this->studentPage(),
        );
    }

    /** A new student page must hold its paint too, or it flashes like the old ones. */
    public function test_every_page_with_the_student_sidebar_holds_its_first_paint(): void
    {
        foreach (glob(resource_path('views/student/{,*/}*.blade.php'), GLOB_BRACE) as $view) {
            $source = file_get_contents($view);

            if (! str_contains($source, "@include('student.partials.sidebar')")) {
                continue;
            }

            $this->assertStringContainsString("@include('student.partials.render-whole')", $source, basename($view).' does not hold its first paint.');
        }
    }

    /** The content security policy only runs inline blocks that carry its nonce. */
    public function test_its_script_and_styles_carry_the_page_nonce(): void
    {
        $html = $this->studentPage();

        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">\s*\(function \(\) \{\s*if \(window\.__contentSkeleton\)/', $html);
        $this->assertMatchesRegularExpression('/<style nonce="[^"]+">\s*html\.csk-hide/', $html);
    }
}
