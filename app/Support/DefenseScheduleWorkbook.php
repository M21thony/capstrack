<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The Defense Scheduling summary, laid out the way CCS has always kept it -
 * a spreadsheet per stage, one block of rows per team, matching the sample
 * "TD_2526 - Schedule - CCS.xlsx" column for column.
 *
 * Every value it writes comes from DefenseScheduleExport::rows(); this class
 * only arranges and styles it. Two things the sample carries have no source
 * in the app and are left blank rather than invented: the sample calls the
 * adviser column "CAPA", which this app uses for an unrelated role (the
 * CaPSA who runs a section), so that column is headed "Adviser" instead; and
 * the sample's per-defense Secretary is not a role this app records at all,
 * so that column exists - a CaPSA may still want to write a name in by hand
 * - but is never filled in from data that is not there.
 */
class DefenseScheduleWorkbook
{
    private const HEADER_FILL = '84E291';

    private const MIN_ROWS = 3;

    /**
     * Section colour, cycling through the same palette the sample used
     * (a pink and a blue) so different sections stay visually apart. Picked
     * by the section's own name, so the same section is always the same
     * colour across a re-export - not by row position, which would repaint
     * a section if an earlier one on the sheet were filtered out.
     */
    private const SECTION_PALETTE = ['EAD1DC', 'C1E4F5', 'D9D2E9', 'FCE5CD', 'D0E0E3', 'F4CCCC'];

    private const STATUS_TONES = [
        'pass' => ['fill' => 'D9EAD3', 'font' => '274E13'],
        'major' => ['fill' => 'FFF2CC', 'font' => '7F6000'],
        'fail' => ['fill' => 'FCE5CD', 'font' => 'B45309'],
        'ongoing' => ['fill' => 'CFE2F3', 'font' => '0B5394'],
        'none' => ['fill' => 'F3F3F3', 'font' => '666666'],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows  From DefenseScheduleExport::rows().
     */
    public function build(array $rows, string $stage, string $scopeLabel): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($stage, 0, 31));

        $this->header($sheet, $stage);

        $row = 2;
        $previousDate = null;
        $hasPrevious = false;
        foreach ($rows as $team) {
            // Null means "not scheduled" here, a bucket of its own - not the
            // same as $hasPrevious being false, which only the very first
            // team is.
            $date = $team['schedule'] !== null ? $team['schedule']['date']->toDateString() : null;

            // A slim spacer row, but only where the sample's own file has
            // one: between one date and the next, not between every team -
            // several teams sharing a day sit back to back with nothing
            // between them.
            if ($hasPrevious && $date !== $previousDate) {
                $sheet->getRowDimension($row)->setRowHeight(10.5);
                $row++;
            }
            $previousDate = $date;
            $hasPrevious = true;

            $row = $this->writeTeam($sheet, $row, $team, $stage);
        }

        $this->columnWidths($sheet);
        $sheet->freezePane('H2');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getHeaderFooter()->setOddHeader('&C&B'.$stage.' Schedule - '.$scopeLabel);
        $sheet->getSheetView()->setZoomScale(100);

        return $book;
    }

    private function header(Worksheet $sheet, string $stage): void
    {
        $paid = DefenseScheduleExport::isPaidStage($stage);

        $sheet->fromArray([[
            'Section', 'Schedule', 'Venue', 'STATUS', 'Name', $paid ? $stage.' Payment' : 'Payment', 'Team Alias', 'Title',
            '', 'Experts', '', 'Panel', '', 'Secretary', 'Similarity %',
        ]], null, 'A1');

        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        // "Experts" and "Panel" read as section titles over their pair of
        // columns, left-set rather than centred - the sample's own choice.
        $sheet->getStyle('I1:M1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_GENERAL);
        $sheet->getRowDimension(1)->setRowHeight(24.75);
    }

    /** @param  array<string, mixed>  $team */
    private function writeTeam(Worksheet $sheet, int $start, array $team, string $stage): int
    {
        $rows = max(self::MIN_ROWS, $team['row_count']);
        $end = $start + $rows - 1;
        $sectionColor = self::SECTION_PALETTE[crc32($team['section']) % count(self::SECTION_PALETTE)];

        // The whole-team facts: one value, merged down every row the block has.
        $this->merged($sheet, "A$start:A$end", $team['section'], ['fill' => $sectionColor]);
        $this->merged($sheet, "B$start:B$end", $this->scheduleText($team['schedule']), ['bold' => true]);
        $this->merged($sheet, "C$start:C$end", $team['venue'] ?? '—', ['bold' => true, 'size' => 9]);
        $this->merged($sheet, "D$start:D$end", $team['status']['label'], [
            'bold' => true, 'size' => 9,
            'fill' => self::STATUS_TONES[$team['status']['tone']]['fill'] ?? self::STATUS_TONES['none']['fill'],
            'font_color' => self::STATUS_TONES[$team['status']['tone']]['font'] ?? self::STATUS_TONES['none']['font'],
        ]);
        $this->merged($sheet, "G$start:G$end", $team['alias'], ['fill' => $sectionColor]);
        $this->merged($sheet, "H$start:H$end", $team['title'] ?? '—', ['size' => 9, 'wrap' => true]);
        $this->merged($sheet, "N$start:N$end", '');
        $this->merged($sheet, "O$start:O$end", $team['similarity'] !== null ? $team['similarity'] / 100 : null, [
            'fill' => $sectionColor, 'numfmt' => '0%',
        ]);

        // One row per member.
        foreach (range(0, $rows - 1) as $i) {
            $r = $start + $i;
            $member = $team['members'][$i] ?? null;

            $sheet->setCellValue("E$r", $member['name'] ?? '');
            $sheet->getStyle("E$r")->applyFromArray(['font' => ['name' => 'Arial', 'size' => 10]]);

            if ($member && $member['paid'] !== null) {
                $sheet->setCellValue("F$r", $member['paid'] ? 'PAID' : 'UNPAID');
                $sheet->getStyle("F$r")->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => $member['paid'] ? '274E13' : 'CC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }
        }

        // The three expert roles and the three panel seats: one label and one
        // value per row, confined to the block's first three rows, whatever
        // its height - a fourth member does not push a fourth role into being.
        $roleRow = fn (int $i) => $start + $i;
        $experts = [['Adviser:', $team['experts']['adviser'] ?? ''], ['Consultant:', $team['experts']['consultant'] ?? ''], ['Grammarian:', $team['experts']['grammarian'] ?? '']];
        $panel = [['Chair:', $team['panel']['chair'] ?? ''], ['Member 1:', $team['panel']['m1'] ?? ''], ['Member 2:', $team['panel']['m2'] ?? '']];

        foreach ($experts as $i => [$label, $value]) {
            $this->label($sheet, 'I'.$roleRow($i), $label);
            $this->value($sheet, 'J'.$roleRow($i), $value);
        }
        foreach ($panel as $i => [$label, $value]) {
            $this->label($sheet, 'K'.$roleRow($i), $label);
            $this->value($sheet, 'L'.$roleRow($i), $value);
        }

        // A thin border on every cell in the block. For a merged range (A,
        // B, C, D, G, H, N, O) Excel only ever draws a merged cell's outer
        // edge regardless of what its hidden interior cells carry, so this
        // reads as one clean box - exactly the look the sample gets from
        // setting borders only on each block's first and last row. For the
        // per-member columns (E, F) it reproduces the sample's own choice of
        // a full grid line between every member.
        $sheet->getStyle("A$start:O$end")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
        ]);

        foreach (range($start, $end) as $r) {
            $sheet->getRowDimension($r)->setRowHeight(15.75);
        }

        return $end + 1;
    }

    private function scheduleText(?array $schedule): string
    {
        if (! $schedule) {
            return 'Not yet scheduled';
        }

        return $schedule['date']->format('l, F j,')."\n".$schedule['date']->format('g:i A').' - '.$schedule['end']->format('g:i A');
    }

    /**
     * @param  array{fill?: string, font_color?: string, bold?: bool, size?: int, wrap?: bool, numfmt?: string}  $style
     */
    private function merged(Worksheet $sheet, string $range, string|float|null $value, array $style = []): void
    {
        $sheet->mergeCells($range);
        [$anchor] = explode(':', $range);
        $sheet->setCellValue($anchor, $value);

        $font = ['name' => 'Arial', 'size' => $style['size'] ?? 10];
        if (! empty($style['bold'])) {
            $font['bold'] = true;
        }
        if (isset($style['font_color'])) {
            $font['color'] = ['rgb' => $style['font_color']];
        }

        $apply = [
            'font' => $font,
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => ! empty($style['wrap']),
            ],
        ];
        if (isset($style['fill'])) {
            $apply['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $style['fill']]];
        }
        if (isset($style['numfmt'])) {
            $apply['numberFormat'] = ['formatCode' => $style['numfmt']];
        }

        $sheet->getStyle($range)->applyFromArray($apply);
    }

    private function label(Worksheet $sheet, string $cell, string $text): void
    {
        $sheet->setCellValue($cell, $text);
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function value(Worksheet $sheet, string $cell, string $text): void
    {
        $sheet->setCellValue($cell, $text);
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function columnWidths(Worksheet $sheet): void
    {
        foreach ([
            'A' => 11, 'B' => 18, 'C' => 12, 'D' => 15, 'E' => 27, 'F' => 9, 'G' => 12,
            'H' => 34, 'I' => 12, 'J' => 26, 'K' => 10, 'L' => 26, 'M' => 3, 'N' => 18, 'O' => 12,
        ] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }
}
