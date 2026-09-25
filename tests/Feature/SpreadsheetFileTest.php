<?php

namespace Tests\Feature;

use App\Support\SpreadsheetFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Both list uploads (students and experts) open their file through
 * App\Support\SpreadsheetFile, so an ñ has to survive every way Excel saves
 * a list - and an upload's temporary file has no extension to go by.
 */
class SpreadsheetFileTest extends TestCase
{
    private const LINES = [
        'Email,Last Name,First Name',
        'juan.pena@my.cspc.edu.ph,Peña,Niño',
    ];

    /** Written the way an upload arrives: bytes in a temp file with no extension. */
    private function upload(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($path, $bytes);

        return $path;
    }

    private function secondRow(string $path): array
    {
        return SpreadsheetFile::load($path)->getActiveSheet()->toArray()[1];
    }

    public function test_a_windows_1252_csv_is_read_with_its_n_tilde(): void
    {
        $bytes = mb_convert_encoding(implode("\r\n", self::LINES)."\r\n", 'Windows-1252', 'UTF-8');

        $this->assertFalse(mb_check_encoding($bytes, 'UTF-8'), 'The fixture has to be something other than UTF-8.');
        $this->assertSame(['juan.pena@my.cspc.edu.ph', 'Peña', 'Niño'], $this->secondRow($this->upload($bytes)));
    }

    public function test_a_utf8_csv_is_read_as_it_is(): void
    {
        $this->assertSame(
            ['juan.pena@my.cspc.edu.ph', 'Peña', 'Niño'],
            $this->secondRow($this->upload(implode("\n", self::LINES)."\n"))
        );
    }

    public function test_the_byte_order_mark_of_a_csv_utf8_file_is_not_part_of_the_first_heading(): void
    {
        $rows = SpreadsheetFile::load($this->upload("\xEF\xBB\xBF".implode("\n", self::LINES)."\n"))
            ->getActiveSheet()->toArray();

        $this->assertSame('Email', $rows[0][0]);
        $this->assertSame('Peña', $rows[1][1]);
    }

    public function test_a_workbook_is_read_as_it_is(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Email', 'Last Name', 'First Name'], ['juan.pena@my.cspc.edu.ph', 'Peña', 'Niño']]);
        $path = tempnam(sys_get_temp_dir(), 'php');
        (new Xlsx($book))->save($path);

        $this->assertSame(['juan.pena@my.cspc.edu.ph', 'Peña', 'Niño'], $this->secondRow($path));
    }
}
