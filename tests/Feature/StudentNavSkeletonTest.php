<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A click in the student sidebar shows the next page's outline in the content
 * area straight away (student/partials/nav-skeleton), instead of the old page
 * sitting unchanged until the new one replaces it all at once.
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

    /** The content security policy only runs inline blocks that carry its nonce. */
    public function test_its_script_and_styles_carry_the_page_nonce(): void
    {
        $html = $this->studentPage();

        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">\s*\(function \(\) \{\s*if \(window\.__navSkeleton\)/', $html);
        $this->assertMatchesRegularExpression('/<style nonce="[^"]+">\s*@keyframes nsk-shimmer/', $html);
    }
}
