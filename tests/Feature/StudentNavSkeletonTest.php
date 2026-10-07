<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Student pages load without a flash.
 *
 * Right after signing in, and the first time each page is opened, the content
 * area shows the page's outline while it loads (student/partials/nav-skeleton);
 * a page already opened comes straight up. And every page holds its first
 * paint until it has been read in full (student/partials/render-whole), so it
 * never shows the sidebar over an empty content area.
 */
class StudentNavSkeletonTest extends TestCase
{
    use RefreshDatabase;

    private function studentPage(): string
    {
        $student = Student::factory()->withCompleteProfile()->create();

        return $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk()->getContent();
    }

    /** Every page the sidebar leads to has an outline waiting - a new link needs one too. */
    public function test_every_sidebar_page_has_an_outline_ready(): void
    {
        $html = $this->studentPage();

        preg_match_all('/<a href="([^"]+)"[^>]*class="nav-item\b/', $html, $links);
        $this->assertNotEmpty($links[1]);

        foreach ($links[1] as $href) {
            $path = parse_url(html_entity_decode($href), PHP_URL_PATH);
            $this->assertStringContainsString('data-nav-skeleton data-path="'.$path.'"', $html, "No outline for the sidebar link to {$path}.");
        }
    }

    /** It swaps the content area only; the sidebar stays exactly as it is. */
    public function test_the_outline_replaces_the_content_area_not_the_sidebar(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString("content = document.querySelector('.main > .content');", $html);
        $this->assertStringContainsString('content.appendChild(template.content.cloneNode(true));', $html);
    }

    /** Once per page per sign-in: "opened" is kept under this sign-in's session. */
    public function test_the_outline_is_shown_once_per_page_per_sign_in(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString("var SESSION = 'nsk:".substr(sha1(session()->getId()), 0, 12).":';", $html);
        $this->assertStringContainsString('if (opened(url.pathname)) return;', $html);
        $this->assertStringContainsString('if (!opened(here) && ownTemplate && first) {', $html);
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

        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">\s*\(function \(\) \{\s*if \(window\.__navSkeleton\)/', $html);
        $this->assertMatchesRegularExpression('/<style nonce="[^"]+">\s*@keyframes nsk-shimmer/', $html);
    }
}
