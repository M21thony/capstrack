<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every notice that pops up at the side of the screen - a page's own
 * "Saved" toast, a live notification, the Meet the Team "Copied" - stays
 * for four seconds, whichever portal or page shows it.
 *
 * Each page draws its own toast, so this reads their sources rather than
 * one shared helper: any timer on a toast line that hides it after a second
 * or more has to be 4000ms. The short ones (a 200ms fade-out) are left alone.
 */
class ToastDurationTest extends TestCase
{
    private const FOUR_SECONDS = 4000;

    public function test_every_toast_hides_after_four_seconds(): void
    {
        $files = collect(File::allFiles(resource_path('views')))
            ->merge(File::allFiles(resource_path('js')))
            ->filter(fn ($file) => preg_match('/\.(php|js|jsx)$/', $file->getFilename()));

        $found = 0;
        $wrong = [];

        foreach ($files as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
                if (! preg_match('/toast|Toast/', $line) || ! preg_match('/setTimeout\(.*,\s*([^,()]+)\)\s*;?\s*$/', $line, $match)) {
                    continue;
                }

                $delay = trim($match[1]);

                if (! ctype_digit($delay) || (int) $delay < 1000) {
                    continue;
                }

                $found++;

                if ((int) $delay !== self::FOUR_SECONDS) {
                    $wrong[] = $file->getRelativePathname().':'.($number + 1).' hides after '.$delay.'ms';
                }
            }
        }

        $this->assertGreaterThan(10, $found, 'The scan found too few toasts to mean anything.');
        $this->assertSame([], $wrong, "Toasts that do not stay four seconds:\n".implode("\n", $wrong));
    }

    public function test_the_live_notification_popups_stay_four_seconds_too(): void
    {
        $source = File::get(resource_path('views/partials/notification-toasts.blade.php'));

        $this->assertStringContainsString('var life = '.self::FOUR_SECONDS.';', $source);
        $this->assertStringContainsString("bar.style.animationDuration = life + 'ms';", $source);
    }

    /** On a phone a pop-up is a slim banner at the top: title and one line, no label or footer. */
    public function test_the_notification_popups_are_slim_banners_at_the_top_on_a_phone(): void
    {
        $source = File::get(resource_path('views/partials/notification-toasts.blade.php'));

        $this->assertStringContainsString('.nt-stack{top:calc(10px + env(safe-area-inset-top, 0px));bottom:auto;left:12px;right:12px;width:auto;max-width:420px;margin:0 auto;gap:6px;flex-direction:column-reverse}', $source);
        $this->assertStringContainsString('.nt-kind,.nt-foot{display:none}', $source);
        $this->assertStringContainsString('.nt-msg{margin-top:1px;font-size:12px;line-height:1.35;-webkit-line-clamp:1}', $source);
    }
}
