<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * An uploaded list, opened whatever it was saved as.
 *
 * The student and expert lists come from Excel. A workbook (.xlsx) carries
 * its own text encoding, but a CSV does not, and Excel on Windows saves
 * "CSV (Comma delimited)" in Windows-1252 rather than UTF-8. Read as UTF-8,
 * every ñ in that file - Peña, Nuñez, Ibáñez - came out as a replacement
 * character, so the names were stored wrong and never matched anybody.
 *
 * A CSV is now read as UTF-8 when it is (with or without the byte-order
 * mark "CSV UTF-8" adds), and as Windows-1252 when it is not.
 */
final class SpreadsheetFile
{
    /** What Excel on Windows saves a plain CSV in. */
    private const WINDOWS_CSV_ENCODING = 'CP1252';

    public static function load(string $path): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);

        if ($reader instanceof Csv) {
            $reader->setInputEncoding(Csv::GUESS_ENCODING);
            $reader->setFallbackEncoding(self::WINDOWS_CSV_ENCODING);
        }

        return $reader->load($path);
    }
}
