<?php

namespace Database\Seeders;

use App\Models\ArchivedDocTag;
use App\Models\ArchivedDocument;
use App\Support\ArchiveTagGuesser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ArchivedDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/seeders/CCS-with-abstract.xlsx');
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Remove header
        array_shift($rows);

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            [$biblionumber, $title, $author, $year, $abstract, $note] = array_pad($row, 6, null);

            // Skip if no title
            if (empty(trim((string) $title))) {
                $skipped++;

                continue;
            }

            // Skip if no valid year (academic_year is NOT NULL)
            if (! is_numeric($year)) {
                $skipped++;

                continue;
            }

            // Detect program
            $program = 'BSIT';
            if ($note && str_contains(strtoupper((string) $note), 'BSCS')) {
                $program = 'BSCS';
            }

            $archivedId = DB::table('archived_documents')->insertGetId([
                'team_id' => null,
                'title' => trim((string) $title),
                'abstract' => $abstract ? trim((string) $abstract) : null,
                'academic_year' => (int) $year,
                'program' => $program,
                'file_name' => null,
                'file_path' => '',
                'file_size' => null,
            ], 'archived_id');

            // Tagged as it goes in, so the archive is counted in Analytics from the start.
            ArchivedDocument::find($archivedId)?->tags()->sync(ArchivedDocTag::idsFor(ArchiveTagGuesser::guess((string) $title, $abstract ? (string) $abstract : null)));

            $inserted++;
        }

        $this->command->info("Inserted: {$inserted} | Skipped: {$skipped}");
    }
}
