<?php

namespace Tests\Unit;

use App\Services\SimilarityService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Reading a verdict out of whatever the model actually wrote.
 *
 * A verdict that fails to parse does not fail loudly - it reaches the student
 * as "Not assessed" beside a study the model had in fact judged, which reads
 * as "the checker skipped this one". That happened on a real scan: the
 * fine-tuned judge closed its object early and carried on, and the parser,
 * matching greedily from the first brace to the LAST one in the reply,
 * swallowed the malformed remainder and threw the whole verdict away.
 *
 * The fields that completed before the model went wrong are worth keeping -
 * the score and the reason come first in the schema, so they almost always
 * have.
 */
class SimilarityVerdictParsingTest extends TestCase
{
    private function read(string $raw): ?array
    {
        $method = new ReflectionMethod(SimilarityService::class, 'readVerdict');
        $method->setAccessible(true);

        return $method->invoke(app(SimilarityService::class), $raw);
    }

    /** The exact shape that produced "Not assessed" on a real scan. */
    public function test_a_stray_closing_brace_does_not_discard_the_verdict(): void
    {
        $verdict = $this->read(
            '{"matches":false,"score":30,"reason":"One handles campus repairs, the other parking.",'
            .'"similarities":"none beyond generic wording",'
            .'"differences":"campus repairs versus students, staff"}, "suggestion": "Name your users."}'
        );

        $this->assertNotNull($verdict, 'a salvageable answer must not be thrown away');
        $this->assertSame(30, $verdict['score']);
        $this->assertStringContainsString('campus repairs', $verdict['reason']);
    }

    public function test_a_second_object_after_the_first_is_ignored(): void
    {
        $verdict = $this->read(
            '{"matches":true,"score":70,"reason":"Same subject.","similarities":"repair requests",'
            .'"differences":"scope","suggestion":"Narrow it."}'
            .'{"matches":false,"score":5,"reason":"Changed my mind."}'
        );

        $this->assertSame(70, $verdict['score'], 'the first object is the answer');
        $this->assertSame('Same subject.', $verdict['reason']);
    }

    /** An object nested in a value must not be mistaken for the end of the answer. */
    public function test_a_nested_object_does_not_truncate_the_verdict(): void
    {
        $verdict = $this->read(
            '{"matches":true,"score":66,"meta":{"model":"v3"},"reason":"Same subject.",'
            .'"similarities":"door access","differences":"scope","suggestion":"Narrow it."}'
        );

        $this->assertSame(66, $verdict['score']);
        $this->assertSame('Narrow it.', $verdict['suggestion'], 'fields after the nested object survive');
    }

    public function test_markdown_fences_and_leading_prose_are_stripped(): void
    {
        foreach ([
            "```json\n".'{"matches":true,"score":80,"reason":"Same.","similarities":"a","differences":"b","suggestion":"c"}'."\n```",
            'Here is my verdict: {"matches":true,"score":80,"reason":"Same.","similarities":"a","differences":"b","suggestion":"c"}',
        ] as $raw) {
            $this->assertSame(80, $this->read($raw)['score']);
        }
    }

    /** The real reply that lost the closest study in a MediTrack scan. */
    public function test_a_missing_comma_between_fields_does_not_lose_the_verdict(): void
    {
        $verdict = $this->read(
            '{"matches": true, "score": 75, "reason": "Both digitalise medical inventory.",'
            .'"similarities": "Medical inventory and patient records",'
            .'"differences": "One covers patient records; the other inventory." '
            .'"suggestion": "Narrow the scope."}'
        );

        $this->assertNotNull($verdict, 'one missing comma must not cost the whole verdict');
        $this->assertSame(75, $verdict['score']);
        $this->assertSame('Narrow the scope.', $verdict['suggestion']);
    }

    public function test_a_trailing_comma_is_tolerated(): void
    {
        $verdict = $this->read(
            '{"matches": false, "score": 20, "reason": "Different subjects.",'
            .'"similarities": "none", "differences": "x", "suggestion": "y",}'
        );

        $this->assertSame(20, $verdict['score']);
    }

    /** The repair is punctuation only - it must never invent a verdict. */
    public function test_repairing_cannot_manufacture_a_score(): void
    {
        $this->assertNull($this->read('{"matches": true "reason": '));
    }

    public function test_a_reply_with_no_object_is_still_rejected(): void
    {
        $this->assertNull($this->read('I am not able to answer that.'));
        $this->assertNull($this->read(''));
    }

    /** Salvaging must not invent a score the model never gave. */
    public function test_a_missing_score_stays_null(): void
    {
        $verdict = $this->read('{"matches":true,"reason":"Same subject.","similarities":"a"}');

        $this->assertNotNull($verdict);
        $this->assertNull($verdict['score']);
    }
}
