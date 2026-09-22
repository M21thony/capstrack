<?php

namespace Tests\Unit;

use App\Services\OllamaSSHClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * With OLLAMA_URL set (a RunPod pod, say) the client talks to Ollama over
 * plain HTTP instead of logging into the Windows laptop.
 */
class OllamaDirectUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ollama.url' => 'https://pod-11434.example.net/', 'services.ollama.embed_model' => 'nomic-embed-text']);
    }

    public function test_generate_and_embed_post_straight_to_ollama(): void
    {
        Http::fake([
            '*/api/generate' => Http::response(['response' => 'Score: 40']),
            '*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2]]),
        ]);

        $client = new OllamaSSHClient;

        $this->assertSame('Score: 40', $client->generate('compare these'));
        $this->assertSame([0.1, 0.2], $client->embed('a title'));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://pod-11434.example.net/api/generate'
            && $r['prompt'] === 'compare these' && $r['options']['temperature'] === 0);
    }

    public function test_generate_many_keeps_each_answer_at_its_index_and_drops_failures(): void
    {
        Http::fake(fn (Request $r) => $r['prompt'] === 'bad'
            ? Http::response('boom', 500)
            : Http::response(['response' => 'answer to '.$r['prompt']]));

        $answers = (new OllamaSSHClient)->generateMany(['one', 'bad', 'three']);

        $this->assertSame([0 => 'answer to one', 2 => 'answer to three'], $answers);
    }
}
