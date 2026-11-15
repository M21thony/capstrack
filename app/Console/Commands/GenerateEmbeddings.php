<?php

namespace App\Console\Commands;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use Illuminate\Console\Command;

class GenerateEmbeddings extends Command
{
    protected $signature = 'embeddings:generate {--fresh : Regenerate all embeddings}';

    protected $description = 'Generate vector embeddings for all archived documents';

    // IMPORTANT: only true filler goes here - grammar, delivery-platform
    // words, and institution/location names. Domain-descriptive words like
    // "management", "system", "monitoring", "capstone", or "thesis" must
    // NOT be added even though they appear in almost every title: testing
    // against the real archive showed that stripping them made a
    // genuinely-related document score the same as a completely unrelated
    // one - the embedding model needs those words to tell projects apart.
    // Must match SimilarityService::removeStopWords()'s list exactly.
    private const STOP_WORDS = [
        // Articles & prepositions
        'a', 'an', 'the', 'of', 'for', 'and', 'or', 'in', 'to', 'is', 'are',
        'with', 'that', 'this', 'on', 'at', 'by', 'from', 'it', 'its', 'be',
        'as', 'was', 'were', 'has', 'have', 'had', 'do', 'does', 'did', 'not',
        'its', 'their', 'our', 'your', 'his', 'her',

        // Delivery-platform words (how it's delivered, not what it does)
        'using', 'based', 'web', 'mobile', 'desktop', 'online',
        'electronic', 'digital', 'internet', 'android', 'ios', 'app',
        'application', 'platform', 'portal',

        // Location noise
        'camarines', 'sur', 'nabua', 'cspc', 'iriga', 'city', 'colleges',
        'polytechnic', 'philippines', 'bicol', 'naga', 'buhi', 'bato',
        'rinconada', 'ocampo', 'polangui', 'albay', 'laguna', 'manila',

        // School/dept noise
        'college', 'department', 'university', 'school', 'cict', 'bsit',
        'bscs', 'institute', 'campus', 'high', 'national', 'junior', 'senior',
        'elementary', 'secondary', 'tertiary', 'academy', 'learning',
        'center', 'centre', 'colegio', 'graduate', 'provincial', 'vocational',
        'technical', 'community',

        // Common filler (not domain content)
        'pre', 'post',
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
                $embedding = $ollama->embed(self::textFor($doc));

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

    /** The text an archive row is embedded from: its title and abstract, cleaned. */
    public static function textFor(ArchivedDocument $doc): string
    {
        $text = self::cleanText($doc->title.' '.($doc->abstract ?? ''));

        if (strlen(trim($text)) < 5) {
            $text = self::cleanText($doc->title);
        }

        return mb_substr($text, 0, 4000);
    }

    private static function cleanText(string $text): string
    {
        // Lowercase
        $text = strtolower($text);

        // Remove punctuation and slashes
        $text = preg_replace('/[\/\\\,\.\(\)\[\]\{\}:;!?"\'\-_]/', ' ', $text);

        // Split and filter
        $words = explode(' ', $text);
        $filtered = array_filter(
            $words,
            fn ($w) => ! in_array(trim($w), self::STOP_WORDS)
                   && strlen(trim($w)) > 2
                   && ! is_numeric(trim($w))
        );

        return implode(' ', array_values($filtered));
    }
}
