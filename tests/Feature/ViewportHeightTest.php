<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * On a phone, 100vh is the screen with the browser's bars hidden - Safari's
 * toolbar, the address bar in Chrome and Brave. While those bars show,
 * anything 100vh tall runs under them: the bottom of the sidebar, Sign Out
 * with it, could not be seen. Every height given in vh is followed by the
 * same in dvh, which is the part of the screen actually showing; a browser
 * too old for dvh keeps the vh.
 */
class ViewportHeightTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<SplFileInfo> */
    private function stylesheets(): iterable
    {
        return collect(File::allFiles(resource_path('student-ui')))
            ->merge(File::files(resource_path('css')))
            ->merge(File::files(public_path('css')))
            ->merge(File::allFiles(resource_path('views')))
            ->filter(fn (SplFileInfo $file) => preg_match('/\.(css|php)$/', $file->getFilename()));
    }

    public function test_every_vh_height_is_followed_by_the_same_in_dvh(): void
    {
        $missing = [];
        $checked = 0;

        foreach ($this->stylesheets() as $file) {
            $source = $file->getContents();

            preg_match_all(
                '/(?<![-\w])((?:min-|max-)?height)(\s*:\s*)([^;{}"\']*?\d(?:\.\d+)?vh\b[^;{}"\']*?)(\s*!important)?\s*(?:;|(?=\}))/',
                $source,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            );

            foreach ($matches as $match) {
                [$property, $colon, $value] = [$match[1][0], $match[2][0], $match[3][0]];

                if (preg_match('/\d(?:\.\d+)?[dsl]vh/', $value)) {
                    continue;
                }

                // The fallback for browsers without dvh, given only to them.
                if (str_contains($value, 'var(--app-height')) {
                    continue;
                }

                $checked++;
                $inDvh = $property.$colon.preg_replace('/(\d(?:\.\d+)?)vh\b/', '$1dvh', $value);
                $after = substr($source, $match[0][1] + strlen($match[0][0]), strlen($inDvh) + 8);

                if (! str_contains($after, $inDvh)) {
                    $missing[] = $file->getRelativePathname().': '.trim($match[0][0]);
                }
            }
        }

        $this->assertGreaterThan(50, $checked, 'The scan found too few vh heights to mean anything.');
        $this->assertSame([], $missing, "Heights in vh with no dvh after them:\n".implode("\n", $missing));
    }

    /** The sidebar is where Sign Out lives, in every portal. */
    public function test_the_sidebar_is_the_height_of_the_screen_showing_in_every_portal(): void
    {
        foreach (['student-ui/styles.css', 'student-ui/Styles/dashboard.css'] as $sheet) {
            $this->assertStringContainsString('height:100vh;height:100dvh;overflow:hidden}', File::get(resource_path($sheet)));
        }

        foreach (['admin.css', 'expert.css'] as $sheet) {
            $this->assertStringContainsString('height:100vh;height:100dvh', File::get(public_path('css/'.$sheet)));
        }
    }

    public function test_an_older_browser_gets_the_visible_height_and_sign_out_clears_the_home_indicator(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('.sidebar .nav{padding-bottom:calc(8px + env(safe-area-inset-bottom, 0px))}', false)
            ->assertSee('@supports not (height:100dvh){', false)
            ->assertSee('.sidebar,.sidebar.mobile-open{height:var(--app-height,100vh)!important}', false)
            ->assertSee("root.style.setProperty('--app-height', window.innerHeight + 'px');", false);
    }
}
