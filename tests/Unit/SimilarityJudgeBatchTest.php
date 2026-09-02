<?php

namespace Tests\Unit;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * How many model calls a check spends, and how it spends them.
 *
 * Every candidate used to be judged, one round trip each - ten of them, one
 * after another. The cost of a check is the network, not the model, so on a
 * weak connection that was minutes.
 */
class SimilarityJudgeBatchTest extends TestCase
{
    private function candidates(int $count, array $scores = []): array
    {
        $out = [];

        foreach (range(0, $count - 1) as $i) {
            $doc = new ArchivedDocument;
            $doc->archived_id = $i + 1;
            $doc->title = "Archived Capstone {$i}";
            $doc->abstract = 'An abstract.';

            $out[] = ['document' => $doc, 'score' => $scores[$i] ?? (90 - $i * 10)];
        }

        return $out;
    }

    private function judge(SimilarityService $service, array $candidates): array
    {
        $m = new ReflectionMethod(SimilarityService::class, 'judgeCandidates');
        $m->setAccessible(true);

        return $m->invoke($service, ['keywords' => ['a'], 'domain' => 'd'], $candidates);
    }

    private function verdictJson(): string
    {
        return json_encode([
            'matches' => true, 'score' => 60, 'reason' => 'r',
            'similarities' => 's', 'differences' => 'd', 'suggestion' => 'x',
        ]);
    }

    public function test_all_the_judging_happens_in_one_round_trip(): void
    {
        config(['services.ollama.judge_limit' => 4, 'services.ollama.judge_min_score' => 40]);

        $ollama = Mockery::mock(OllamaSSHClient::class);
        // One call, carrying every prompt - not one call per candidate.
        $ollama->shouldReceive('generateMany')->once()
            ->andReturnUsing(fn (array $prompts) => array_map(fn () => $this->verdictJson(), $prompts));
        $ollama->shouldNotReceive('generate');

        $verdicts = $this->judge(new SimilarityService($ollama), $this->candidates(10));

        $this->assertCount(4, $verdicts, 'More candidates were judged than the limit allows.');
    }

    /** Candidates too far off are not worth a call - they keep the fallback. */
    public function test_a_distant_candidate_is_not_judged(): void
    {
        config(['services.ollama.judge_limit' => 10, 'services.ollama.judge_min_score' => 40]);

        $ollama = Mockery::mock(OllamaSSHClient::class);
        $ollama->shouldReceive('generateMany')->once()
            ->andReturnUsing(fn (array $prompts) => array_map(fn () => $this->verdictJson(), $prompts));

        // 80, 45, 20, 10 - only the first two clear the floor.
        $verdicts = $this->judge(new SimilarityService($ollama), $this->candidates(4, [80, 45, 20, 10]));

        $this->assertSame([0, 1], array_keys($verdicts));
    }

    /**
     * The best match is always explained, however it scored. A check that
     * says nothing about its own top result is not much of a check.
     */
    public function test_the_top_match_is_always_judged(): void
    {
        config(['services.ollama.judge_limit' => 4, 'services.ollama.judge_min_score' => 40]);

        $ollama = Mockery::mock(OllamaSSHClient::class);
        $ollama->shouldReceive('generateMany')->once()
            ->andReturnUsing(fn (array $prompts) => array_map(fn () => $this->verdictJson(), $prompts));

        $verdicts = $this->judge(new SimilarityService($ollama), $this->candidates(3, [12, 8, 5]));

        $this->assertSame([0], array_keys($verdicts));
        $this->assertNotNull($verdicts[0]);
    }

    /** A failed batch costs speed, not verdicts. */
    public function test_it_falls_back_to_one_at_a_time(): void
    {
        config(['services.ollama.judge_limit' => 3, 'services.ollama.judge_min_score' => 40]);

        $ollama = Mockery::mock(OllamaSSHClient::class);
        $ollama->shouldReceive('generateMany')->once()->andThrow(new \Exception('ssh died'));
        $ollama->shouldReceive('generate')->times(3)->andReturn($this->verdictJson());

        $verdicts = $this->judge(new SimilarityService($ollama), $this->candidates(3, [90, 80, 70]));

        $this->assertCount(3, $verdicts);
        $this->assertNotNull($verdicts[0]);
    }

    /** A batch that answers only some is topped up individually. */
    public function test_a_partial_batch_is_topped_up(): void
    {
        config(['services.ollama.judge_limit' => 3, 'services.ollama.judge_min_score' => 40]);

        $ollama = Mockery::mock(OllamaSSHClient::class);
        // only the first two came back
        $ollama->shouldReceive('generateMany')->once()
            ->andReturn([0 => $this->verdictJson(), 1 => $this->verdictJson()]);
        $ollama->shouldReceive('generate')->once()->andReturn($this->verdictJson());

        $verdicts = $this->judge(new SimilarityService($ollama), $this->candidates(3, [90, 80, 70]));

        $this->assertCount(3, $verdicts);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
