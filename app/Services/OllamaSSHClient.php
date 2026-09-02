<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class OllamaSSHClient
{
    /** Remembers that the model is loaded, so a scan need not warm it again. */
    private const WARM_KEY = 'ollama:model-warm';

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

        // An empty channel is usually a blip on a slow link rather than a
        // real failure - the command ran, the bytes did not come back. One
        // retry on a fresh connection costs a second and turns the commonest
        // failure during a presentation into a pause nobody notices.
        if (empty(trim($output))) {
            Log::warning('Empty response from Ollama, retrying once', ['endpoint' => $endpoint]);

            $this->connection = null;
            $ssh = $this->getConnection();

            if ($ssh->put($this->toSftpPath($remotePath), $jsonPayload) === false) {
                throw new \Exception('Failed to upload request payload via SFTP.');
            }

            $output = $ssh->exec($command);
        }

        if (empty(trim($output))) {
            throw new \Exception("Empty response from Ollama at {$endpoint}.");
        }

        // An answer is proof the model is loaded, so the next scan can skip
        // warming it.
        $this->markWarm();

        return $output;
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC METHODS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Load the model, unless it is already loaded.
     *
     * This ran at the top of every scan and cost a full round trip plus a
     * generate - about four and a half seconds at a desk, and far worse on a
     * weak link - to load a model that keep_alive had kept in memory the
     * whole time. It saved nothing either way: a cold model gets loaded by
     * the first real call regardless, so warming first only paid for it
     * twice.
     *
     * Every successful call now leaves a marker saying the model is up, and
     * this returns immediately while that marker stands. The marker expires
     * before keep_alive does, so the one scan that follows a long idle still
     * warms properly rather than assuming.
     */
    public function warmup(): void
    {
        if (Cache::get(self::WARM_KEY)) {
            return;
        }

        try {
            $payload = [
                'model' => config('services.ollama.model', 'qwen2.5:7b'),
                'prompt' => 'hi',
                'stream' => false,
                'keep_alive' => config('services.ollama.keep_alive', '30m'),
                'options' => ['num_predict' => 1],
            ];

            $this->runCurl(json_encode($payload), '/api/generate');

            Log::info('Ollama warmup complete.');
        } catch (\Exception $e) {
            Log::warning('Ollama warmup failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * How long to trust that the model is still loaded.
     *
     * Deliberately short of keep_alive: being wrong the safe way costs one
     * unnecessary warmup, being wrong the other way means a scan meeting a
     * cold model with no warning.
     */
    private function markWarm(): void
    {
        $keepAlive = (string) config('services.ollama.keep_alive', '30m');

        $minutes = match (true) {
            $keepAlive === '-1' => 60,
            str_ends_with($keepAlive, 'h') => ((int) $keepAlive) * 60,
            str_ends_with($keepAlive, 'm') => (int) $keepAlive,
            default => 30,
        };

        Cache::put(self::WARM_KEY, true, now()->addMinutes(max(1, (int) ($minutes * 0.7))));
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
            // Without this Ollama drops the model five minutes after the last
            // call and reads it back off disk on the next one. A check runs
            // one call per candidate, so the model was being reloaded partway
            // through a single run - that reload is the wait, not the work.
            'keep_alive' => config('services.ollama.keep_alive', '30m'),
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

    /**
     * Answer several prompts in one trip, run in parallel on the far side.
     *
     * generate() costs a full round trip each time: an SFTP upload, a remote
     * curl, and the output read back. A duplication check judges its
     * candidates one at a time, so ten of them meant ten round trips - about
     * two seconds each on a good link, and minutes on a weak one, because the
     * cost is the network rather than the model.
     *
     * This sends every prompt as one file, starts a background job per prompt
     * on the remote host so they overlap, and reads all the answers back
     * together. The model still judges each candidate on its own - that
     * separation is what keeps the scoring honest - only the waiting is
     * shared.
     *
     * Answers come back keyed by the index they were given in. A prompt whose
     * job produced nothing is simply absent, so a caller can fall back to
     * generate() for that one rather than losing the whole batch.
     *
     * @param  string[]  $prompts
     * @return array<int, string>
     */
    public function generateMany(array $prompts, int $maxTokens = 512): array
    {
        if ($prompts === []) {
            return [];
        }

        $payloads = [];

        foreach (array_values($prompts) as $prompt) {
            $payloads[] = [
                'model' => config('services.ollama.model', 'qwen2.5:7b'),
                'prompt' => $prompt,
                'stream' => false,
                'keep_alive' => config('services.ollama.keep_alive', '30m'),
                'options' => [
                    'num_predict' => $maxTokens,
                    'temperature' => 0,
                    'min_p' => 0,
                    'top_p' => 1,
                    'top_k' => 1,
                ],
            ];
        }

        $ssh = $this->getConnection();
        $tempDir = $this->getRemoteTempDir($ssh);
        $batchId = uniqid();
        $remotePath = $tempDir.'\\ollama_batch_'.$batchId.'.json';

        if ($ssh->put($this->toSftpPath($remotePath), json_encode($payloads)) === false) {
            throw new \Exception('Failed to upload batch payload via SFTP.');
        }

        // Each answer is announced by a marker carrying its index, because
        // jobs finish in whatever order they finish in.
        $marker = '@@OLLAMA_'.$batchId.'_';

        $script = '$items = Get-Content -Raw -Encoding UTF8 \"'.$remotePath.'\" | ConvertFrom-Json; '
                .'$jobs = @(); '
                .'for ($i = 0; $i -lt $items.Count; $i++) { '
                .'  $f = \"'.$tempDir.'\\ollama_'.$batchId.'_$i.json\"; '
                // Written without a BOM: curl hands the file to Ollama as-is,
                // and a BOM makes it invalid JSON.
                .'  [System.IO.File]::WriteAllText($f, ($items[$i] | ConvertTo-Json -Depth 20 -Compress), (New-Object System.Text.UTF8Encoding $false)); '
                .'  $jobs += Start-Job -ArgumentList $f, $i -ScriptBlock { param($file, $idx) '
                .'    $r = curl.exe -s --max-time 300 -X POST http://localhost:11434/api/generate -H \"Content-Type: application/json\" --data-binary \"@$file\"; '
                .'    Write-Output (\"'.$marker.'$idx@@\"); '
                .'    Write-Output $r; '
                .'    Remove-Item $file -ErrorAction SilentlyContinue '
                .'  } '
                .'} '
                .'$null = $jobs | Wait-Job; '
                .'foreach ($j in $jobs) { Receive-Job $j } '
                .'$jobs | Remove-Job -Force -ErrorAction SilentlyContinue; '
                .'Remove-Item \"'.$remotePath.'\" -ErrorAction SilentlyContinue';

        $output = $ssh->exec('powershell -Command "'.$script.'"');

        if (empty(trim((string) $output))) {
            throw new \Exception('Empty response from Ollama batch.');
        }

        $this->markWarm();

        return $this->splitBatchOutput((string) $output, $marker, count($payloads));
    }

    /**
     * Pull the individual answers out of one batch's combined output.
     *
     * @return array<int, string>
     */
    private function splitBatchOutput(string $output, string $marker, int $expected): array
    {
        $parts = preg_split(
            '/'.preg_quote($marker, '/').'(\d+)@@/',
            $output,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $answers = [];

        // parts: [before, index, body, index, body, ...]
        for ($i = 1; $i < count($parts) - 1; $i += 2) {
            $index = (int) $parts[$i];
            $body = trim($parts[$i + 1]);

            if ($index >= 0 && $index < $expected && $body !== '') {
                $decoded = json_decode($body, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['response'])) {
                    $answers[$index] = $decoded['response'];
                }
            }
        }

        if ($answers === []) {
            Log::warning('Ollama batch returned nothing usable', ['expected' => $expected]);
        }

        return $answers;
    }

    public function embed(string $text): array
    {
        $payload = [
            'model' => config('services.ollama.embed_model', 'nomic-embed-text'),
            'prompt' => mb_substr($text, 0, 4000),
            // The embedding model pays the same reload cost as the big one.
            'keep_alive' => config('services.ollama.keep_alive', '30m'),
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
