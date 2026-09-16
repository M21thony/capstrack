<?php
namespace Tests\Unit;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A batch answer nobody could read is asked again.
 *
 * The batched judge runs one PowerShell job per candidate, all writing to one
 * stdout captured over SSH, so an answer can come back interleaved or clipped.
 * The reader returned null for those and the caller stored the null, which
 * has a key - so the retry loop, which looked for a MISSING key, skipped it.
 * The student saw "Not assessed" against a study the model had judged.
 */
class UnreadableBatchAnswerIsRetriedTest extends TestCase
{
    private function candidates(int $count): array
    {
        $out = [];

        foreach (range(0, $count - 1) as $i) {
            $doc = new ArchivedDocument;
            $doc->archived_id = $i + 1;
            $doc->title = "Archived Capstone {$i}";
            $doc->abstract = 'An abstract long enough to be judged.';
            $out[] = ['document' => $doc, 'score' => 80];
        }

        return $out;
    }

    private function judge(OllamaSSHClient $client, int $count): array
    {
        $svc = new SimilarityService($client);
        $method = new ReflectionMethod($svc, 'judgeCandidates');
        $method->setAccessible(true);

        // buildJudgePrompt reads these straight out of the array, so the
        // fixture carries every field a real extraction would.
        $concepts = [
            'title' => 'A Pre-Proposal',
            'main_topic' => 'Something specific.',
            'domain' => 'Web System',
            'keywords' => ['scheduling'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
        ];

        return $method->invoke($svc, $concepts, $this->candidates($count));
    }

    public function test_an_unreadable_batch_answer_is_asked_again_on_its_own(): void
    {
        $good = '{"matches":false,"score":15,"reason":"Different subjects entirely.",'
            .'"similarities":"none","differences":"a versus b","suggestion":"Narrow it."}';

        $client = Mockery::mock(OllamaSSHClient::class);
        // The batch answers both, but the second came back as noise.
        $client->shouldReceive('generateMany')->once()
            ->andReturn([0 => $good, 1 => 'ollama_batch_9f2@@ {"resp']);
        // So the second is asked again, alone, and answers properly.
        $client->shouldReceive('generate')->once()->andReturn($good);

        $verdicts = $this->judge($client, 2);

        $this->assertNotNull($verdicts[1], 'the clipped answer must be re-asked, not surrendered');
        $this->assertSame(15, $verdicts[1]['score']);
    }

    public function test_a_readable_batch_answer_is_not_asked_twice(): void
    {
        $good = '{"matches":true,"score":70,"reason":"Same subject and users.",'
            .'"similarities":"campus repairs","differences":"scope","suggestion":"Narrow it."}';

        $client = Mockery::mock(OllamaSSHClient::class);
        $client->shouldReceive('generateMany')->once()->andReturn([0 => $good, 1 => $good]);
        $client->shouldNotReceive('generate');

        $verdicts = $this->judge($client, 2);

        $this->assertSame(70, $verdicts[0]['score']);
        $this->assertSame(70, $verdicts[1]['score']);
    }

    /** If the single retry fails too, it stays null - not an invented verdict. */
    public function test_a_second_failure_is_still_reported_as_unassessed(): void
    {
        $client = Mockery::mock(OllamaSSHClient::class);
        $client->shouldReceive('generateMany')->once()->andReturn([0 => 'not json at all']);
        $client->shouldReceive('generate')->once()->andReturn('still not json');

        $verdicts = $this->judge($client, 1);

        $this->assertNull($verdicts[0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
