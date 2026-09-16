<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\DuplicationCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A similarity check saved before a study's authors were recorded still
 * names them: authors are read from the archive, not the stored copy.
 */
class ArchivedAuthorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_check_shows_authors_recorded_after_it_ran(): void
    {
        $team = \App\Models\Team::create(['team_name' => 'Archive Team', 'team_status' => 'Active']);
        $study = ArchivedDocument::create([
            'team_id' => $team->team_id, 'title' => 'Smart Parking Finder', 'academic_year' => '2024',
            'program' => 'BSIT', 'abstract' => 'A parking app.', 'file_path' => 'archives/parking.pdf',
        ]);
        $check = new DuplicationCheck(['matches' => [
            ['archived_id' => $study->archived_id, 'title' => 'Smart Parking Finder', 'score' => 40],
        ]]);

        $this->assertNull($check->matchesWithAuthors()[0]['authors']);

        $study->update(['authors' => 'Juan Dela Cruz, Maria Santos']);

        $this->assertSame('Juan Dela Cruz, Maria Santos', $check->matchesWithAuthors()[0]['authors']);
        $this->assertSame(40, $check->matchesWithAuthors()[0]['score']);
    }
}
