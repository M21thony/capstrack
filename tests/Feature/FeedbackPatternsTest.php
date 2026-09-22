<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The feedback rules, held in place.
 *
 * Each of these started as a real defect in this tree: a dozen window.alert()
 * calls, three browser confirms, three competing definitions of .modal-overlay,
 * and a toast that faded in and then went invisible for the rest of its life.
 * A grep is a cheap way to stop each of them coming back.
 */
class FeedbackPatternsTest extends TestCase
{
    /** @return list<string> */
    private function frontendFiles(): array
    {
        $found = [];

        foreach (['resources/views', 'resources/js'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir))
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'jsx'], true)) {
                    $found[] = $file->getPathname();
                }
            }
        }

        return $found;
    }

    public function test_no_browser_alert_or_confirm_survives_outside_the_dialog_partials(): void
    {
        // These two describe the old behaviour in prose, which is the point of them.
        $allowed = [
            'partials/app-dialog.blade.php',
            'partials/form-notice.blade.php',
            'instructor/docreview.blade.php',
            'student/duplication.blade.php',
        ];

        $offenders = [];

        foreach ($this->frontendFiles() as $path) {
            foreach ($allowed as $exempt) {
                if (str_contains(str_replace('\\', '/', $path), $exempt)) {
                    continue 2;
                }
            }

            foreach (file($path) as $number => $line) {
                // confirmSheet( and formNotice( are ours; window.confirm( is not.
                if (preg_match('/(^|[^a-zA-Z.$])(window\.)?(alert|confirm)\s*\(/', $line)
                    && ! str_contains($line, 'confirmSheet(')) {
                    $offenders[] = basename($path).':'.($number + 1).' '.trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, "A browser dialog came back:\n".implode("\n", $offenders));
    }

    public function test_every_view_calling_confirm_sheet_loads_the_dialog(): void
    {
        $missing = [];

        foreach ($this->frontendFiles() as $path) {
            // The partial that defines confirmSheet does not include itself.
            if (! str_ends_with($path, '.blade.php')
                || str_contains(str_replace('\\', '/', $path), 'partials/app-dialog.blade.php')) {
                continue;
            }

            $source = file_get_contents($path);

            if (str_contains($source, 'confirmSheet(') && ! str_contains($source, 'partials.app-dialog')) {
                $missing[] = basename($path);
            }
        }

        $this->assertSame([], $missing, 'These views call confirmSheet with nothing defining it: '.implode(', ', $missing));
    }

    public function test_the_dialog_defines_both_shapes_and_manages_focus(): void
    {
        $source = file_get_contents(resource_path('views/partials/app-dialog.blade.php'));

        $this->assertStringContainsString('window.confirmSheet =', $source);
        $this->assertStringContainsString('window.outcomeDialog =', $source);

        // The behaviour that was missing from all 103 hand-built overlays.
        $this->assertStringContainsString("role', cfg.role", $source, 'the dialog never sets its role');
        $this->assertStringContainsString("'Escape'", $source, 'Esc does not close the dialog');
        $this->assertStringContainsString("e.key !== 'Tab'", $source, 'focus is not trapped');
        $this->assertStringContainsString('lastFocused.focus()', $source, 'focus is not returned to the trigger');
        $this->assertStringContainsString('prefers-reduced-motion', $source);
    }

    public function test_admin_css_declares_each_modal_rule_once(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        foreach ([':root{', '.modal-overlay{', '.modal-box{', '.modal-title{', '.modal-sub{', '.modal-actions{'] as $rule) {
            $this->assertSame(
                1,
                substr_count($css, $rule),
                "{$rule} is declared more than once, so which one wins depends on paste order."
            );
        }
    }

    public function test_a_stacked_toast_stays_visible_after_its_animation(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        // .toast carries opacity:0 for the older show/hide system. The stacked
        // system inherited it and its animation has no fill-mode, so without
        // both of these a toast vanished 300ms after appearing.
        $this->assertMatchesRegularExpression(
            '/\.toast-wrap \.toast\{[^}]*animation:toastIn [^};]*both/',
            $css,
            'the stacked toast animation has no fill-mode, so it reverts to opacity:0'
        );
        $this->assertMatchesRegularExpression(
            '/\.toast-wrap \.toast\{[^}]*opacity:1/',
            $css,
            'the stacked toast does not override the older opacity:0'
        );
    }

    public function test_server_failures_on_the_receipt_page_reach_a_dialog_not_a_toast(): void
    {
        $js = file_get_contents(base_path('resources/js/financial/capsa.js'));

        $this->assertStringContainsString('outcomeDialog({', $js);
        // The two catch blocks that used to read toast(e.message).
        $this->assertSame(0, preg_match_all('/catch \(e\) \{\s*toast\(e\.message\)/', $js));
    }
}
