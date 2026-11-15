<?php

namespace App\Jobs;

use App\Console\Commands\GenerateEmbeddings;
use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Gives one archived capstone the embedding the duplication check compares
 * against, in the background so publishing never waits on the AI server.
 *
 * Only a published one: an entry still waiting for the CaPSA/TSA must stay
 * out of the check. If the AI server is down the row simply stays without an
 * embedding, and "Update embeddings" on the Archive page picks it up later.
 */
class EmbedArchive implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $archiveId) {}

    public function handle(OllamaSSHClient $ollama): void
    {
        $archive = ArchivedDocument::published()->find($this->archiveId);

        if (! $archive) {
            return;
        }

        try {
            $embedding = $ollama->embed(GenerateEmbeddings::textFor($archive));
        } catch (\Throwable $e) {
            Log::warning('Archive embedding failed', ['archived_id' => $this->archiveId, 'error' => $e->getMessage()]);

            return;
        }

        if ($embedding) {
            $archive->forceFill(['embedding' => json_encode($embedding)])->save();
        }
    }
}
