<?php

namespace Tests\Unit;

use App\Models\ArchivedDocument;
use App\Services\SimilarityService;
use App\Support\SimilarityBand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The judge answers three questions and the code works out the score.
 *
 * Asked for a number alone, it wrote "both systems track graduates" about a
 * real duplicate and gave it 15, because one of the two covered more. Three
 * of twelve duplicates in the gold set were missed that way. Answers cannot
 * drift like that: the same answers always give the same percentage.
 */
class SimilarityJudgeRubricTest extends TestCase
{
    private function read(string $raw): ?array
    {
        $method = new ReflectionMethod(SimilarityService::class, 'readVerdict');
        $method->setAccessible(true);

        return $method->invoke(app(SimilarityService::class), $raw);
    }

    private function reply(mixed $things, mixed $users, mixed $features, int $ownScore = 15, bool $matches = false): string
    {
        return json_encode([
            'things' => 'graduates of a school vs graduates of a school',
            'same_things' => $things,
            'same_users' => $users,
            'same_features' => $features,
            'matches' => $matches,
            'score' => $ownScore,
            'reason' => 'Both track graduates.',
        ]);
    }

    public function test_the_same_thing_with_the_same_features_is_a_match_whatever_number_the_model_gave(): void
    {
        $verdict = $this->read($this->reply('yes', 'yes', 'yes', ownScore: 15, matches: false));

        $this->assertSame(95, $verdict['score']);
        $this->assertTrue($verdict['matches']);
        $this->assertSame(15, $verdict['own_score']);
        $this->assertSame(['things' => 'yes', 'users' => 'yes', 'features' => 'yes'], $verdict['answers']);
    }

    public function test_a_different_thing_is_not_a_match_whatever_number_the_model_gave(): void
    {
        $verdict = $this->read($this->reply('no', 'yes', 'yes', ownScore: 85, matches: true));

        $this->assertSame(25, $verdict['score']);
        $this->assertFalse($verdict['matches']);
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: int}> */
    public static function answersAndScores(): array
    {
        return [
            'same thing, users and features' => ['yes', 'yes', 'yes', 95],
            'same thing, some features extra or missing' => ['yes', 'yes', 'partly', 75],
            'same thing, different features' => ['yes', 'yes', 'no', 50],
            'same thing and features, other users' => ['yes', 'no', 'yes', 70],
            'same thing, some features, other users' => ['yes', 'no', 'partly', 50],
            'partly the same thing, same features' => ['partly', 'yes', 'yes', 60],
            'partly the same thing, some features' => ['partly', 'partly', 'partly', 35],
            'same kind of system for another thing' => ['no', 'yes', 'yes', 25],
            'nothing in common' => ['no', 'no', 'no', 0],
        ];
    }

    #[DataProvider('answersAndScores')]
    public function test_each_set_of_answers_has_one_score(string $things, string $users, string $features, int $score): void
    {
        $this->assertSame($score, SimilarityService::rubricScore(compact('things', 'users', 'features')));
        $this->assertSame($score, $this->read($this->reply($things, $users, $features))['score']);
    }

    public function test_the_thing_handled_outweighs_the_features(): void
    {
        $differentThing = SimilarityService::rubricScore(['things' => 'no', 'users' => 'yes', 'features' => 'yes']);
        $sameThing = SimilarityService::rubricScore(['things' => 'yes', 'users' => 'yes', 'features' => 'no']);

        $this->assertLessThan(30, $differentThing);
        $this->assertGreaterThan($differentThing, $sameThing);
    }

    /**
     * Measured on the gold set: the judge answered "partly" on the features
     * of nine of twelve duplicates, and at 60 all nine were missed.
     */
    public function test_the_same_thing_for_the_same_users_is_close_even_when_only_some_features_are_shared(): void
    {
        $this->assertGreaterThanOrEqual(
            SimilarityBand::CLOSE,
            SimilarityService::rubricScore(['things' => 'yes', 'users' => 'yes', 'features' => 'partly']),
        );
    }

    public function test_the_same_thing_for_other_users_with_only_some_features_shared_is_not_close(): void
    {
        $score = SimilarityService::rubricScore(['things' => 'yes', 'users' => 'no', 'features' => 'partly']);

        $this->assertSame('some', SimilarityBand::of($score));
    }

    public function test_a_different_thing_is_never_more_than_little_overlap(): void
    {
        foreach (['yes', 'partly', 'no'] as $users) {
            foreach (['yes', 'partly', 'no'] as $features) {
                $this->assertSame('little', SimilarityBand::of(SimilarityService::rubricScore(['things' => 'no'] + compact('users', 'features'))));
            }
        }
    }

    public function test_answers_written_loosely_are_still_read(): void
    {
        $verdict = $this->read($this->reply(true, ' Yes ', 'Partially'));

        $this->assertSame(['things' => 'yes', 'users' => 'yes', 'features' => 'partly'], $verdict['answers']);
        $this->assertSame(75, $verdict['score']);
    }

    public function test_a_reply_without_the_answers_keeps_the_models_own_number(): void
    {
        $verdict = $this->read('{"matches":false,"score":30,"reason":"Different."}');

        $this->assertNull($verdict['answers']);
        $this->assertSame(30, $verdict['score']);
        $this->assertFalse($verdict['matches']);
    }

    public function test_an_answer_that_is_not_yes_partly_or_no_keeps_the_models_own_number(): void
    {
        $verdict = $this->read($this->reply('yes', 'maybe', 'yes', ownScore: 70, matches: true));

        $this->assertNull($verdict['answers']);
        $this->assertSame(70, $verdict['score']);
        $this->assertTrue($verdict['matches']);
    }

    public function test_the_judge_is_asked_for_the_three_answers(): void
    {
        $method = new ReflectionMethod(SimilarityService::class, 'buildJudgePrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke(
            app(SimilarityService::class),
            ['title' => 'Graduate Tracer', 'domain' => 'Alumni affairs'],
            new ArchivedDocument(['title' => 'Alumni Tracking System', 'abstract' => 'Tracks graduates.']),
            80,
        );

        $this->assertStringContainsString('"same_things":"yes|partly|no","same_users":"yes|partly|no","same_features":"yes|partly|no"', $prompt);
        $this->assertStringContainsString('"yes" even when one system covers more or fewer of them', $prompt);
    }
}
