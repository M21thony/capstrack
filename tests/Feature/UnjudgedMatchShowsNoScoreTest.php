<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A number nobody judged is not a similarity score.
 *
 * Only the first few candidates got a verdict; the rest kept their
 * embedding rank multiplied by 0.4 and were shown as "27%" beside the ones
 * a model had actually read. Measured on the archive, the genuine match sat
 * outside the judged set about 8% of the time - so a real duplicate could
 * be presented as a low score nobody had looked at.
 */
class UnjudgedMatchShowsNoScoreTest extends TestCase
{
    use RefreshDatabase;

    private function archived(string $title): ArchivedDocument
    {
        $doc = ArchivedDocument::create([
            'team_id' => 0,
            'title' => $title,
            'abstract' => 'An abstract for '.$title,
            'academic_year' => '2025',
            'program' => 'BSIT',
            // Both are NOT NULL on the table.
            'file_path' => 'archive/'.md5($title).'.pdf',
        ]);

        // Not fillable - it is written by the embedding command, not by a form.
        $doc->embedding = json_encode(array_fill(0, 8, 0.5));
        $doc->save();

        return $doc;
    }

    public function test_a_candidate_no_model_read_is_marked_unassessed(): void
    {
        config(['services.ollama.judge_limit' => 2]);

        // Six, because below five embedded documents the checker abandons
        // vector search for the legacy keyword path and this exercises
        // nothing.
        foreach (range(1, 6) as $n) {
            $this->archived("Capstone {$n}");
        }

        $verdict = json_encode(['matches' => true, 'score' => 12, 'reason' => 'Different subject.']);

        $ollama = $this->mock(OllamaSSHClient::class);
        $ollama->shouldReceive('embed')->andReturn(array_fill(0, 8, 0.5));
        // One answer per prompt sent, and the limit allows two.
        $ollama->shouldReceive('generateMany')->andReturn([$verdict, $verdict]);
        $ollama->shouldReceive('generate')->andReturn($verdict);

        // The full shape extractKeyConcepts returns - the query is built from
        // every field, so a partial one only produces warnings.
        $matches = app(SimilarityService::class)->findSimilarDocuments([
            'title' => 'A Capstone',
            'main_topic' => 'keeping records for a barangay',
            'domain' => 'Web System',
            'keywords' => ['records', 'barangay'],
            'problem_statement' => 'Records are kept on paper.',
            'proposed_solution' => 'A system to keep them instead.',
            'scope' => 'One barangay.',
        ], 10);

        $this->assertCount(6, $matches);

        $assessed = array_values(array_filter($matches, fn ($m) => $m['assessed']));
        $unassessed = array_values(array_filter($matches, fn ($m) => ! $m['assessed']));

        $this->assertCount(2, $assessed, 'the judged candidates are marked assessed');
        $this->assertCount(4, $unassessed, 'the four past the limit are not');

        foreach ($assessed as $m) {
            $this->assertSame(12, $m['score'], 'a judged candidate keeps the score it was given');
        }

        $this->assertSame([true, true], array_column(array_slice($matches, 0, 2), 'assessed'), 'a low judged score still ranks above a row with no score');

        foreach ($unassessed as $m) {
            $this->assertStringContainsString('Not assessed', $m['reason']);
        }
    }
}
