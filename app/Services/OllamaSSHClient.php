<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use Illuminate\Support\Facades\Log;

class OllamaSSHClient
{
    private function makeConnection(): SSH2
    {
        $host    = config('services.ssh_tunnel.host');
        $port    = (int) config('services.ssh_tunnel.port', 22);
        $user    = config('services.ssh_tunnel.user');
        $keyPath = config('services.ssh_tunnel.key_path');

        if (!file_exists($keyPath)) {
            throw new \Exception("SSH key not found at: {$keyPath}");
        }

        $ssh = new SSH2($host, $port);
        $ssh->setTimeout(300); // 5 min — allows cold model load
        $key = PublicKeyLoader::load(file_get_contents($keyPath));

        if (!$ssh->login($user, $key)) {
            throw new \Exception('SSH login failed. Check host, user, and key.');
        }

        return $ssh;
    }

    // ── Warmup — loads model into GPU memory before real requests ─────
    public function warmup(): void
    {
        try {
            $ssh     = $this->makeConnection();
            $payload = json_encode([
                'model'   => config('services.ollama.model', 'qwen2.5:3B'),
                'prompt'  => 'hi',
                'stream'  => false,
                'options' => ['num_predict' => 1],
            ]);
            $this->runCurl($ssh, $payload, '/api/generate');
            $ssh->disconnect();
            Log::info('Ollama warmup complete.');
        } catch (\Exception $e) {
            Log::warning('Ollama warmup failed', ['error' => $e->getMessage()]);
        }
    }

    // ── Text generation ───────────────────────────────────────────────
    public function generate(string $prompt): string
    {
        $ssh   = $this->makeConnection();
        $model = config('services.ollama.model', 'qwen2.5:3B');

        $payload = json_encode([
            'model'   => $model,
            'prompt'  => $prompt,
            'stream'  => false,
            'options' => [
                'num_predict' => 2048,
                'temperature' => 0.2,
            ],
        ]);

        $output  = $this->runCurl($ssh, $payload, '/api/generate');
        $ssh->disconnect();

        $decoded = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('OllamaSSH generate raw output', ['output' => $output]);
            throw new \Exception('Failed to decode Ollama JSON. Raw: ' . substr($output, 0, 300));
        }

        return $decoded['response'] ?? '';
    }

    // ── Embedding generation via nomic-embed-text ─────────────────────
    public function embed(string $text): array
    {
        $ssh   = $this->makeConnection();
        $model = config('services.ollama.embed_model', 'nomic-embed-text');

        $truncated = mb_substr($text, 0, 4000);

        $payload = json_encode([
            'model'  => $model,
            'prompt' => $truncated,
        ]);

        $output  = $this->runCurl($ssh, $payload, '/api/embeddings');
        $ssh->disconnect();

        $decoded = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['embedding'])) {
            Log::error('OllamaSSH embed raw output', ['output' => $output]);
            throw new \Exception('Failed to get embedding from Ollama. Raw: ' . substr($output, 0, 300));
        }

        return $decoded['embedding'];
    }

    // ── Shared cURL runner via SSH ────────────────────────────────────
    private function runCurl(SSH2 $ssh, string $jsonPayload, string $endpoint): string
    {
        $encoded = base64_encode($jsonPayload);
        $tmpFile = 'ollama_' . uniqid() . '.json';

        $command = 'powershell -Command "'
                 . '$bytes = [System.Convert]::FromBase64String(\'' . $encoded . '\'); '
                 . '$tmp = \"$env:TEMP\\' . $tmpFile . '\"; '
                 . '[System.IO.File]::WriteAllBytes($tmp, $bytes); '
                 . '$r = curl.exe -s --max-time 240 -X POST http://localhost:11434' . $endpoint . ' '
                 . '-H \"Content-Type: application/json\" '
                 . '--data-binary \"@$tmp\"; '
                 . 'Remove-Item $tmp -ErrorAction SilentlyContinue; '
                 . 'Write-Output $r'
                 . '"';

        $output = $ssh->exec($command);

        if (empty(trim($output))) {
            throw new \Exception("Empty response from Ollama at {$endpoint}.");
        }

        return $output;
    }
}