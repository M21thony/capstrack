<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The CapsTrack logo is wired in everywhere the brand shows up.
 *
 * The mark is one file that three portals, the welcome modal, the browser tab
 * and the push notification all point at. A rename or a missed file shows up
 * as a broken image on a page nobody opened that day, so these check the
 * files and the places that name them rather than how they look.
 */
class BrandAssetsTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function brandFiles(): array
    {
        return [
            'mark' => ['images/brand/capstrack-mark.svg'],
            'mark, small sizes' => ['images/brand/capstrack-mark-small.svg'],
            'mark, 192px png' => ['images/brand/capstrack-mark-192.png'],
            'logo, dark' => ['images/brand/capstrack-logo.svg'],
            'logo, light' => ['images/brand/capstrack-logo-light.svg'],
            'logo, stacked' => ['images/brand/capstrack-logo-stacked.svg'],
            'logo without tagline, dark' => ['images/brand/capstrack-logo-ui.svg'],
            'logo without tagline, light' => ['images/brand/capstrack-logo-ui-light.svg'],
            'wordmark' => ['images/brand/capstrack-wordmark.svg'],
            'favicon svg' => ['favicon.svg'],
            'favicon ico' => ['favicon.ico'],
            'apple touch icon' => ['apple-touch-icon.png'],
        ];
    }

    #[DataProvider('brandFiles')]
    public function test_each_brand_file_exists_and_is_not_empty(string $path): void
    {
        $this->assertFileExists(public_path($path));
        $this->assertGreaterThan(0, filesize(public_path($path)));
    }

    /**
     * One mark everywhere: the same ink tile with a gold rim in the browser
     * tab, the sidebar, the login page and every lockup, so the logo does not
     * look different from one place to the next.
     */
    #[DataProvider('filesShowingTheTile')]
    public function test_every_logo_uses_the_same_gold_rimmed_tile(string $path): void
    {
        $this->assertStringContainsString('fill="#141414" stroke="#F5B700"', file_get_contents(public_path($path)));
    }

    /** @return array<string, array{0: string}> */
    public static function filesShowingTheTile(): array
    {
        return [
            'mark' => ['images/brand/capstrack-mark.svg'],
            'mark, small sizes' => ['images/brand/capstrack-mark-small.svg'],
            'favicon' => ['favicon.svg'],
            'logo, dark' => ['images/brand/capstrack-logo.svg'],
            'logo, light' => ['images/brand/capstrack-logo-light.svg'],
            'logo, stacked' => ['images/brand/capstrack-logo-stacked.svg'],
            'logo without tagline, dark' => ['images/brand/capstrack-logo-ui.svg'],
            'logo without tagline, light' => ['images/brand/capstrack-logo-ui-light.svg'],
        ];
    }

    /** A real .ico starts with a zero, a zero and the type 1 (icon). */
    public function test_the_favicon_is_a_real_icon_file(): void
    {
        $this->assertSame("\x00\x00\x01\x00", substr(file_get_contents(public_path('favicon.ico')), 0, 4));
    }

    /** @return array<string, array{0: string}> */
    public static function viewsThatShowTheMark(): array
    {
        return [
            'student sidebar' => ['student/partials/sidebar.blade.php'],
            'admin sidebar' => ['capsa/_sidebar.blade.php'],
            'expert sidebar' => ['instructor/partials/sidebar.blade.php'],
            'welcome modal' => ['partials/welcome-modal.blade.php'],
        ];
    }

    #[DataProvider('viewsThatShowTheMark')]
    public function test_the_view_uses_the_mark_and_not_the_old_layers_icon(string $view): void
    {
        $source = file_get_contents(resource_path('views/'.$view));

        $this->assertStringContainsString("asset('images/brand/capstrack-mark.svg')", $source);
        $this->assertStringNotContainsString('M12 2L2 7l10 5 10-5-10-5', $source);
    }

    /** @return array<string, array{0: string}> */
    public static function sidebars(): array
    {
        return [
            'student sidebar' => ['student/partials/sidebar.blade.php'],
            'admin sidebar' => ['capsa/_sidebar.blade.php'],
            'expert sidebar' => ['instructor/partials/sidebar.blade.php'],
        ];
    }

    /**
     * At sidebar size a lone mark reads as a plain triangle, so the name
     * beside it is the wordmark with its pyramid A's, not plain text.
     */
    #[DataProvider('sidebars')]
    public function test_the_sidebar_names_the_app_with_the_wordmark(string $view): void
    {
        $source = file_get_contents(resource_path('views/'.$view));

        $this->assertStringContainsString("asset('images/brand/capstrack-wordmark.svg')", $source);
        $this->assertStringNotContainsString('capstrack-mark-small.svg', $source);
    }

    /** The login page shows the logo in its own colors, not the old one forced white. */
    public function test_the_login_page_shows_the_new_logo(): void
    {
        $source = file_get_contents(resource_path('views/auth/login.blade.php'));

        $this->assertStringContainsString("asset('images/brand/capstrack-logo-ui.svg')", $source);
        $this->assertStringNotContainsString("asset('images/capstrack.png')", $source);
        $this->assertStringNotContainsString('brightness-0 invert', $source);
    }

    public function test_the_guest_layout_links_the_new_browser_icons(): void
    {
        $source = file_get_contents(resource_path('views/layouts/guest.blade.php'));

        $this->assertStringContainsString("asset('favicon.svg')", $source);
        $this->assertStringContainsString("asset('favicon.ico')", $source);
        $this->assertStringContainsString("asset('apple-touch-icon.png')", $source);
    }

    /** The service worker draws the notification icon from this path. */
    public function test_push_notifications_use_an_icon_that_exists(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("icon: '/images/brand/capstrack-mark-192.png'", $worker);
        $this->assertFileExists(public_path('images/brand/capstrack-mark-192.png'));
    }
}
