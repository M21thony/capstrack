<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class OllamaSSHClient
{
    private function makeConnection(): SSH2
    {
        $host = config('services.ssh_tunnel.host');
        $port = (int) config('services.ssh_tunnel.port', 22);
        $user = config('services.ssh_tunnel.user');
        $keyPath = config('services.ssh_tunnel.key_path');

        if (! file_exists($keyPath)) {
            throw new \Exception("SSH key not found at: {$keyPath}");
        }

        $ssh = new SSH2($host, $port);
        $key = PublicKeyLoader::load(file_get_contents($keyPath));

        if (! $ssh->login($user, $key)) {
            throw new \Exception('SSH login failed. Check host, user, and key.');
        }

        return $ssh;
    }

    // ── Text generation ───────────────────────────────────
    public function generate(string $prompt): string
    {
        $ssh = $this->makeConnection();
        $model = config('services.ollama.model', 'qwen2.5:3B');

        $payload = json_encode([
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'num_predict' => 2048,
                'temperature' => 0.2,
            ],
        ]);

        $output = $this->runCurl($ssh, $payload, '/api/generate');

        $encoded = base64_encode($payload);
        $tmpFile = 'ollama_'.uniqid().'.json';

        // Combine everything into ONE powershell command
        $command = 'powershell -Command "'
                .'$bytes = [System.Convert]::FromBase64String(\''.$encoded.'\'); '
                .'$tmp = \"$env:TEMP\\'.$tmpFile.'\"; '
                .'[System.IO.File]::WriteAllBytes($tmp, $bytes); '
                .'$r = curl.exe -s -X POST http://localhost:11434/api/generate '
                .'-H \"Content-Type: application/json\" '
                .'-d \"@$tmp\"; '
                .'Remove-Item $tmp; '
                .'Write-Output $r'
                .'"';

        $output = $ssh->exec($command);
        $ssh->disconnect();

        $decoded = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('OllamaSSH generate raw output', ['output' => $output]);
            throw new \Exception('Failed to decode Ollama JSON. Raw: '.substr($output, 0, 300));
            Log::error('OllamaSSH raw output', ['output' => $output]);
            throw new \Exception('Failed to decode Ollama JSON. Raw: '.substr($output, 0, 300));
        }

        return $decoded['response'] ?? '';
    }

    // ── Embedding generation via nomic-embed-text ─────────────────────
    /**
     * Generate a vector embedding for the given text.
     * Uses the /api/embeddings endpoint with nomic-embed-text.
     *
     * @param  string  $text  The text to embed (title + abstract recommended)
     * @return float[] A float array of ~768 dimensions
     */
    public function embed(string $text): array
    {
        $ssh = $this->makeConnection();
        $model = config('services.ollama.embed_model', 'nomic-embed-text');

        // Truncate to avoid token limits — nomic-embed-text supports ~8192 tokens
        $truncated = mb_substr($text, 0, 4000);

        $payload = json_encode([
            'model' => $model,
            'prompt' => $truncated,
        ]);

        $output = $this->runCurl($ssh, $payload, '/api/embeddings');
        $ssh->disconnect();

        $decoded = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($decoded['embedding'])) {
            Log::error('OllamaSSH embed raw output', ['output' => $output]);
            throw new \Exception('Failed to get embedding from Ollama. Raw: '.substr($output, 0, 300));
        }

        return $decoded['embedding']; // array of floats
    }

    // ── Shared cURL runner via SSH ────────────────────────────────────
    private function runCurl(SSH2 $ssh, string $jsonPayload, string $endpoint): string
    {
        $encoded = base64_encode($jsonPayload);
        $tmpFile = 'ollama_'.uniqid().'.json';

        $command = 'powershell -Command "'
                .'$bytes = [System.Convert]::FromBase64String(\''.$encoded.'\'); '
                .'$tmp = \"$env:TEMP\\'.$tmpFile.'\"; '
                .'[System.IO.File]::WriteAllBytes($tmp, $bytes); '
                .'$r = curl.exe -s --max-time 180 -X POST http://localhost:11434'.$endpoint.' '  // ← added --max-time 180
                .'-H \"Content-Type: application/json\" '
                .'--data-binary \"@$tmp\"; '
                .'Remove-Item $tmp; '
                .'Write-Output $r'
                .'"';

        $output = $ssh->exec($command);

        if (empty(trim($output))) {
            throw new \Exception("Empty response from Ollama at {$endpoint}.");
        }

        return $output;
    }
}
