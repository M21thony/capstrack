<?php

namespace App\Console\Commands;

use App\Models\ArchivedDocument;
use App\Services\SimilarityService;
use App\Support\SimilarityBand;
use Illuminate\Console\Command;

/**
 * Measures the duplication checker against pairs a person has labelled.
 *
 * Each pair in the gold set is a pre-proposal, another study, and what a
 * person says they are to each other: duplicate, related or unrelated. The
 * other study is given with its abstract, or by the title of an archived one. Every
 * pair is scored the way a real check scores a match, and the result is
 * counted against the label. A study the checker calls "Close to existing
 * work" that the person did not label a duplicate is a false positive.
 *
 * The gold set must be labelled by a person. The checker's own answers, or
 * another model's, would only measure the checker against itself.
 */
class EvaluateDuplicationChecker extends Command
{
    private const LABELS = ['duplicate', 'related', 'unrelated'];

    /** The band a person's label should land in. */
    private const EXPECTED_BAND = ['duplicate' => 'close', 'related' => 'some', 'unrelated' => 'little'];

    protected $signature = 'duplication:evaluate
        {--file=tests/Fixtures/duplication-gold-set.json : The labelled pairs, relative to the project root}';

    protected $description = 'Measure the duplication checker against pairs labelled by a person: false positives, missed duplicates, and how far apart the labels score';

    public function handle(SimilarityService $similarity): int
    {
        $pairs = $this->readGoldSet(base_path($this->option('file')));

        if ($pairs === null) {
            return self::FAILURE;
        }

        $results = [];
        $bar = $this->output->createProgressBar(count($pairs));
        $bar->start();

        foreach ($pairs as $pair) {
            $other = $this->otherStudy($pair['other']);

            if ($other === null) {
                $this->newLine();
                $this->error("Pair {$pair['id']} has no abstract, and no archived study is titled: {$pair['other']['title']}");

                return self::FAILURE;
            }

            $startedAt = microtime(true);

            try {
                $scored = $similarity->scorePair($pair['pre_proposal'] + ['domain' => ''], $other);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Pair {$pair['id']} could not be scored: {$e->getMessage()}");

                return self::FAILURE;
            }

            $results[] = $scored + [
                'id' => $pair['id'],
                'label' => $pair['label'],
                'shown' => $scored['assessed'] ? $scored['score'] : null,
                'seconds' => microtime(true) - $startedAt,
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->reportPairs($results);
        $this->reportDisagreements($results);
        $this->reportSeparation($results);
        $this->reportErrors($results);

        return self::SUCCESS;
    }

    /**
     * The study a pre-proposal is compared with. One given without an
     * abstract is read from the archive, matched on its title the way
     * ArchivedAuthorsSeeder matches, so it is found in any copy of the archive.
     *
     * @param  array{title: string, abstract?: string}  $other
     */
    private function otherStudy(array $other): ?ArchivedDocument
    {
        if (isset($other['abstract'])) {
            return new ArchivedDocument(['title' => $other['title'], 'abstract' => $other['abstract']]);
        }

        $key = fn (?string $title) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $title));

        return ArchivedDocument::all()->first(fn (ArchivedDocument $doc) => $key($doc->title) === $key($other['title']));
    }

    /**
     * @return array<int, array{id: string, label: string, pre_proposal: array<string, mixed>, other: array{title: string, abstract?: string}}>|null
     */
    private function readGoldSet(string $path): ?array
    {
        if (! is_file($path)) {
            $this->error("No gold set at {$path}.");
            $this->line('Copy tests/Fixtures/duplication-gold-set.example.json to that path and replace its pairs with ones a person has labelled.');

            return null;
        }

        $pairs = json_decode((string) file_get_contents($path), true);

        if (! is_array($pairs) || $pairs === []) {
            $this->error('The gold set is not a JSON list of pairs.');

            return null;
        }

        foreach ($pairs as $position => $pair) {
            $name = $pair['id'] ?? 'number '.($position + 1);

            if (! in_array($pair['label'] ?? null, self::LABELS, true)) {
                $this->error("Pair {$name} needs a label: ".implode(', ', self::LABELS).'.');

                return null;
            }

            if (empty($pair['id']) || empty($pair['pre_proposal']['title']) || empty($pair['other']['title'])) {
                $this->error("Pair {$name} needs an id, a pre_proposal.title and an other.title.");

                return null;
            }
        }

        return $pairs;
    }

    /** @param  array<int, array<string, mixed>>  $results */
    private function reportPairs(array $results): void
    {
        $this->table(
            ['Pair', 'Label', 'Cosine', 'Embedding', 'Judge', 'Shown', 'Band', 'As labelled'],
            array_map(fn (array $r) => [
                $r['id'],
                $r['label'],
                number_format($r['cosine'], 2),
                $r['embedding_score'].'%',
                $r['judge_score'] === null ? '-' : $r['judge_score'].'%',
                $r['shown'] === null ? '-' : $r['shown'].'%',
                SimilarityBand::label($r['shown']),
                SimilarityBand::of($r['shown']) === self::EXPECTED_BAND[$r['label']] ? 'yes' : 'NO',
            ], $results),
        );
    }

    /**
     * What the judge said about each pair it did not score as labelled: the
     * place to look for why, before changing anything.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    private function reportDisagreements(array $results): void
    {
        $disagreements = array_filter($results, fn (array $r) => SimilarityBand::of($r['shown']) !== self::EXPECTED_BAND[$r['label']]);

        if ($disagreements === []) {
            return;
        }

        $this->line('Not as labelled, and the reason given');

        foreach ($disagreements as $r) {
            $this->line("  {$r['id']} (".($r['shown'] === null ? 'not scored' : $r['shown'].'%')."): {$r['reason']}");
        }

        $this->newLine();
    }

    /**
     * How far apart the labels score before the judge reads anything. If the
     * ranges overlap, no cut-off on this number can tell the labels apart.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    private function reportSeparation(array $results): void
    {
        $rows = [];

        foreach (self::LABELS as $label) {
            $cosines = array_column(array_filter($results, fn (array $r) => $r['label'] === $label), 'cosine');

            $rows[] = $cosines === []
                ? [$label, 0, '-', '-', '-']
                : [$label, count($cosines), number_format(min($cosines), 2), number_format(array_sum($cosines) / count($cosines), 2), number_format(max($cosines), 2)];
        }

        $this->line('Cosine similarity by label');
        $this->table(['Label', 'Pairs', 'Lowest', 'Average', 'Highest'], $rows);
    }

    /** @param  array<int, array<string, mixed>>  $results */
    private function reportErrors(array $results): void
    {
        $duplicates = array_filter($results, fn (array $r) => $r['label'] === 'duplicate');
        $others = array_filter($results, fn (array $r) => $r['label'] !== 'duplicate');

        $this->line('Called "'.SimilarityBand::BANDS['close'].'" ('.SimilarityBand::CLOSE.'% and above)');
        $this->table(
            ['', 'False positives', 'Missed duplicates'],
            [
                $this->errorRow('Score shown', $duplicates, $others, fn (array $r) => $r['shown']),
                $this->errorRow('Embedding alone', $duplicates, $others, fn (array $r) => $r['embedding_score']),
            ],
        );

        $asLabelled = count(array_filter($results, fn (array $r) => SimilarityBand::of($r['shown']) === self::EXPECTED_BAND[$r['label']]));
        $notScored = count(array_filter($results, fn (array $r) => $r['shown'] === null));

        $this->line('In the band its label expects: '.$this->share($asLabelled, count($results)));
        $this->line('Not scored by the judge: '.$this->share($notScored, count($results)));
        $this->line('Seconds per pair: '.number_format(array_sum(array_column($results, 'seconds')) / count($results), 1));
    }

    /**
     * @param  array<int, array<string, mixed>>  $duplicates
     * @param  array<int, array<string, mixed>>  $others
     * @param  callable(array<string, mixed>): (int|null)  $scoreOf
     * @return array<int, string>
     */
    private function errorRow(string $name, array $duplicates, array $others, callable $scoreOf): array
    {
        $isClose = fn (array $r) => SimilarityBand::of($scoreOf($r)) === 'close';

        return [
            $name,
            $this->share(count(array_filter($others, $isClose)), count($others)),
            $this->share(count(array_filter($duplicates, fn (array $r) => ! $isClose($r))), count($duplicates)),
        ];
    }

    private function share(int $count, int $total): string
    {
        return $total === 0 ? 'none in the set' : "{$count} of {$total} (".round($count / $total * 100).'%)';
    }
}
