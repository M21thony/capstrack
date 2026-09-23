<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Support\CapsaReports;
use App\Support\SectionScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports: pick the period once on the index, then open any report as a web
 * table - sorted, searched, columns shown, hidden and reordered, merged with
 * another report - and download exactly that as a PDF or an Excel sheet.
 */
class ReportController extends Controller
{
    /** Excel fill and text colour per chip tone. */
    private const FILLS = [
        'ok' => ['DCFCE7', '166534'],
        'warn' => ['FEF3C7', '92400E'],
        'bad' => ['FEE2E2', '991B1B'],
        'none' => ['EEF0F3', '475569'],
        'info' => ['E0EAFB', '1D4F91'],
    ];

    /** Excel fill for a merged report's band of columns. */
    private const BAND_FILLS = ['grey' => 'E5E7EB', 'gold' => 'F5EBC4', 'blue' => 'DCE7F8', 'green' => 'DCF1E3'];

    public function __construct(private CapsaReports $reports) {}

    public function index(Request $request): View|RedirectResponse
    {
        // The old page kept the report in ?tab=; send those links to the report's own page.
        if ($request->has('tab')) {
            $report = in_array($request->query('tab'), CapsaReports::TEAM_REPORTS, true) ? $request->query('tab') : 'progress';

            return redirect()->route('capsa.reports.show', ['report' => $report] + $request->except(['tab', 'from', 'to']));
        }

        $ctx = $this->reports->context(auth('expert')->user(), $request->query());

        return view('capsa.reports.index', [
            'ctx' => $ctx,
            'reports' => collect(CapsaReports::REPORTS)->map(fn ($r, $key) => [
                'key' => $key,
                'name' => $r[0],
                'about' => $r[1],
                'new' => in_array($key, CapsaReports::NEW_REPORTS, true),
                'extra' => $this->reports->extraFilters($key, $ctx),
            ])->values()->all(),
            'period' => $this->periodQuery($ctx),
            'teamCount' => $ctx['teams']->count(),
            'sectionCount' => $ctx['teams']->map(fn ($t) => $ctx['teamSections'][$t->team_id])->unique()->count(),
        ]);
    }

    /** A section on its own: the Team Progress table filtered to it. */
    public function section(Request $request, string $section): RedirectResponse
    {
        abort_unless($section === SectionScope::UNASSIGNED || in_array($section, auth('expert')->user()->sections ?? [], true), 404);

        return redirect()->route('capsa.reports.show', ['report' => 'progress', 'section' => $section] + $request->except('tab'));
    }

    public function show(Request $request, string $report): View|Response
    {
        $ctx = $this->reports->context(auth('expert')->user(), $request->query());
        $teamReport = in_array($report, CapsaReports::TEAM_REPORTS, true);
        $bySection = $teamReport && $request->query('rows') === 'sections';

        $merged = [];
        if ($teamReport && ! $bySection) {
            $asked = array_filter(explode(',', (string) $request->query('merge', '')));
            $merged = array_values(array_filter(CapsaReports::TEAM_REPORTS, fn ($r) => $r !== $report && in_array($r, $asked, true)));
        }

        $table = $this->reports->build($report, $ctx, $bySection);
        $groups = [];
        if ($merged) {
            [$table, $groups] = $this->merge($table, $report, $merged, $ctx);
        }

        // Column order and which are hidden; the first column names the row and always shows first.
        $keys = array_keys($table['columns']);
        $first = $keys[0];
        $order = array_values(array_intersect(explode(',', (string) $request->query('order', '')), $keys));
        $order = array_values(array_unique([$first, ...array_diff($order, [$first]), ...array_diff($keys, $order)]));
        $hidden = array_values(array_diff(array_intersect(explode(',', (string) $request->query('hide', '')), $keys), [$first]));
        $visible = array_values(array_diff($order, $hidden));

        $name = CapsaReports::REPORTS[$report][0];
        $scope = $this->scopeLine($report, $ctx, $bySection, $merged);
        $download = $request->query('download');

        if ($download === 'pdf' || $download === 'xlsx') {
            $rows = $this->searchAndSort($table['rows'], $visible, (string) $request->query('q', ''), (string) $request->query('sort', ''), $request->query('dir') === 'desc');
            $fileName = Str::slug($name).'_'.$ctx['year'].'_'.now()->toDateString();
            $sheet = $this->plain($table, $rows, $visible, $groups);

            if ($download === 'xlsx') {
                return $this->excel($name, $scope, $sheet, $fileName.'.xlsx');
            }

            $pdf = Pdf::loadView('capsa.reports.pdf', ['name' => $name, 'scope' => $scope, 'sheet' => $sheet, 'by' => auth('expert')->user()->name ?? 'the CaPSA'])
                ->setPaper('letter', 'landscape');
            // The page count is only known once laid out, so the numbers are drawn after rendering.
            $pdf->render();
            $dom = $pdf->getDomPDF();
            $canvas = $dom->getCanvas();
            $canvas->page_text($canvas->get_width() - 104, $canvas->get_height() - 23, 'Page {PAGE_NUM} of {PAGE_COUNT}',
                $dom->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.33, 0.33, 0.33]);

            return $request->boolean('inline') ? $pdf->stream($fileName.'.pdf') : $pdf->download($fileName.'.pdf');
        }

        $mergeable = $teamReport ? array_values(array_diff(CapsaReports::TEAM_REPORTS, [$report])) : [];
        $baseKeys = array_keys($this->reports->build($report, $ctx)['columns']);

        return view('capsa.reports.table', [
            'ctx' => $ctx,
            'report' => $report,
            'name' => $name,
            'about' => CapsaReports::REPORTS[$report][1],
            'table' => $table,
            'groups' => $groups,
            'order' => $order,
            'hidden' => $hidden,
            'bySection' => $bySection,
            'teamReport' => $teamReport,
            'merged' => $merged,
            'mergeable' => collect($mergeable)->map(fn ($r) => [
                'key' => $r,
                'name' => CapsaReports::REPORTS[$r][0],
                'band' => CapsaReports::BANDS[$r],
                'columns' => collect($this->reports->build($r, $ctx)['columns'])->except(['team', 'section', ...$baseKeys])->map(fn ($c) => $c['label'])->all(),
            ])->all(),
            'extra' => $this->reports->extraFilters($report, $ctx),
            'mergeExtra' => array_merge(...array_map(fn ($r) => $this->reports->extraFilters($r, $ctx), $mergeable ?: ['master'])),
            'scope' => $scope,
            'period' => $this->periodQuery($ctx),
            'details' => $bySection || $report === 'experts' ? [] : $this->reports->teamDetails($ctx, array_values(array_filter(array_column($table['rows'], 'id')))),
        ]);
    }

    /**
     * Add merged reports' columns beside each team, keyed "report.column".
     *
     * @return array{0: array, 1: list<array{label: string, band: string, keys: string[]}>}
     */
    private function merge(array $table, string $report, array $merged, array $ctx): array
    {
        $groups = [['label' => CapsaReports::REPORTS[$report][0], 'band' => CapsaReports::BANDS[$report], 'keys' => array_keys($table['columns'])]];

        foreach ($merged as $other) {
            $theirs = $this->reports->build($other, $ctx);
            // A column the table already has (the project title, most often) is not added twice.
            $have = array_map(fn ($k) => Str::afterLast($k, '.'), array_keys($table['columns']));
            $cols = collect($theirs['columns'])->except(['team', 'section', ...$have])->mapWithKeys(fn ($c, $k) => [$other.'.'.$k => $c])->all();
            $byTeam = collect($theirs['rows'])->keyBy('id');

            $table['columns'] += $cols;
            foreach ($table['rows'] as $i => $row) {
                $cells = $byTeam->get($row['id'])['cells'] ?? [];
                foreach (array_keys($cols) as $key) {
                    $table['rows'][$i]['cells'][$key] = $cells[Str::after($key, '.')] ?? CapsaReports::DASH;
                }
            }
            if ($table['total']) {
                foreach (array_keys($cols) as $key) {
                    $table['total'][$key] = '';
                }
            }

            $groups[] = ['label' => $this->reports->bandLabel($other, $ctx), 'band' => CapsaReports::BANDS[$other], 'keys' => array_keys($cols)];
        }

        return [$table, $groups];
    }

    /** @return array<string, string> the period filters, for links that keep them */
    private function periodQuery(array $ctx): array
    {
        return array_filter([
            'year' => $ctx['year'],
            'term' => $ctx['term'],
            'program' => $ctx['program'],
            'section' => $ctx['section'],
        ], fn ($v) => $v !== null && $v !== '') + ['term' => ''];
    }

    private function scopeLine(string $report, array $ctx, bool $bySection, array $merged): string
    {
        return implode(' · ', array_filter([
            'S.Y. '.$ctx['year'],
            $report === 'experts' ? 'compared with S.Y. '.$ctx['compare'] : $ctx['termOptions'][$ctx['term']],
            $report === 'experts' ? null : ($ctx['program'] !== '' ? $ctx['program'] : $ctx['programOptions']['']),
            $report === 'experts' ? null : ($ctx['section'] !== '' ? $ctx['sectionOptions'][$ctx['section']] : 'All sections'),
            in_array($report, ['results', 'schedule'], true) || in_array('results', $merged, true) ? $ctx['stage'] : null,
            $report === 'fees' || in_array('fees', $merged, true) ? $ctx['fee'].' fee' : null,
            $bySection ? 'Section totals' : null,
            $merged ? 'merged with '.implode(' and ', array_map(fn ($r) => CapsaReports::REPORTS[$r][0], $merged)) : null,
        ]));
    }

    /** The page searches and sorts live; a download does the same from the query it was given. */
    private function searchAndSort(array $rows, array $visible, string $q, string $sort, bool $desc): array
    {
        $q = mb_strtolower(trim($q));
        if ($q !== '') {
            $rows = array_values(array_filter($rows, fn ($row) => str_contains(
                mb_strtolower(implode(' ', array_map(fn ($k) => CapsaReports::text($row['cells'][$k] ?? '', ' '), $visible))),
                $q,
            )));
        }

        if (in_array($sort, $visible, true)) {
            usort($rows, function ($a, $b) use ($sort, $desc) {
                $x = CapsaReports::sortValue($a['cells'][$sort] ?? '');
                $y = CapsaReports::sortValue($b['cells'][$sort] ?? '');
                // Empty values stay at the bottom whichever way round.
                if (($x === '') !== ($y === '')) {
                    return $x === '' ? 1 : -1;
                }

                return $desc ? $y <=> $x : $x <=> $y;
            });
        }

        return $rows;
    }

    /** The shown columns in order, every cell as text with its tone, for the PDF and the sheet. */
    private function plain(array $table, array $rows, array $visible, array $groups): array
    {
        $tone = fn ($cell) => is_array($cell) && $cell[0] === 'chip' ? $cell[2] : null;
        $line = fn (array $cells, bool $bold = false) => array_map(fn ($k) => [
            'text' => CapsaReports::text($cells[$k] ?? ''),
            'tone' => $tone($cells[$k] ?? null),
            'bold' => $bold || (is_array($cells[$k] ?? null) && $cells[$k][0] === 'b'),
        ], $visible);

        // Bands follow the columns: one per run of neighbouring columns from the same report.
        $bands = [];
        if ($groups) {
            $of = [];
            foreach ($groups as $g) {
                foreach ($g['keys'] as $k) {
                    $of[$k] = $g;
                }
            }
            foreach ($visible as $k) {
                $g = $of[$k];
                $last = array_key_last($bands);
                if ($last !== null && $bands[$last]['label'] === $g['label']) {
                    $bands[$last]['span']++;
                } else {
                    $bands[] = ['label' => $g['label'], 'band' => $g['band'], 'span' => 1];
                }
            }
        }

        return [
            'headings' => array_map(fn ($k) => $table['columns'][$k]['label'], $visible),
            'right' => array_map(fn ($k) => $table['columns'][$k]['align'] === 'right', $visible),
            'bands' => $bands,
            'rows' => array_map(fn ($r) => $line($r['cells']), $rows),
            'total' => $table['total'] && $rows ? $line($table['total'], true) : null,
            'summary' => $table['summary'],
            'empty' => $table['empty'],
        ];
    }

    /**
     * Title and scope on top, merged reports' bands, a gold heading row kept
     * in view with the first column, the rows, and the total.
     */
    private function excel(string $title, string $scope, array $sheetData, string $fileName): Response
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(Str::limit($title, 31, ''));
        $width = max(1, count($sheetData['headings']));
        // Typed explicitly, so a team named "=SUM(...)" stays text instead of becoming a formula.
        $put = fn ($c, $r, $value) => $sheet->setCellValueExplicit([$c, $r], $value, DataType::TYPE_STRING);
        $fill = fn ($range, $rgb) => $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);

        $put(1, 1, $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $put(1, 2, $scope.' · Generated '.now()->format('M j, Y'));
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('6B7280');

        $head = 4;
        if ($sheetData['bands']) {
            $c = 1;
            foreach ($sheetData['bands'] as $band) {
                $put($c, $head, $band['label']);
                if ($band['span'] > 1) {
                    $sheet->mergeCells([$c, $head, $c + $band['span'] - 1, $head]);
                }
                $fill([$c, $head, $c + $band['span'] - 1, $head], self::BAND_FILLS[$band['band']]);
                $c += $band['span'];
            }
            $sheet->getStyle([1, $head, $width, $head])->getFont()->setBold(true);
            $head++;
        }

        foreach ($sheetData['headings'] as $i => $heading) {
            $put($i + 1, $head, $heading);
        }
        $sheet->getStyle([1, $head, $width, $head])->getFont()->setBold(true)->getColor()->setRGB('18191F');
        $fill([1, $head, $width, $head], 'C9A82C');

        $lines = [...$sheetData['rows'], ...($sheetData['total'] ? [$sheetData['total']] : [])];
        foreach ($lines as $i => $line) {
            $r = $head + 1 + $i;
            foreach ($line as $c => $cell) {
                $put($c + 1, $r, $cell['text']);
                if ($cell['tone']) {
                    $fill([$c + 1, $r], self::FILLS[$cell['tone']][0]);
                    $sheet->getStyle([$c + 1, $r])->getFont()->getColor()->setRGB(self::FILLS[$cell['tone']][1]);
                }
                if ($cell['bold']) {
                    $sheet->getStyle([$c + 1, $r])->getFont()->setBold(true);
                }
            }
        }
        $last = $head + count($lines);

        if (! $lines) {
            $put(1, $head + 1, $sheetData['empty']);
            $last = $head + 1;
        }

        $sheet->getStyle([1, $head, $width, $last])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('9AA0AD');
        $sheet->getStyle([1, $head + 1, $width, $last])->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        if ($sheetData['total']) {
            $fill([1, $last, $width, $last], 'F2F3F5');
        }

        // Widths from the table alone, so the long title and scope lines above don't stretch the columns.
        foreach (range(1, $width) as $c) {
            $texts = [$sheetData['headings'][$c - 1] ?? '', ...array_map(fn ($l) => $l[$c - 1]['text'] ?? '', $lines)];
            $longest = max(array_map(fn ($s) => max(array_map('mb_strlen', explode("\n", (string) $s))), $texts));
            $sheet->getColumnDimensionByColumn($c)->setWidth(min(60, max(10, $longest + 3)));

            if ($sheetData['right'][$c - 1] ?? false) {
                $sheet->getStyle([$c, $head, $c, $last])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        // The headings and the first column stay in view while scrolling.
        $sheet->freezePane('B'.($head + 1));

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
