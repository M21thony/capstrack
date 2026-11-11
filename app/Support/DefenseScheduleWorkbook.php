<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The defense schedule as a spreadsheet: the department's own columns, in
 * CapsTrack's look.
 *
 * The columns are the ones CCS has always kept ("TD_2526 - Schedule -
 * CCS.xlsx"): section, schedule, venue, status, a row per member with their
 * payment, alias, title, the three experts, the three panelists, secretary
 * and similarity. Around them: a title band with the counts, a dark header
 * that repeats on every printed page, a gold band per day, and the teams
 * still waiting for a date - cancelled, up for a redefense, or not yet
 * scheduled - at the end. A returnee is marked after their name.
 *
 * Every value comes from DefenseScheduleExport::rows(); this class only
 * arranges and styles it. The sample's per-defense Secretary is not a role
 * this app records, so that column is there to write in by hand.
 */
class DefenseScheduleWorkbook
{
    /** The header row; the title band sits above it. */
    public const HEADER_ROW = 5;

    private const MIN_ROWS = 3;

    private const INK = '1F2028';

    private const INK2 = '4B4D57';

    private const INK3 = '7A7C86';

    private const LINE = 'E4E4E7';

    private const LINE2 = 'C9CAD1';

    private const GOLD = 'C9A82C';

    private const GOLD_INK = '7D630E';

    private const GOLD_SOFT = 'FAF6E8';

    private const WAITING_FILL = 'F1F1F4';

    private const VIOLET = '6D28D9';

    /** Status fill and text, by DefenseOutcome::tone() and the export's own keys. */
    private const STATUS_TONES = [
        'pass' => ['E8F5EC', '1E7A3C'],
        'major' => ['FDF2D6', '92400E'],
        'fail' => ['FDECEC', 'B42318'],
        'ongoing' => ['EDF2FE', '1D4ED8'],
        'none' => ['F1F1F4', '5B5D66'],
    ];

    /**
     * Section colours, soft fill and text. Picked by the section's own name,
     * so a section is always the same colour across a re-export.
     */
    private const SECTION_PALETTE = [['EDF2FE', '1D4ED8'], ['F1EBFF', '6D28D9'], ['E8F5EC', '1E7A3C'], ['FDF2D6', '92400E'], ['FDECEC', 'B42318'], ['E6F6F8', '0E7490']];

    private const WIDTHS = ['A' => 11, 'B' => 25, 'C' => 11, 'D' => 20, 'E' => 34, 'F' => 10, 'G' => 17, 'H' => 36, 'I' => 12, 'J' => 22, 'K' => 10, 'L' => 24, 'M' => 2, 'N' => 16, 'O' => 11];

    /**
     * @param  array<int, array<string, mixed>>  $rows  From DefenseScheduleExport::rows().
     * @param  array{start: ?string, end: ?string}|null  $window
     */
    public function build(array $rows, string $stage, string $scopeLabel, ?array $window = null): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10)->getColor()->setRGB(self::INK);
        $book->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($stage, 0, 31));
        $sheet->setShowGridlines(false);

        foreach (self::WIDTHS as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $this->titleBand($sheet, $rows, $stage, $scopeLabel, $window);
        $this->header($sheet, $stage);

        $row = self::HEADER_ROW + 1;
        $groups = collect($rows)->groupBy(fn (array $team) => $team['schedule'] !== null ? $team['schedule']['date']->toDateString() : 'waiting');

        foreach ($groups as $day => $teams) {
            $row = $day === 'waiting'
                ? $this->band($sheet, $row, 'WAITING FOR A DATE   ·   '.$teams->count().' '.($teams->count() === 1 ? 'team' : 'teams'), self::WAITING_FILL, self::INK2)
                : $this->band($sheet, $row, mb_strtoupper($teams->first()['schedule']['date']->format('l, F j, Y')).'   ·   '.$teams->count().' '.($teams->count() === 1 ? 'defense' : 'defenses'), self::GOLD_SOFT, self::GOLD_INK);

            foreach ($teams as $team) {
                $row = $this->writeTeam($sheet, $row, $team);
            }
        }

        $last = $row - 1;
        $this->footnote($sheet, $row + 1);

        $sheet->freezePane('E'.(self::HEADER_ROW + 1));
        if ($last > self::HEADER_ROW) {
            $sheet->setAutoFilter('A'.self::HEADER_ROW.':O'.$last);
        }

        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_FOLIO)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);
        $sheet->getHeaderFooter()->setOddFooter('&L&8'.$stage.' Schedule · '.$scopeLabel.'&R&8Page &P of &N');
        $sheet->setSelectedCell('A1');

        return $book;
    }

    /** The name, what it covers, and how many teams stand where. */
    private function titleBand(Worksheet $sheet, array $rows, string $stage, string $scopeLabel, ?array $window): void
    {
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', $stage.' Schedule');
        $sheet->getStyle('A1')->getFont()->setSize(18)->setBold(true);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->mergeCells('I1:O1');
        $sheet->setCellValue('I1', 'CapsTrack · College of Computer Studies');
        $sheet->getStyle('I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::GOLD_INK]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $parts = [$scopeLabel];
        if ($window && $window['start'] && $window['end']) {
            $parts[] = 'Window '.Carbon::parse($window['start'])->format('M j').' – '.Carbon::parse($window['end'])->format('M j, Y');
        }
        $parts[] = 'Exported '.now()->format('F j, Y, g:i A');
        $sheet->mergeCells('A2:O2');
        $sheet->setCellValue('A2', implode('   ·   ', $parts));
        $sheet->getStyle('A2')->getFont()->setColor(new Color(self::INK3));
        $sheet->getRowDimension(2)->setRowHeight(18);

        $count = fn (callable $test) => count(array_filter($rows, $test));
        $scheduled = $count(fn ($r) => $r['schedule'] !== null);
        $returnees = array_sum(array_map(fn ($r) => collect($r['members'])->where('returnee', true)->count(), $rows));
        $bits = [
            [count($rows).' '.(count($rows) === 1 ? 'team' : 'teams'), self::INK],
            [$scheduled.' scheduled', '1D4ED8'],
            [$count(fn ($r) => $r['status']['key'] === 'cancelled').' cancelled', 'B42318'],
            [$count(fn ($r) => $r['status']['key'] === 'redefense').' waiting to redefend', '92400E'],
            [$count(fn ($r) => $r['status']['key'] === 'not_scheduled').' not scheduled', self::INK3],
            [$returnees.' '.($returnees === 1 ? 'returnee' : 'returnees'), self::VIOLET],
        ];

        // The total and the scheduled always show; the rest only when there are any.
        $bits = array_values(array_filter($bits, fn (array $bit, int $i) => $i < 2 || ! str_starts_with($bit[0], '0 '), ARRAY_FILTER_USE_BOTH));

        $text = new RichText;
        foreach ($bits as $i => [$label, $color]) {
            if ($i) {
                $text->createTextRun('   ·   ')->getFont()->setName('Arial')->setSize(10)->setColor(new Color(self::LINE2));
            }
            $text->createTextRun($label)->getFont()->setName('Arial')->setSize(10)->setBold(true)->setColor(new Color($color));
        }
        $sheet->mergeCells('A3:O3');
        $sheet->setCellValue('A3', $text);
        $sheet->getRowDimension(3)->setRowHeight(20);

        // A thin gold rule under the title, the portal's own accent.
        $sheet->getRowDimension(4)->setRowHeight(4);
        $sheet->getStyle('A4:O4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GOLD);
    }

    private function header(Worksheet $sheet, string $stage): void
    {
        $paid = DefenseScheduleExport::isPaidStage($stage);
        $h = self::HEADER_ROW;

        $sheet->fromArray([[
            'Section', 'Schedule', 'Venue', 'STATUS', 'Name', $paid ? $stage.' Payment' : 'Payment', 'Team Alias', 'Title',
            'Experts', '', 'Panel', '', '', 'Secretary', 'Similarity %',
        ]], null, 'A'.$h);
        $sheet->mergeCells("I$h:J$h");
        $sheet->mergeCells("K$h:M$h");

        $sheet->getStyle("A$h:O$h")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::INK]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'indent' => 1],
        ]);
        $sheet->getRowDimension($h)->setRowHeight(28);
    }

    /** A full-width band naming the day below it, or the teams still waiting. */
    private function band(Worksheet $sheet, int $row, string $text, string $fill, string $color): int
    {
        $sheet->mergeCells("A$row:O$row");
        $sheet->setCellValue("A$row", $text);
        $sheet->getStyle("A$row:O$row")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $color]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
            'alignment' => ['indent' => 1],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::LINE2]],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::LINE2]],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);

        return $row + 1;
    }

    /** @param  array<string, mixed>  $team */
    private function writeTeam(Worksheet $sheet, int $start, array $team): int
    {
        $rows = max(self::MIN_ROWS, $team['row_count']);
        $end = $start + $rows - 1;
        [$sectionFill, $sectionInk] = self::SECTION_PALETTE[crc32($team['section']) % count(self::SECTION_PALETTE)];
        [$statusFill, $statusInk] = self::STATUS_TONES[$team['status']['tone']] ?? self::STATUS_TONES['none'];
        $scheduled = $team['schedule'] !== null;

        // The whole-team facts: one value, merged down every row the block has.
        $this->merged($sheet, "A$start:A$end", $team['section'], ['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $sectionInk]], 'fill' => $sectionFill]);
        $this->merged($sheet, "B$start:B$end", $scheduled
            ? $team['schedule']['date']->format('g:i A').' – '.$team['schedule']['end']->format('g:i A')
            : 'Not yet scheduled'.($team['note'] ? "\n".$team['note'] : ''),
            $scheduled ? ['font' => ['bold' => true]] : ['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => self::INK3]]]);
        $this->merged($sheet, "C$start:C$end", $team['venue'] ?? '—', ['font' => ['bold' => $scheduled, 'color' => ['rgb' => $scheduled ? self::INK : self::INK3]]]);
        $this->merged($sheet, "D$start:D$end", $team['status']['label'], ['font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => $statusInk]], 'fill' => $statusFill]);
        $this->merged($sheet, "G$start:G$end", $team['alias'], ['font' => ['bold' => true]]);
        $this->merged($sheet, "H$start:H$end", $team['title'] ?? '—', ['font' => ['size' => 9, 'color' => ['rgb' => self::INK2]]]);
        $this->merged($sheet, "N$start:N$end", '');
        $this->merged($sheet, "O$start:O$end", $team['similarity'] !== null ? $team['similarity'] / 100 : null, [
            'numberFormat' => ['formatCode' => '0%'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'font' => ['color' => ['rgb' => self::INK2]],
        ]);

        $experts = [['Adviser:', $team['experts']['adviser'] ?? ''], ['Consultant:', $team['experts']['consultant'] ?? ''], ['Grammarian:', $team['experts']['grammarian'] ?? '']];
        $panel = [['Chair:', $team['panel']['chair'] ?? ''], ['Member 1:', $team['panel']['m1'] ?? ''], ['Member 2:', $team['panel']['m2'] ?? '']];

        foreach (range(0, $rows - 1) as $i) {
            $r = $start + $i;
            $member = $team['members'][$i] ?? null;

            if ($member) {
                $this->member($sheet, "E$r", $member);

                if ($member['paid'] !== null) {
                    $sheet->setCellValue("F$r", $member['paid'] ? 'PAID' : 'UNPAID');
                    $sheet->getStyle("F$r")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => $member['paid'] ? '1E7A3C' : 'B42318']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
            }
            $sheet->getStyle("E$r")->getAlignment()->setIndent(1);

            // The three expert roles and the three panel seats sit on the
            // block's first three rows, whatever its height.
            if ($i < 3) {
                foreach ([['I', 'J', $experts[$i]], ['K', 'L', $panel[$i]]] as [$labelCol, $valueCol, [$label, $value]]) {
                    $sheet->setCellValue("$labelCol$r", $label);
                    $sheet->getStyle("$labelCol$r")->applyFromArray(['font' => ['size' => 8, 'bold' => true, 'color' => ['rgb' => self::INK3]], 'alignment' => ['indent' => 1]]);
                    $sheet->setCellValue("$valueCol$r", $value !== '' ? $value : 'None yet');
                    if ($value === '') {
                        $sheet->getStyle("$valueCol$r")->getFont()->setItalic(true)->setColor(new Color('B42318'));
                    }
                }
            }

            $sheet->getRowDimension($r)->setRowHeight(17);
        }

        // Faint rules between the members, a light line between columns,
        // and a firmer one under the team.
        $sheet->getStyle("E$start:F$end")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::LINE);
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'K', 'N', 'O'] as $col) {
            $sheet->getStyle("$col$start:$col$end")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        }
        $sheet->getStyle("O$start:O$end")->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        $sheet->getStyle("A$end:O$end")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE2);

        return $end + 1;
    }

    /** A member's name, with "(Returnee)" after it in violet when they are one. */
    private function member(Worksheet $sheet, string $cell, array $member): void
    {
        if (empty($member['returnee'])) {
            $sheet->setCellValue($cell, $member['name']);

            return;
        }

        $text = new RichText;
        $text->createTextRun($member['name'].'  ')->getFont()->setName('Arial')->setSize(10)->setColor(new Color(self::INK));
        $text->createTextRun('(Returnee)')->getFont()->setName('Arial')->setSize(9)->setBold(true)->setColor(new Color(self::VIOLET));
        $sheet->setCellValue($cell, $text);
    }

    /** What the statuses mean, for whoever reads the printout. */
    private function footnote(Worksheet $sheet, int $row): void
    {
        $sheet->mergeCells("A$row:O$row");
        $sheet->setCellValue("A$row", 'CANCELLED: the CaPA did not give the go, or the requirements were not complete on the defense day; RESCHED is the new date the CaPSA/TSA set. REDEFENSE: the team waits for a new date before the deadline shown. (Returnee): a student back from an earlier school year.');
        $sheet->getStyle("A$row")->getFont()->setSize(8)->setItalic(true)->setColor(new Color(self::INK3));
    }

    /**
     * @param  array{font?: array<string, mixed>, fill?: string, numberFormat?: array<string, string>, alignment?: array<string, mixed>}  $style
     */
    private function merged(Worksheet $sheet, string $range, string|float|null $value, array $style = []): void
    {
        $sheet->mergeCells($range);
        [$anchor] = explode(':', $range);
        $sheet->setCellValue($anchor, $value);

        $apply = [
            'alignment' => array_merge(['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'indent' => 1], $style['alignment'] ?? []),
        ];
        if (isset($style['font'])) {
            $apply['font'] = $style['font'];
        }
        if (isset($style['fill'])) {
            $apply['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $style['fill']]];
        }
        if (isset($style['numberFormat'])) {
            $apply['numberFormat'] = $style['numberFormat'];
        }

        $sheet->getStyle($range)->applyFromArray($apply);
    }
}
