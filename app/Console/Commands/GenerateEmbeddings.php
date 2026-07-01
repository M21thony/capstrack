<?php

namespace App\Console\Commands;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use Illuminate\Console\Command;

class GenerateEmbeddings extends Command
{
    protected $signature = 'embeddings:generate {--fresh : Regenerate all embeddings}';

    protected $description = 'Generate vector embeddings for all archived documents';

    private array $stopWords = [
        // Articles & prepositions
        'a', 'an', 'the', 'of', 'for', 'and', 'or', 'in', 'to', 'is', 'are',
        'with', 'that', 'this', 'on', 'at', 'by', 'from', 'it', 'its', 'be',
        'as', 'was', 'were', 'has', 'have', 'had', 'do', 'does', 'did', 'not',
        'its', 'their', 'our', 'your', 'his', 'her',

        // Platform words — must match SimilarityService stop words
        'web', 'mobile', 'desktop', 'online', 'electronic', 'digital',
        'internet', 'android', 'ios', 'app', 'application', 'platform', 'portal',

        // Generic capstone/academic words
        'proposed', 'computerized', 'system', 'management', 'records', 'record',
        'using', 'based', 'automated', 'implementation', 'development', 'design',
        'modified', 'integrated', 'centralized', 'computer', 'computing',
        'software', 'hardware', 'database', 'information', 'technology',
        'generation', 'data', 'file', 'document', 'report', 'form',

        // Location noise
        'camarines', 'sur', 'nabua', 'cspc', 'iriga', 'city', 'colleges',
        'polytechnic', 'philippines', 'bicol', 'naga', 'buhi', 'bato',
        'rinconada', 'ocampo', 'polangui', 'albay', 'laguna', 'manila',

        // School/dept noise
        'college', 'department', 'university', 'school', 'cict', 'bsit',
        'bscs', 'institute', 'campus', 'high', 'national', 'junior', 'senior',
        'elementary', 'secondary', 'tertiary', 'academy', 'institute',

        // Common filler
        'version', 'study', 'case', 'research', 'project', 'capstone',
        'thesis', 'proposal', 'pre', 'post', 'new', 'old', 'local', 'global',
    ];

    public function handle(OllamaSSHClient $ollama): void
    {
        $query = ArchivedDocument::query();

        if (! $this->option('fresh')) {
            $query->whereNull('embedding');
        }

        $docs = $query->get();
        $total = $docs->count();

        if ($total === 0) {
            $this->info('All documents already have embeddings.');

            return;
        }

        $this->info("Generating clean embeddings for {$total} documents...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($docs as $doc) {
            try {
                $raw = $doc->title.' '.($doc->abstract ?? '');
                $text = $this->cleanText($raw);

                if (strlen(trim($text)) < 5) {
                    $text = $this->cleanText($doc->title);
                }

                $text = mb_substr($text, 0, 4000);

                $embedding = $ollama->embed($text);

                if (! empty($embedding)) {
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
        // Lowercase
        $text = strtolower($text);

        // Remove punctuation and slashes
        $text = preg_replace('/[\/\\\,\.\(\)\[\]\{\}:;!?"\'\-_]/', ' ', $text);

        // Split and filter
        $words = explode(' ', $text);
        $filtered = array_filter(
            $words,
            fn ($w) => ! in_array(trim($w), $this->stopWords)
                   && strlen(trim($w)) > 2
                   && ! is_numeric(trim($w))
        );

        return implode(' ', array_values($filtered));
    }
}
