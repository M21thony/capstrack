<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CapstonePdfImportSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/capstone_merged.json');
        $entries = json_decode(file_get_contents($path), true);

        DB::table('archived_documents')->truncate();

        $inserted = 0;
        foreach ($entries as $e) {
            DB::table('archived_documents')->insert([
                'team_id' => null,
                'title' => $e['title'],
                'abstract' => $e['abstract'] ?: null,
                'academic_year' => $e['year'],
                'program' => 'BSIT',
                'file_name' => null,
                'file_path' => '',
                'file_size' => null,
            ]);
            $inserted++;
        }

        $this->command->info("Cleared archived_documents and inserted {$inserted} entries from the PDF.");
    }
}
