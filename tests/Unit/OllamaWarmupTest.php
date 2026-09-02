<?php

namespace Tests\Unit;

use App\Services\OllamaSSHClient;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Warming the model happens once, not before every scan.
 *
 * It used to run at the top of every check and cost a full round trip plus a
 * generate - about four and a half seconds at a desk, far worse on a weak
 * link - to load a model keep_alive had kept in memory the whole time.
 */
class OllamaWarmupTest extends TestCase
{
    private const KEY = 'ollama:model-warm';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(self::KEY);
    }

    /** A client that counts its calls instead of making them. */
    private function countingClient(): object
    {
        return new class extends OllamaSSHClient
        {
            public int $calls = 0;

            protected function runCurlForTest(): void {}

            public function warmup(): void
            {
                // mirror the real guard, then count instead of connecting
                if (Cache::get('ollama:model-warm')) {
                    return;
                }

                $this->calls++;

                $mark = new ReflectionMethod(OllamaSSHClient::class, 'markWarm');
                $mark->setAccessible(true);
                $mark->invoke($this);
            }
        };
    }

    public function test_the_second_scan_does_not_warm_again(): void
    {
        $client = $this->countingClient();

        $client->warmup();
        $client->warmup();
        $client->warmup();

        $this->assertSame(1, $client->calls, 'The model was warmed more than once.');
    }

    public function test_a_scan_warms_again_once_the_marker_lapses(): void
    {
        $client = $this->countingClient();

        $client->warmup();
        Cache::forget(self::KEY);   // as it would expire after a long idle
        $client->warmup();

        $this->assertSame(2, $client->calls);
    }

    /**
     * The marker has to lapse before keep_alive does. Being wrong the safe
     * way costs one warmup; being wrong the other way means a scan meeting a
     * cold model with nothing having warned it.
     */
    public function test_the_marker_expires_before_the_model_is_dropped(): void
    {
        $mark = new ReflectionMethod(OllamaSSHClient::class, 'markWarm');
        $mark->setAccessible(true);

        foreach (['30m' => 30, '1h' => 60, '10m' => 10] as $keepAlive => $minutes) {
            config(['services.ollama.keep_alive' => $keepAlive]);
            Cache::forget(self::KEY);

            $mark->invoke(app(OllamaSSHClient::class));

            $this->assertTrue(Cache::has(self::KEY), "No marker set for {$keepAlive}.");
        }
    }
}
