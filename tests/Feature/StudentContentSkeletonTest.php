<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertStringContainsString('background-color: #e1e4e9;', $html);
        $this->assertStringContainsString("cover.style.cssText = 'left:' + box.left", $html);
        $this->assertStringNotContainsString('.main > .content { opacity: .', $html);
    }

    /** Once per page per sign-in: "opened" is kept under this sign-in's session. */
    public function test_the_skeleton_is_shown_once_per_page_per_sign_in(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString("var key = 'csk:".substr(sha1(session()->getId()), 0, 12).":' + ", $html);
        $this->assertStringContainsString('if (opened) return;', $html);
    }

    /** It is never a still picture: a wave sweeps every block, already moving on the first frame. */
    public function test_the_skeleton_shimmers_from_its_first_frame(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString('animation: csk-wave 1s linear -.3s infinite;', $html);
        $this->assertStringContainsString('background-attachment: fixed;', $html);
        $this->assertStringContainsString('(waited >= 700 && picturesReady())', $html);
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
