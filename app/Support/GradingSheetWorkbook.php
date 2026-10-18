<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The defense rubric form (GradingSheet) as a spreadsheet, laid out like the
 * department's own "PreProposal-Rubric.docx" and the PDF: the letterhead,
 * title, date and team alias, a block per category with its level points
 * across the top and a descriptor per criterion and level, one score per
 * category, the total, verdict, remarks and signatures. Landscape, one page
 * wide. A yellow cell takes each category's score, and the total adds itself
 * up - for a panel that can open Excel but not CapsTrack.
 */
class GradingSheetWorkbook
{
    private const LINE = '000000';

    private const TITLE_FILL = 'F2F2F2';

    private const SCORE_FILL = 'FFF8D6';

    public function build(array $sheet): Spreadsheet
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet();
        $ws->setTitle('Rating sheet');
        $ws->setShowGridlines(false);
        $ws->setPrintGridlines(false);
        $book->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(10);

        $levels = $sheet['levelCount'];
        // A: category; then one column per level; then Score.
        $first = 'A';
        $lastLevel = Coordinate::stringFromColumnIndex(1 + $levels);
        $scoreCol = Coordinate::stringFromColumnIndex(2 + $levels);
        $ws->getColumnDimension('A')->setWidth(18);
        for ($k = 2; $k <= 1 + $levels; $k++) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($k))->setWidth(max(18, (int) round(112 / $levels)));
        }
        $ws->getColumnDimension($scoreCol)->setWidth(9);

        // Letterhead.
        $this->logo($ws, 'cspc', 'A1', 4);
        $this->logo($ws, 'ccs', 'A1', 62);
        foreach ([
            ['Republic of the Philippines', false, 7],
            ['Camarines Sur Polytechnic Colleges', true, 8],
            ['Nabua, Camarines Sur', false, 7],
            ['ISO 9001:2015 Certified', false, 7],
            ['COLLEGE of COMPUTER STUDIES', true, 8],
        ] as $i => [$text, $bold, $size]) {
            $ws->setCellValue('B'.($i + 1), $text);
            $ws->mergeCells('B'.($i + 1).':'.$lastLevel.($i + 1));
            $ws->getStyle('B'.($i + 1))->getFont()->setBold($bold)->setSize($size);
        }
        $ws->getStyle("A5:{$scoreCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        // Title, date, team alias.
        $ws->setCellValue('A7', 'Title :');
        $beforeDate = Coordinate::stringFromColumnIndex(max(2, $levels));
        $ws->setCellValue('B7', $sheet['title']);
        $ws->mergeCells("B7:{$beforeDate}7");
        $ws->setCellValue($lastLevel.'7', 'Date : '.($sheet['date'] ?? ''));
        $ws->mergeCells("{$lastLevel}7:{$scoreCol}7");
        $ws->getStyle($lastLevel.'7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->setCellValue('A8', 'Team Alias :');
        $ws->setCellValue('B8', $sheet['team']);
        $ws->getStyle('B7:B8')->getFont()->setBold(true);
        $ws->getStyle($lastLevel.'7')->getFont()->setBold(true);

        $row = 10;
        $tableTop = $row;
        $ws->setCellValue('A'.$row, mb_strtoupper($sheet['rubric']));
        $ws->mergeCells("A{$row}:{$scoreCol}{$row}");
        $ws->getStyle("A{$row}")->getFont()->setBold(true);
        $ws->getStyle("A{$row}:{$scoreCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TITLE_FILL);
        $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        if ($sheet['description']) {
            $ws->setCellValue('A'.$row, mb_strtoupper($sheet['description']));
            $ws->mergeCells("A{$row}:{$scoreCol}{$row}");
            $ws->getStyle("A{$row}")->getFont()->setBold(true);
            $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $scoreCells = [];
        foreach ($sheet['sections'] as $section) {
            $head = $row;
            $items = count($section['items']);

            // The category's name, down the whole block.
            $ws->setCellValue('A'.$head, $section['name']);
            $ws->mergeCells('A'.$head.':A'.($head + $items));
            $ws->getStyle('A'.$head)->getFont()->setBold(true);
            $ws->getStyle('A'.$head)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

            for ($k = 0; $k < $levels; $k++) {
                $col = Coordinate::stringFromColumnIndex(2 + $k);
                if (isset($section['levelTotals'][$k])) {
                    $ws->setCellValue($col.$head, $section['levelTotals'][$k]);
                }
            }
            $ws->setCellValue($scoreCol.$head, 'Score');
            $ws->getStyle("B{$head}:{$scoreCol}{$head}")->getFont()->setBold(true);
            $ws->getStyle("B{$head}:{$scoreCol}{$head}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;

            foreach ($section['items'] as $item) {
                for ($k = 0; $k < $levels; $k++) {
                    $level = $item['levels'][$k] ?? null;
                    if (! $level) {
                        continue;
                    }
                    $text = $level['desc'] !== '' ? $level['desc'] : ($level['label'] !== '' ? $level['label'].' - '.$item['name'] : $item['name']);
                    // The descriptor in plain type, its points in bold, as on the form.
                    $rich = new RichText;
                    $rich->createText($text.' ');
                    $rich->createTextRun('('.GradingSheet::points($level['score']).'pts)')->getFont()->setBold(true)->setName('Times New Roman')->setSize(10);
                    $ws->setCellValue(Coordinate::stringFromColumnIndex(2 + $k).$row, $rich);
                }
                $ws->getStyle("B{$row}:{$lastLevel}{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP)->setHorizontal(Alignment::HORIZONTAL_JUSTIFY);
                $ws->getStyle("B{$row}:{$lastLevel}{$row}")->getFont()->setSize(10);
                $row++;
            }

            // One score for the category, as on the form.
            if ($items > 0) {
                $ws->mergeCells($scoreCol.($head + 1).':'.$scoreCol.($head + $items));
                $ws->getStyle($scoreCol.($head + 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::SCORE_FILL);
                $ws->getStyle($scoreCol.($head + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $scoreCells[] = $scoreCol.($head + 1);
            }
        }

        $ws->setCellValue('A'.$row, 'Total');
        $ws->setCellValue('B'.$row, $sheet['total']);
        $ws->setCellValue($scoreCol.$row, $scoreCells ? '='.implode('+', $scoreCells) : 0);
        $ws->getStyle("A{$row}:{$scoreCol}{$row}")->getFont()->setBold(true);
        $ws->getStyle("A{$row}:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle($scoreCol.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("A{$tableTop}:{$scoreCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        $row += 2;

        $ws->setCellValue('A'.$row, 'Verdict:');
        $ws->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;
        foreach ($sheet['outcomes'] as $outcome) {
            $ws->setCellValue('A'.$row, '      ☐  '.$outcome);
            $ws->mergeCells("A{$row}:{$lastLevel}{$row}");
            $row++;
        }
        $row++;

        $ws->setCellValue('A'.$row, 'Remarks:');
        $ws->mergeCells("A{$row}:{$scoreCol}{$row}");
        $ws->getStyle('A'.$row)->getFont()->setBold(true);
        $remarksTop = $row;
        $row++;
        $ws->mergeCells("A{$row}:{$scoreCol}".($row + 3));
        $ws->getStyle("A{$remarksTop}:{$scoreCol}".($row + 3))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        $row += 6;

        // Signatures: Panel Member 1 and 2 side by side, the chairman under them.
        $left = 'A';
        $leftEnd = Coordinate::stringFromColumnIndex(max(2, (int) floor(($levels + 2) / 2) - 0));
        $rightStart = Coordinate::stringFromColumnIndex(min($levels + 2, (int) floor(($levels + 2) / 2) + 2));
        $this->signature($ws, $row, $left, $leftEnd, $sheet['signatures']['Panel Member 1'] ?? '', 'Panel Member 1');
        $this->signature($ws, $row, $rightStart, $scoreCol, $sheet['signatures']['Panel Member 2'] ?? '', 'Panel Member 2');
        $row += 4;
        $midStart = Coordinate::stringFromColumnIndex(2);
        $midEnd = Coordinate::stringFromColumnIndex($levels + 1);
        $this->signature($ws, $row, $midStart, $midEnd, $sheet['signatures']['Panel Chair'] ?? '', 'Panel Chairman');
        $row += 3;

        $ws->setCellValue('A'.$row, $sheet['note']);
        $ws->mergeCells("A{$row}:{$scoreCol}{$row}");
        $ws->getStyle('A'.$row)->getFont()->setSize(7.5)->getColor()->setRGB('555555');

        $this->printSetup($ws, $sheet);
        $ws->setSelectedCell($scoreCells[0] ?? 'A1');

        return $book;
    }

    private function signature(Worksheet $ws, int $row, string $from, string $to, string $name, string $seat): void
    {
        $ws->setCellValue($from.$row, mb_strtoupper($name));
        $ws->mergeCells("{$from}{$row}:{$to}{$row}");
        $ws->getStyle("{$from}{$row}")->getFont()->setBold(true);
        $ws->getStyle("{$from}{$row}:{$to}{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $ws->setCellValue($from.($row + 1), $seat);
        $ws->mergeCells("{$from}".($row + 1).":{$to}".($row + 1));
        $ws->getStyle("{$from}{$row}:{$to}".($row + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function logo(Worksheet $ws, string $which, string $cell, int $offsetX): void
    {
        $path = public_path('images/brand/'.($which === 'ccs' ? 'ccs.png' : 'cspc.png'));
        if (! is_file($path)) {
            return;
        }

        $drawing = new Drawing;
        $drawing->setPath($path);
        $drawing->setHeight(44);
        $drawing->setCoordinates($cell);
        $drawing->setOffsetX($offsetX);
        $drawing->setOffsetY(2);
        $drawing->setWorksheet($ws);
    }

    private function printSetup(Worksheet $ws, array $sheet): void
    {
        $ws->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $ws->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
        $ws->getHeaderFooter()->setOddFooter('&L&7'.$sheet['team'].' · '.$sheet['stage'].' · '.$sheet['reference'].'&R&8Page &P of &N');
    }
}
