<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AI judge gave 85 to a PDF scanned against itself. Meaning and exact
 * words are both looked for now, and the higher one is the score.
 */
class ExactWordsInDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private const CONCEPTS = [
        'title' => 'Barangay Health Records', 'main_topic' => 'health records', 'domain' => 'Web System',
        'keywords' => ['health', 'records'], 'problem_statement' => 'Paper records.', 'proposed_solution' => 'A web system.',
        'scope' => 'One barangay.', 'technologies' => ['Laravel'], 'target_users' => 'Health workers',
    ];

    private const TEXT = 'the barangay health center keeps patient records on paper so nurses lose time finding a '
        .'chart and families repeat their history at every visit while the midwife copies vaccine dates by hand '
        .'into a logbook that nobody can search during an outbreak or a referral to the city hospital';

    /** One archive study with this abstract and the judge answering 40; the rest have short abstracts. */
    private function scan(string $abstract, ?string $judgeAnswer = null): array
    {
        foreach (range(1, 6) as $n) {
            $doc = ArchivedDocument::create([
                'team_id' => 0, 'title' => "Capstone {$n}", 'abstract' => $n === 1 ? $abstract : 'About it.',
                'academic_year' => '2024', 'program' => 'BSIT', 'file_path' => "archive/{$n}.pdf",
            ]);
            $doc->embedding = json_encode(array_fill(0, 8, 0.5));
            $doc->save();
        }

        $answer = $judgeAnswer ?? json_encode(['matches' => true, 'score' => 40, 'reason' => 'Read both.', 'differences' => 'Scope differs.']);
        $ollama = $this->mock(OllamaSSHClient::class);
        $ollama->shouldReceive('embed')->andReturn(array_fill(0, 8, 0.5));
        $ollama->shouldReceive('generateMany')->andReturnUsing(fn (array $prompts) => array_fill(0, count($prompts), $answer));
        $ollama->shouldReceive('generate')->andReturn($answer);

        $matches = app(SimilarityService::class)->findSimilarDocuments(self::CONCEPTS, 10, null, self::TEXT);

        return collect($matches)->firstWhere('document.title', 'Capstone 1');
    }

    public function test_the_same_text_scores_100_whatever_the_judge_said(): void
    {
        $match = $this->scan(self::TEXT);

        $this->assertSame(100, $match['score']);
        $this->assertSame(100, $match['exact_score']);
        $this->assertSame(40, $match['judge_score']);
        $this->assertSame('', $match['differences']);
    }

    public function test_it_needs_no_judge_at_all(): void
    {
        $match = $this->scan(self::TEXT, 'not json');

        $this->assertSame(100, $match['score']);
        $this->assertTrue($match['assessed']);
    }

    public function test_a_partly_copied_text_says_so_in_its_reason(): void
    {
        $words = explode(' ', self::TEXT);
        $abstract = implode(' ', array_slice($words, 0, 30)).' '.implode(' ', array_map(fn ($n) => "other{$n}", range(1, 12)));

        $match = $this->scan($abstract);

        $this->assertGreaterThan(40, $match['score']);
        $this->assertLessThan(100, $match['score']);
        $this->assertSame($match['exact_score'], $match['score']);
        $this->assertSame($match['score'].'% of its wording is the same as yours.', $match['reason']);
    }

    public function test_a_little_shared_wording_is_shown_but_does_not_move_the_score(): void
    {
        $words = explode(' ', self::TEXT);
        $abstract = implode(' ', array_slice($words, 0, 10)).' '.implode(' ', array_map(fn ($n) => "other{$n}", range(1, 30)));

        $match = $this->scan($abstract);

        $this->assertSame(40, $match['score']);
        $this->assertLessThan(SimilarityService::EXACT_WORDS_MIN, $match['exact_score']);
    }
}
