<?php

namespace App\Console\Commands;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use Illuminate\Console\Command;

class GenerateEmbeddings extends Command
{
    protected $signature   = 'embeddings:generate {--fresh : Regenerate all embeddings}';
    protected $description = 'Generate vector embeddings for all archived documents';

    private array $stopWords = [
        // Generic system words
        'proposed', 'computerized', 'system', 'management', 'records', 'using',
        'based', 'web', 'online', 'automated', 'digital', 'application',
        'information', 'technology', 'development', 'design', 'implementation',
        'modified', 'integrated', 'centralized', 'electronic', 'mobile',
        // Location noise
        'camarines', 'sur', 'nabua', 'cspc', 'iriga', 'city', 'colleges',
        'polytechnic', 'philippines', 'bicol', 'naga',
        // School noise
        'college', 'department', 'university', 'school', 'cict', 'bsit',
        'bscs', 'institute', 'campus', 'high', 'national', 'junior', 'senior',
        // Common filler
        'a', 'an', 'the', 'of', 'for', 'and', 'or', 'in', 'to', 'is',
        'are', 'with', 'that', 'this', 'on', 'at', 'by', 'from',
    ];

    public function handle(OllamaSSHClient $ollama): void
    {
        $query = ArchivedDocument::query();

        if (!$this->option('fresh')) {
            $query->whereNull('embedding');
        }

        $docs  = $query->get();
        $total = $docs->count();

        if ($total === 0) {
            $this->info('All documents already have embeddings.');
            return;
        }

        $this->info("Generating clean embeddings for {$total} documents...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $failed  = 0;

        foreach ($docs as $doc) {
            try {
                // Clean title + abstract before embedding
                $raw  = $doc->title . ' ' . ($doc->abstract ?? '');
                $text = $this->cleanText($raw);

                if (empty(trim($text))) {
                    $text = $doc->title; // fallback to raw title
                }

                $text = mb_substr($text, 0, 4000);

                $embedding = $ollama->embed($text);

                if (!empty($embedding)) {
                    $doc->embedding = json_encode($embedding);
                    $doc->save();
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Failed doc {$doc->archived_id}: {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done! Success: {$success} | Failed: {$failed}");
    }

    private function cleanText(string $text): string
    {
        $words    = explode(' ', strtolower($text));
        $filtered = array_filter(
            $words,
            fn($w) => !in_array(trim($w, '.,/()'), $this->stopWords) && strlen(trim($w)) > 2
        );
        return implode(' ', $filtered);
    }
}