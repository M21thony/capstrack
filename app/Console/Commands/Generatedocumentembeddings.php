<?php

namespace App\Console\Commands;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDocumentEmbeddings extends Command
{
    protected $signature = 'rag:embed-documents
                            {--force : Re-generate embeddings even if they already exist}
                            {--id=  : Embed a single document by archived_id}';

    protected $description = 'Generate nomic-embed-text vector embeddings for all archived documents';

    public function __construct(private OllamaSSHClient $ollama)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = $this->option('force');
        $singleId = $this->option('id');

        // ── Single document mode ──────────────────────────────────────
        if ($singleId) {
            $doc = ArchivedDocument::find($singleId);
            if (!$doc) {
                $this->error("Document #{$singleId} not found.");
                return Command::FAILURE;
            }
            $this->embedDocument($doc);
            $this->info("Done — document #{$singleId} embedded.");
            return Command::SUCCESS;
        }

        // ── Batch mode ────────────────────────────────────────────────
        $query = ArchivedDocument::query();

        if (!$force) {
            $query->whereNull('embedding');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('All documents already have embeddings. Use --force to regenerate.');
            return Command::SUCCESS;
        }

        $this->info("Generating embeddings for {$total} document(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $failed  = 0;

        $query->orderBy('archived_id')->chunk(10, function ($docs) use (&$success, &$failed, $bar) {
            foreach ($docs as $doc) {
                try {
                    $this->embedDocument($doc);
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Embedding failed', [
                        'archived_id' => $doc->archived_id,
                        'error'       => $e->getMessage(),
                    ]);
                }
                $bar->advance();

                // Small delay to avoid hammering Ollama
                usleep(200_000); // 200ms
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("✓ Success: {$success}  ✗ Failed: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // ── Build text representation and store embedding ─────────────────
    private function embedDocument(ArchivedDocument $doc): void
    {
        // Combine title + abstract for a richer semantic representation
        $text = trim($doc->title . "\n\n" . ($doc->abstract ?? ''));

        if (empty($text)) {
            throw new \Exception("Document #{$doc->archived_id} has no title or abstract.");
        }

        $embedding = $this->ollama->embed($text);

        $doc->update([
            'embedding'              => json_encode($embedding),
            'embedding_generated_at' => now(),
        ]);
    }
}