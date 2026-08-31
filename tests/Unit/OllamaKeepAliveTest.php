<?php

namespace Tests\Unit;

use App\Services\OllamaSSHClient;
use Tests\TestCase;
use ReflectionClass;

/**
 * Every call has to tell Ollama to keep the model resident.
 *
 * Ollama's own default drops a model five minutes after the last call, and a
 * duplication run makes one call per candidate over longer than that - so the
 * model was read back off disk partway through a single check, more than
 * once. That reload is the wait, not the work.
 */
class OllamaKeepAliveTest extends TestCase
{
    /** @return string[] the source of each method that builds an Ollama payload */
    private function payloadMethods(): array
    {
        $file = (new ReflectionClass(OllamaSSHClient::class))->getFileName();
        $lines = file($file);

        $out = [];

        foreach (['warmup', 'generate', 'embed'] as $name) {
            $method = new \ReflectionMethod(OllamaSSHClient::class, $name);
            $out[$name] = implode('', array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1
            ));
        }

        return $out;
    }

    public function test_every_ollama_call_sends_keep_alive(): void
    {
        foreach ($this->payloadMethods() as $name => $source) {
            $this->assertStringContainsString(
                "'keep_alive'",
                $source,
                "OllamaSSHClient::{$name}() does not keep the model loaded, so it will be reloaded from disk."
            );
        }
    }

    public function test_keep_alive_outlasts_ollamas_own_five_minute_default(): void
    {
        $configured = config('services.ollama.keep_alive', '30m');

        $this->assertNotEmpty($configured);

        // Accepted forms: -1 (never unload), or a duration longer than the
        // 5m default that caused the reloads.
        if ($configured === '-1') {
            return;
        }

        $this->assertMatchesRegularExpression('/^\d+(m|h)$/', (string) $configured);

        $minutes = (int) $configured * (str_ends_with((string) $configured, 'h') ? 60 : 1);

        $this->assertGreaterThan(5, $minutes, 'Anything up to 5m is no better than the default that caused the reloads.');
    }
}
