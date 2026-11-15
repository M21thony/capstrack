<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * A PDF on the school's letterhead (capsa.reports._letterhead), landscape,
 * with "Page N of M" drawn in the bottom right once the pages are laid out.
 *
 * Inline is what the PDF window previews; without it the browser saves the
 * file. Export shows the inline one first, with Download PDF and Download
 * Excel beside it.
 */
class LetterheadPdf
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function response(string $view, array $data, string $fileName, bool $inline = false, string $paper = 'letter'): Response
    {
        $pdf = Pdf::loadView($view, $data + ['by' => auth('expert')->user()->name ?? 'the CaPSA/TSA'])
            ->setPaper($paper, 'landscape');

        // The page count is only known once laid out, so the numbers are drawn after rendering.
        $pdf->render();
        $dom = $pdf->getDomPDF();
        $canvas = $dom->getCanvas();
        $canvas->page_text($canvas->get_width() - 104, $canvas->get_height() - 23, 'Page {PAGE_NUM} of {PAGE_COUNT}',
            $dom->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.33, 0.33, 0.33]);

        return $inline ? $pdf->stream($fileName) : $pdf->download($fileName);
    }
}
