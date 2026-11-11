<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TableReportController;
use App\Models\ReportTable;
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
 * Reports: pick the period once on the list, then open any report as a web
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
            'tables' => ReportTable::where('expert_id', auth('expert')->id())->latest('updated_at')->get()->map->toClient()->values(),
            'templates' => ReportTable::TEMPLATES,
            'fields' => ReportTable::FIELDS,
            // A saved table keeps its own period unless Home's filters were set.
            'openQuery' => $request->only(['year', 'term', 'program', 'section']),
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
        $bySection = $teamReport && $this->text($request, 'rows') === 'sections';

        $merged = [];
        if ($teamReport && ! $bySection) {
            $asked = $this->list($request, 'merge');
            $merged = array_values(array_filter(CapsaReports::TEAM_REPORTS, fn ($r) => $r !== $report && in_array($r, $asked, true)));
        }

        $table = $this->reports->build($report, $ctx, $bySection);
        $groups = [];
        if ($merged) {
            [$table, $groups] = $this->merge($table, $report, $merged, $ctx);
        }

        return $this->present($request, [
            'report' => $report,
            'name' => CapsaReports::REPORTS[$report][0],
            'about' => CapsaReports::REPORTS[$report][1],
            'scope' => $this->scopeLine($report, $ctx, $bySection, $merged),
            'fileTag' => $ctx['year'],
            'ctx' => $ctx,
            'table' => $table,
            'groups' => $groups,
            'teamReport' => $teamReport,
            'bySection' => $bySection,
            'merged' => $merged,
        ]);
    }

    /**
     * A table from any staff page, opened as a report. The page sent what it
     * was showing (TableReportController); this is the same report screen the
     * Reports use - column picker, search and sort, Preview PDF, and a PDF or
     * Excel download - over that table. Open to the Expert and Admin portals
     * as well as the CaPSA/TSA's.
     */
    public function snapshot(Request $request, string $token): View|Response
    {
        $saved = TableReportController::recall(auth('expert')->id(), $token);

        if ($saved === null) {
            return response()->view('capsa.reports.expired', ['back' => TableReportController::safeBack($request->query('back'))], 410);
        }

        // Columns are c0, c1, ... so two columns with the same heading stay apart.
        $columns = [];
        foreach ($saved['headings'] as $i => $heading) {
            $columns['c'.$i] = ['label' => $heading, 'align' => 'left'];
        }
        $rows = array_map(fn ($line) => ['id' => null, 'cells' => array_combine(array_keys($columns), $line)], $saved['rows']);

        return $this->present($request, [
            'report' => 'snapshot',
            'name' => $saved['title'],
            'about' => 'Exactly what the page showed when you opened this report. Pick the columns you want, then preview or download it.',
            'scope' => $saved['scope'],
            'fileTag' => null,
            // A table kept from a page has no school year or section to look up, so the
            // report opens without the queries that would build them - it is the same
            // screen, and it comes up as fast as the page it was opened from.
            'ctx' => [
                'year' => '', 'term' => '', 'program' => '', 'section' => '', 'compare' => '', 'stage' => '', 'fee' => '',
                'years' => [], 'termOptions' => [], 'programOptions' => [], 'sectionOptions' => [], 'compareOptions' => [],
                'teams' => collect(), 'teamSections' => [],
            ],
            'table' => [
                'columns' => $columns,
                'rows' => $rows,
                'total' => null,
                'summary' => count($rows).' '.Str::plural('row', count($rows)),
                'empty' => 'The table had no rows when this report was opened.',
            ],
            'groups' => [],
            'teamReport' => false,
            'bySection' => false,
            'merged' => [],
            'baseUrl' => route('table.report.show', $token),
            'back' => $saved['back'],
            'portal' => $saved['portal'],
        ]);
    }

    /** @param  array<string, mixed>  $r */
    private function present(Request $request, array $r): View|Response
    {
        ['report' => $report, 'name' => $name, 'scope' => $scope, 'ctx' => $ctx, 'table' => $table, 'groups' => $groups,
            'teamReport' => $teamReport, 'bySection' => $bySection, 'merged' => $merged] = $r;

        // Column order and which are hidden; the first column names the row and always shows first.
        $keys = array_keys($table['columns']);
        $first = $keys[0];
        $order = array_values(array_intersect($this->list($request, 'order'), $keys));
        $order = array_values(array_unique([$first, ...array_diff($order, [$first]), ...array_diff($keys, $order)]));
        $hidden = array_values(array_diff(array_intersect($this->list($request, 'hide'), $keys), [$first]));
        $visible = array_values(array_diff($order, $hidden));

        $download = $this->text($request, 'download');

        if ($download === 'pdf' || $download === 'xlsx') {
            $rows = $this->searchAndSort($table['rows'], $visible, $this->text($request, 'q'), $this->text($request, 'sort'), $this->text($request, 'dir') === 'desc');
            $fileName = Str::slug($name).'_'.($r['fileTag'] ? $r['fileTag'].'_' : '').now()->toDateString();
            $sheet = $this->plain($table, $rows, $visible, $groups);

            if ($download === 'xlsx') {
                return $this->excel($name, $scope, $sheet, $fileName.'.xlsx');
            }

            $pdf = Pdf::loadView('capsa.reports.pdf', ['name' => $name, 'scope' => $scope, 'sheet' => $sheet, 'by' => auth('expert')->user()->name ?? 'the CaPSA/TSA'])
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
        $baseKeys = $teamReport ? array_keys(CapsaReports::TEAM_COLUMNS[$report]) : [];

        return view('capsa.reports.table', [
            // Where the download links point, and which portal's frame to draw it in.
            'baseUrl' => $r['baseUrl'] ?? route('capsa.reports.show', $report),
            'back' => $r['back'] ?? null,
            'portal' => $r['portal'] ?? 'capsa',
            'ctx' => $ctx,
            'report' => $report,
            'name' => $name,
            'about' => $r['about'],
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
                'columns' => collect(CapsaReports::TEAM_COLUMNS[$r])->except(['team', 'section', ...$baseKeys])->all(),
            ])->all(),
            'extra' => $this->reports->extraFilters($report, $ctx),
            'mergeExtra' => array_merge(...array_map(fn ($r) => $this->reports->extraFilters($r, $ctx), $mergeable ?: ['master'])),
            'scope' => $scope,
            'search' => $this->text($request, 'q'),
            'period' => $this->periodQuery($ctx),
            'details' => $bySection || in_array($report, ['experts', 'expert_list', 'snapshot'], true) ? [] : $this->reports->teamDetails($ctx, array_values(array_filter(array_column($table['rows'], 'id')))),
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

    /** A query value as text; anything else (a list, say, from a hand-edited link) reads as nothing. */
    private function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /**
     * A comma-separated query value as a list. A list-style parameter
     * (key[]=a&key[]=b) is read the same way.
     *
     * @return string[]
     */
    private function list(Request $request, string $key): array
    {
        $value = $request->query($key);
        $items = is_array($value) ? array_filter($value, 'is_string') : explode(',', is_string($value) ? $value : '');

        return array_values(array_filter(array_map('trim', $items), fn ($v) => $v !== ''));
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
        // A list of people as they are today, not a school year's numbers.
        if ($report === 'expert_list') {
            return 'All approved experts';
        }

        return implode(' · ', array_filter([
            'S.Y. '.$ctx['year'],
            $report === 'experts' ? 'compared with S.Y. '.$ctx['compare'] : $ctx['termOptions'][$ctx['term']],
            $ctx['program'] !== '' ? $ctx['program'] : $ctx['programOptions'][''],
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
                mb_strtolower(($row['group'] ?? '').' '.implode(' ', array_map(fn ($k) => CapsaReports::text($row['cells'][$k] ?? '', ' '), $visible))),
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
        $tone = fn ($cell) => is_array($cell) && in_array($cell[0], ['chip', 'tone'], true) ? $cell[2] : null;
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

        $sheet = [
            'headings' => array_map(fn ($k) => $table['columns'][$k]['label'], $visible),
            'right' => array_map(fn ($k) => $table['columns'][$k]['align'] === 'right', $visible),
            'bands' => $bands,
            'rows' => array_map(fn ($r) => $line($r['cells']), $rows),
            'total' => $table['total'] && $rows ? $line($table['total'], true) : null,
            'summary' => $table['summary'],
            'empty' => $table['empty'],
            'notes' => $table['notes'] ?? [],
        ];

        // The page shows each row's section as a row above its group; a download keeps it as a column beside the first, so a sorted or filtered sheet still says it.
        if ($rows && isset($rows[0]['group'])) {
            $at = fn (array $list, $value) => [$list[0], $value, ...array_slice($list, 1)];
            $sheet['headings'] = $at($sheet['headings'], 'Section');
            $sheet['right'] = $at($sheet['right'], false);
            $sheet['rows'] = array_map(fn ($line, $r) => $at($line, ['text' => $r['group'], 'tone' => null, 'bold' => false]), $sheet['rows'], $rows);
            $sheet['total'] = $sheet['total'] ? $at($sheet['total'], ['text' => '', 'tone' => null, 'bold' => true]) : null;
            if ($bands) {
                $sheet['bands'][0]['span']++;
            }
        }

        return $sheet;
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
        // Lines a report carries above its table, like the Expert Load's "Enough experts?".
        foreach ($sheetData['notes'] as $note) {
            $put(1, $head++, $note);
        }
        if ($sheetData['notes']) {
            $head++;
        }
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
