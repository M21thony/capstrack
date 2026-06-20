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
        $key = PublicKeyLoader::load(file_get_contents($keyPath));

        if (!$ssh->login($user, $key)) {
            throw new \Exception('SSH login failed. Check host, user, and key.');
        }

        return $ssh;
    }

    public function generate(string $prompt): string
    {
        $ssh   = $this->makeConnection();
        $model = config('services.ollama.model', 'qwen2.5:3B');

        $payload = json_encode([
            'model'   => $model,
            'prompt'  => $prompt,
            'stream'  => false,
            'options' => [
                'num_predict' => 2048,   // allow longer output, prevents truncation
                'temperature' => 0.2,    // more deterministic scoring
            ],
        ]);

        $encoded = base64_encode($payload);
        $tmpFile = 'ollama_' . uniqid() . '.json';

        // Combine everything into ONE powershell command
        $command = 'powershell -Command "'
                . '$bytes = [System.Convert]::FromBase64String(\''. $encoded .'\'); '
                . '$tmp = \"$env:TEMP\\' . $tmpFile . '\"; '
                . '[System.IO.File]::WriteAllBytes($tmp, $bytes); '
                . '$r = curl.exe -s -X POST http://localhost:11434/api/generate '
                . '-H \"Content-Type: application/json\" '
                . '-d \"@$tmp\"; '
                . 'Remove-Item $tmp; '
                . 'Write-Output $r'
                . '"';

        $output = $ssh->exec($command);
        $ssh->disconnect();

        if (empty(trim($output))) {
            throw new \Exception('Empty response from Ollama.');
        }

        $decoded = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('OllamaSSH raw output', ['output' => $output]);
            throw new \Exception('Failed to decode Ollama JSON. Raw: ' . substr($output, 0, 300));
        }

        return $decoded['response'] ?? '';
    }
}