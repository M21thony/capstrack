<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class OllamaSSHClient
{
    // Always SSH — this client only ever talks to Ollama through the tunnel.
    // Requires: SSH server running + .env SSH_TUNNEL_* values.

    // Reused across every call on this instance (it's bound as a singleton —
    // see AppServiceProvider) instead of reconnecting per warmup/generate/embed
    // call. A fresh SSH handshake + login per call was the reason a single
    // duplication check (warmup + 3 generate calls + 1 embed call) took over
    // two minutes — five separate connections instead of one.
    private ?SFTP $connection = null;

    private ?string $remoteTempDir = null;

    // ─────────────────────────────────────────────────────────────────
    // SSH INTERNALS
    // ─────────────────────────────────────────────────────────────────

    private function getConnection(): SFTP
    {
        if ($this->connection !== null && $this->connection->isConnected() && $this->connection->isAuthenticated()) {
            return $this->connection;
        }

        $host = config('services.ssh_tunnel.host');
        $port = (int) config('services.ssh_tunnel.port', 22);
        $user = config('services.ssh_tunnel.user');
        $keyPath = config('services.ssh_tunnel.key_path');

        if (! file_exists($keyPath)) {
            throw new \Exception("SSH key not found at: {$keyPath}");
        }

        // SFTP (a subclass of SSH2) so the same connection can both exec()
        // commands and upload the request payload as a file, instead of
        // embedding it base64-encoded inside the exec'd command line —
        // that blew past Windows' command-length limit for any prompt of
        // meaningful size (e.g. enrichWithReasons' ~20-document prompt),
        // silently failing every "AI reason" and falling back to the
        // generic "Semantically similar based on vector embedding analysis."
        // Must stay comfortably under PHP's own max_execution_time (120s on
        // this server - confirmed via storage/logs/laravel.log entries like
        // "Maximum execution time of 120 seconds exceeded" at SSH2.php).
        // A longer SSH timeout than that is pointless: PHP's hard fatal
        // fires first, and a FatalError isn't a \Throwable subtype that
        // ordinary `catch (\Exception $e)` blocks can catch - it bypasses
        // DuplicationController/AdminGradingRubricController's error
        // handling entirely and Laravel serves its raw HTML error page,
        // which the frontend then reports as "Server returned an error
        // page" instead of a real message.
        $ssh = new SFTP($host, $port);
        $ssh->setTimeout(90);
        $key = PublicKeyLoader::load(file_get_contents($keyPath));

        if (! $ssh->login($user, $key)) {
            throw new \Exception('SSH login failed. Check host, user, and key.');
        }

        $this->connection = $ssh;
        $this->remoteTempDir = null;

        return $ssh;
    }

    private function getRemoteTempDir(SFTP $ssh): string
    {
        if ($this->remoteTempDir !== null) {
            return $this->remoteTempDir;
        }

        $dir = trim($ssh->exec('powershell -Command "Write-Output $env:TEMP"'));

        if ($dir === '') {
            throw new \Exception('Could not resolve remote TEMP directory.');
        }

        return $this->remoteTempDir = $dir;
    }

    // Windows OpenSSH's exec() channel takes native paths (C:\Users\...),
    // but its SFTP subsystem is POSIX-rooted and expects /C:/Users/...
    // — passing the native form to put() fails with "No such file".
    private function toSftpPath(string $windowsPath): string
    {
        $posix = str_replace('\\', '/', $windowsPath);

        return preg_match('/^[A-Za-z]:/', $posix) ? '/'.$posix : $posix;
    }

    private function runCurl(string $jsonPayload, string $endpoint): string
    {
        $ssh = $this->getConnection();
        $tempDir = $this->getRemoteTempDir($ssh);
        $remotePath = $tempDir.'\\ollama_'.uniqid().'.json';

        if ($ssh->put($this->toSftpPath($remotePath), $jsonPayload) === false) {
            throw new \Exception('Failed to upload request payload via SFTP.');
        }

        // The command itself now only ever references a short fixed-length
        // file path, regardless of how large the payload is.
        $command = 'powershell -Command "'
                 .'$r = curl.exe -s --max-time 300 -X POST http://localhost:11434'.$endpoint.' '
                 .'-H \"Content-Type: application/json\" '
                 .'--data-binary \"@'.$remotePath.'\"; '
                 .'Remove-Item \"'.$remotePath.'\" -ErrorAction SilentlyContinue; '
                 .'Write-Output $r'
                 .'"';

        $output = $ssh->exec($command);

        if (empty(trim($output))) {
            throw new \Exception("Empty response from Ollama at {$endpoint}.");
        }

        return $output;
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC METHODS
    // ─────────────────────────────────────────────────────────────────

    public function warmup(): void
    {
        try {
            $payload = [
                'model' => config('services.ollama.model', 'qwen2.5:7b'),
                'prompt' => 'hi',
                'stream' => false,
                'options' => ['num_predict' => 1],
            ];

            $this->runCurl(json_encode($payload), '/api/generate');

            Log::info('Ollama warmup complete.');
        } catch (\Exception $e) {
            Log::warning('Ollama warmup failed', ['error' => $e->getMessage()]);
        }
    }

    public function generate(string $prompt, int $maxTokens = 512): string
    {
        // The custom "capstrack-duplication" model's own Modelfile bakes in
        // `temperature 1.5` (confirmed via `ollama show --modelfile`) - a
        // previous `temperature: 0.1` here did NOT reliably override that,
        // confirmed by sending the exact same prompt three times and getting
        // three different scores/reasons for an unambiguous comparison. That
        // was the real cause of duplication-check results changing between
        // otherwise-identical runs. Forcing every sampling knob to its most
        // deterministic value (not just temperature) reproducibly gives byte-
        // identical output for the same prompt.
        $payload = [
            'model' => config('services.ollama.model', 'qwen2.5:7b'),
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'num_predict' => $maxTokens,
                'temperature' => 0,
                'min_p' => 0,
                'top_p' => 1,
                'top_k' => 1,
            ],
        ];

        $output = $this->runCurl(json_encode($payload), '/api/generate');
        $decoded = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('OllamaSSH generate raw output', ['output' => $output]);
            throw new \Exception('Failed to decode Ollama JSON. Raw: '.substr($output, 0, 300));
        }

        return $decoded['response'] ?? '';
    }

    public function embed(string $text): array
    {
        $payload = [
            'model' => config('services.ollama.embed_model', 'nomic-embed-text'),
            'prompt' => mb_substr($text, 0, 4000),
        ];

        $output = $this->runCurl(json_encode($payload), '/api/embeddings');
        $decoded = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($decoded['embedding'])) {
            Log::error('OllamaSSH embed raw output', ['output' => $output]);
            throw new \Exception('Failed to get embedding from Ollama. Raw: '.substr($output, 0, 300));
        }

        return $decoded['embedding'];
    }
}
