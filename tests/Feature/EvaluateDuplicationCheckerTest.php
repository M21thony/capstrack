<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateEmbeddings;
use App\Models\ArchivedDocument;
use App\Models\Team;
use App\Services\OllamaSSHClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The checker is measured against pairs a person labelled, so a false
 * positive is a number that can be counted and not an impression.
 */
class EvaluateDuplicationCheckerTest extends TestCase
{
    use RefreshDatabase;

    private const GOLD_SET = 'storage/framework/testing/duplication-gold-set.json';

    protected function tearDown(): void
    {
        @unlink(base_path(self::GOLD_SET));

        parent::tearDown();
    }

    /** @param  array<int, array<string, mixed>>  $pairs */
    private function goldSet(array $pairs): void
    {
        @mkdir(dirname(base_path(self::GOLD_SET)), 0755, true);
        file_put_contents(base_path(self::GOLD_SET), json_encode($pairs));
    }

    /** @return array<string, mixed> */
    private function pair(string $id, string $label, string $otherTitle): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'pre_proposal' => ['title' => 'Patient Records System', 'problem_statement' => 'Records are kept on paper.'],
            'other' => ['title' => $otherTitle, 'abstract' => 'An abstract about '.$otherTitle.'.'],
        ];
    }

    /**
     * Every text embeds to the same vector, so the embedding alone calls
     * every pair close. The judge answers by the title it is shown.
     *
     * @param  array<string, string>  $verdictsByTitle
     */
    private function fakeModel(array $verdictsByTitle): void
    {
        $answer = function (string $prompt) use ($verdictsByTitle): string {
            foreach ($verdictsByTitle as $title => $verdict) {
                if (str_contains($prompt, $title)) {
                    return $verdict;
                }
            }

            return 'no verdict';
        };

        $this->mock(OllamaSSHClient::class, function ($mock) use ($answer) {
            $mock->shouldReceive('embed')->andReturn([1.0, 0.0]);
            $mock->shouldReceive('generateMany')->andReturnUsing(fn (array $prompts) => array_map($answer, $prompts));
            $mock->shouldReceive('generate')->andReturnUsing($answer);
        });
    }

    private function run_evaluation(): string
    {
        Artisan::call('duplication:evaluate', ['--file' => self::GOLD_SET]);

        return Artisan::output();
    }

    public function test_a_study_called_close_that_a_person_did_not_label_a_duplicate_is_a_false_positive(): void
    {
        $this->goldSet([
            $this->pair('p1', 'duplicate', 'Rural Health Unit Records'),
            $this->pair('p2', 'unrelated', 'Tilapia Pond Feeder'),
            $this->pair('p3', 'unrelated', 'Smart Door Lock'),
            $this->pair('p4', 'related', 'Medicine Inventory'),
        ]);
        $this->fakeModel([
            'Rural Health Unit Records' => '{"matches": true, "score": 90, "reason": "Same system."}',
            'Tilapia Pond Feeder' => '{"matches": true, "score": 80, "reason": "Wrongly called the same."}',
            'Smart Door Lock' => '{"matches": false, "score": 10, "reason": "Different."}',
            'Medicine Inventory' => '{"matches": false, "score": 50, "reason": "Same place, other purpose."}',
        ]);

        $output = $this->run_evaluation();

        $this->assertMatchesRegularExpression('/Score shown\s+\|\s+1 of 3 \(33%\)\s+\|\s+0 of 1 \(0%\)/', $output);
        $this->assertMatchesRegularExpression('/Embedding alone\s+\|\s+3 of 3 \(100%\)\s+\|\s+0 of 1 \(0%\)/', $output);
        $this->assertStringContainsString('In the band its label expects: 3 of 4 (75%)', $output);
        $this->assertStringContainsString('Not scored by the judge: 0 of 4 (0%)', $output);
    }

    public function test_a_duplicate_scored_below_close_is_a_missed_duplicate(): void
    {
        $this->goldSet([$this->pair('p1', 'duplicate', 'Rural Health Unit Records')]);
        $this->fakeModel(['Rural Health Unit Records' => '{"matches": false, "score": 30, "reason": "Wrongly called different."}']);

        $output = $this->run_evaluation();

        $this->assertMatchesRegularExpression('/Score shown\s+\|\s+none in the set\s+\|\s+1 of 1 \(100%\)/', $output);
        $this->assertStringContainsString('p1 (30%): Wrongly called different.', $output);
    }

    public function test_a_pair_the_judge_could_not_answer_is_counted_as_not_scored_and_never_as_close(): void
    {
        $this->goldSet([$this->pair('p1', 'unrelated', 'Tilapia Pond Feeder')]);
        $this->fakeModel([]);

        $output = $this->run_evaluation();

        $this->assertStringContainsString('Not scored by the judge: 1 of 1 (100%)', $output);
        $this->assertMatchesRegularExpression('/Score shown\s+\|\s+0 of 1 \(0%\)/', $output);
    }

    public function test_the_judges_answers_decide_the_score_and_its_own_number_is_shown_beside_it(): void
    {
        $this->goldSet([$this->pair('p1', 'duplicate', 'Rural Health Unit Records')]);
        $this->fakeModel([
            'Rural Health Unit Records' => '{"same_things": "yes", "same_users": "yes", "same_features": "yes", "matches": false, "score": 15, "reason": "One covers more."}',
        ]);

        $output = $this->run_evaluation();

        $this->assertMatchesRegularExpression('/p1\s+\|\s+duplicate\s+\|\s+1\.00\s+\|\s+100%\s+\|\s+yes \/ yes \/ yes\s+\|\s+15%\s+\|\s+95%\s+\|\s+95%/', $output);
        $this->assertMatchesRegularExpression('/Score shown\s+\|\s+none in the set\s+\|\s+0 of 1 \(0%\)/', $output);
        $this->assertMatchesRegularExpression("/The model's own number\s+\|\s+none in the set\s+\|\s+1 of 1 \(100%\)/", $output);
    }

    public function test_the_cosine_range_of_each_label_is_reported(): void
    {
        $this->goldSet([$this->pair('p1', 'duplicate', 'Rural Health Unit Records')]);
        $this->fakeModel(['Rural Health Unit Records' => '{"matches": true, "score": 90}']);

        $output = $this->run_evaluation();

        $this->assertMatchesRegularExpression('/duplicate\s+\|\s+1\s+\|\s+1\.00\s+\|\s+1\.00\s+\|\s+1\.00/', $output);
        $this->assertMatchesRegularExpression('/unrelated\s+\|\s+0\s+\|/', $output);
    }

    public function test_it_fails_when_there_is_no_gold_set(): void
    {
        $this->artisan('duplication:evaluate', ['--file' => self::GOLD_SET])
            ->expectsOutputToContain('No gold set at')
            ->assertFailed();
    }

    public function test_it_fails_on_a_label_it_does_not_know(): void
    {
        $this->goldSet([$this->pair('p1', 'similar', 'Rural Health Unit Records')]);

        $this->artisan('duplication:evaluate', ['--file' => self::GOLD_SET])
            ->expectsOutputToContain('Pair p1 needs a label: duplicate, related, unrelated.')
            ->assertFailed();
    }

    public function test_it_fails_when_the_model_cannot_be_reached(): void
    {
        $this->goldSet([$this->pair('p1', 'duplicate', 'Rural Health Unit Records')]);
        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('embed')->andThrow(new \RuntimeException('connection refused'));
        });

        $this->artisan('duplication:evaluate', ['--file' => self::GOLD_SET])
            ->expectsOutputToContain('Pair p1 could not be scored: connection refused')
            ->assertFailed();
    }

    public function test_a_study_given_by_title_alone_is_read_from_the_archive(): void
    {
        $team = Team::create(['team_name' => 'Archive Source Team']);
        ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'PARKMATE: RFID-Based Parking Management System',
            'abstract' => 'Vehicles carry a tag that is read at the gate.',
            'academic_year' => 2025,
            'program' => 'BSIT',
            'file_path' => 'archives/parkmate.pdf',
        ]);
        $pair = $this->pair('p1', 'duplicate', 'parkmate: rfid based parking management  system');
        unset($pair['other']['abstract']);
        $this->goldSet([$pair]);

        $prompts = [];
        $this->mock(OllamaSSHClient::class, function ($mock) use (&$prompts) {
            $mock->shouldReceive('embed')->andReturn([1.0, 0.0]);
            $mock->shouldReceive('generateMany')->andReturnUsing(function (array $sent) use (&$prompts) {
                $prompts = $sent;

                return ['{"matches": true, "score": 90}'];
            });
        });

        $this->artisan('duplication:evaluate', ['--file' => self::GOLD_SET])->assertSuccessful();

        $this->assertStringContainsString('Vehicles carry a tag that is read at the gate.', $prompts[0]);
    }

    public function test_it_fails_when_a_study_given_by_title_alone_is_not_in_the_archive(): void
    {
        $pair = $this->pair('p1', 'duplicate', 'A Study Nobody Archived');
        unset($pair['other']['abstract']);
        $this->goldSet([$pair]);
        $this->fakeModel([]);

        $this->artisan('duplication:evaluate', ['--file' => self::GOLD_SET])
            ->expectsOutputToContain('Pair p1 has no abstract, and no archived study is titled: A Study Nobody Archived')
            ->assertFailed();
    }

    /** @return array<string, array{0: string}> */
    public static function draftGoldSets(): array
    {
        return [
            'the set the scoring was adjusted on' => ['tests/Fixtures/duplication-gold-set.json'],
            'the set kept aside to check it' => ['tests/Fixtures/duplication-gold-set-2.json'],
        ];
    }

    #[DataProvider('draftGoldSets')]
    public function test_a_draft_gold_set_has_a_duplicate_a_related_and_an_unrelated_study_for_each_pre_proposal(string $file): void
    {
        $pairs = json_decode(file_get_contents(base_path($file)), true);
        $archived = collect(json_decode(file_get_contents(database_path('seeders/data/archived_authors.json')), true))->pluck('title');

        $this->assertSame(['duplicate' => 12, 'related' => 12, 'unrelated' => 12], array_count_values(array_column($pairs, 'label')));
        $this->assertCount(36, array_unique(array_column($pairs, 'id')));

        foreach ($pairs as $pair) {
            $this->assertContains($pair['other']['title'], $archived, "{$pair['id']} names a study that is not in the archive.");
            $this->assertNotEmpty($pair['why']);
            $this->assertNotEmpty($pair['pre_proposal']['problem_statement']);
        }
    }

    public function test_the_set_kept_aside_shares_no_pair_with_the_set_the_scoring_was_adjusted_on(): void
    {
        $key = fn (array $pair) => $pair['pre_proposal']['title'].'|'.$pair['other']['title'];
        $first = array_map($key, json_decode(file_get_contents(base_path('tests/Fixtures/duplication-gold-set.json')), true));
        $second = array_map($key, json_decode(file_get_contents(base_path('tests/Fixtures/duplication-gold-set-2.json')), true));

        $this->assertSame([], array_intersect($first, $second));
    }

    public function test_the_example_gold_set_is_one_the_command_accepts(): void
    {
        $this->fakeModel([]);

        $this->artisan('duplication:evaluate', ['--file' => 'tests/Fixtures/duplication-gold-set.example.json'])
            ->assertSuccessful();
    }

    public function test_an_archive_row_is_embedded_from_its_title_and_abstract_without_filler_words(): void
    {
        $doc = new ArchivedDocument(['title' => 'Web-Based Inventory System of CSPC', 'abstract' => 'Tracks the stock of 25 items.']);

        $this->assertSame('inventory system tracks stock items', GenerateEmbeddings::textFor($doc));
    }
}
