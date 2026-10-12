<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every "View PDF" opens the one PDF window (resources/js/pdf-viewer.js).
 *
 * Three of them loaded the file into an iframe, which hands it to the
 * browser's own viewer: its toolbar and download button, and on a phone often
 * a download prompt instead of the pages. Two more opened a new tab. The
 * window draws the pages in the page with pdf.js instead. The Document
 * Annotator keeps its own viewer and is not covered here.
 */
class PdfViewerTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function pagesThatShowAPdf(): array
    {
        return [
            'Team Assignments' => ['instructor/assignments.blade.php'],
            'Team Management title' => ['capsa/team-management/title.blade.php'],
            'Reports' => ['capsa/reports/table.blade.php'],
            'Archive' => ['capsa/archive.blade.php'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pagesThatShowAPdf')]
    public function test_the_page_opens_pdfs_in_the_shared_window_not_an_iframe(string $view): void
    {
        $source = file_get_contents(resource_path('views/'.$view));

        $this->assertStringContainsString("@include('partials.pdf-viewer')", $source);
        $this->assertStringNotContainsString('<iframe', $source);
    }

    public function test_the_student_side_uses_the_same_window(): void
    {
        $sidebar = file_get_contents(resource_path('views/student/partials/sidebar.blade.php'));
        $preview = file_get_contents(resource_path('js/submission-preview.js'));
        $experts = file_get_contents(resource_path('views/student/experts.blade.php'));

        $this->assertStringContainsString('<dialog class="pv" id="previewDialog"', $sidebar);
        $this->assertStringContainsString("import './pdf-viewer.js';", $preview);
        $this->assertStringContainsString('PdfViewer.open', $preview);
        // The proposal link on an application opens in the window, not a tab.
        $this->assertStringContainsString('data-pdf-view=', $experts);
    }

    public function test_the_window_is_built_by_vite_and_draws_with_pdfjs(): void
    {
        $partial = file_get_contents(resource_path('views/partials/pdf-viewer.blade.php'));
        $script = file_get_contents(resource_path('js/pdf-viewer.js'));
        $config = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString("@vite('resources/js/pdf-viewer.js')", $partial);
        $this->assertStringContainsString("'resources/js/pdf-viewer.js'", $config);
        $this->assertStringContainsString("from 'pdfjs-dist'", $script);
        $this->assertStringNotContainsString('<iframe', $script);
        $this->assertStringNotContainsString("createElement('iframe')", $script);
    }

    public function test_the_window_has_page_zoom_download_and_close_controls(): void
    {
        $script = file_get_contents(resource_path('js/pdf-viewer.js'));

        foreach (['aria-label="Previous page"', 'aria-label="Next page"', 'aria-label="Zoom in"', 'aria-label="Zoom out"', 'Fit width', 'Download', 'aria-label="Close"'] as $control) {
            $this->assertStringContainsString($control, $script);
        }
    }
}
