<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A table from any staff page, as a spreadsheet.
 *
 * The page reads its own table (what is on screen, filters and all), lets the
 * person tick the columns they want, and sends the headings and the rows here.
 * This only lays them out, the same way the Reports' Excel files look: title
 * and scope on top, a gold heading row kept in view, thin borders.
 */
final class TableWorkbook
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    public static function build(string $title, string $scope, array $headings, array $rows): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(self::sheetName($title));
        $width = max(1, count($headings));

        // Typed explicitly, so a cell reading "=SUM(...)" stays text instead of becoming a formula.
        $put = fn (int $column, int $row, string $value) => $sheet->setCellValueExplicit([$column, $row], $value, DataType::TYPE_STRING);

        $put(1, 1, $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $put(1, 2, $scope);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('6B7280');

        $head = 4;
        foreach ($headings as $i => $heading) {
            $put($i + 1, $head, $heading);
        }
        $sheet->getStyle([1, $head, $width, $head])->getFont()->setBold(true)->getColor()->setRGB('18191F');
        $sheet->getStyle([1, $head, $width, $head])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C9A82C');

        foreach ($rows as $r => $line) {
            foreach ($line as $c => $value) {
                $put($c + 1, $head + 1 + $r, $value);
            }
        }
        $last = $head + max(1, count($rows));

        if ($rows === []) {
            $put(1, $head + 1, 'No rows to show.');
        }

        $sheet->getStyle([1, $head, $width, $last])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('9AA0AD');
        $sheet->getStyle([1, $head + 1, $width, $last])->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        self::widths($sheet, $headings, $rows, $width);
        $sheet->freezePane('A'.($head + 1));

        return $book;
    }

    /** @param  list<list<string>>  $rows */
    private static function widths(Worksheet $sheet, array $headings, array $rows, int $width): void
    {
        // From the table alone, so the long title and scope lines above don't stretch the columns.
        foreach (range(1, $width) as $c) {
            $texts = [$headings[$c - 1] ?? '', ...array_map(fn ($line) => $line[$c - 1] ?? '', $rows)];
            $longest = max(array_map(fn ($s) => max(array_map('mb_strlen', explode("\n", $s))), $texts));
            $sheet->getColumnDimensionByColumn($c)->setWidth(min(60, max(10, $longest + 3)));
        }
    }

    /** A sheet name Excel accepts: no \ / ? * [ ] :, at most 31 characters. */
    private static function sheetName(string $title): string
    {
        $clean = trim(preg_replace('/[\\\\\/?*\[\]:]+/', ' ', $title));

        return mb_substr($clean !== '' ? $clean : 'Table', 0, 31);
    }
}
