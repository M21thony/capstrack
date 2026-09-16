<?php

namespace Database\Seeders;

use App\Models\ArchivedDocument;
use Illuminate\Database\Seeder;

/**
 * The researchers of each archived capstone, from the College of Computer
 * Studies capstone list (database/seeders/data/archived_authors.json).
 *
 * Matched on the title, not the id, so it lands on the right study in any
 * copy of the archive. Only the authors column is written.
 *
 *   php artisan db:seed --class=ArchivedAuthorsSeeder
 */
class ArchivedAuthorsSeeder extends Seeder
{
    public function run(): void
    {
        $key = fn (?string $title) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $title));
        $rows = collect(json_decode(file_get_contents(database_path('seeders/data/archived_authors.json')), true))
            ->keyBy(fn ($r) => $key($r['title']));

        $updated = 0;
        $missing = [];

        foreach (ArchivedDocument::all() as $document) {
            $row = $rows->get($key($document->title));

            if (! $row || $row['authors'] === '') {
                $missing[] = $document->title;

                continue;
            }

            if ($document->authors !== $row['authors']) {
                $document->update(['authors' => $row['authors']]);
                $updated++;
            }
        }

        $this->command?->info("Authors written for {$updated} studies.");
        foreach ($missing as $title) {
            $this->command?->warn("No authors in the list for: {$title}");
        }
    }
}
