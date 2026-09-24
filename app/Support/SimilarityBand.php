<?php

namespace App\Support;

/**
 * How close a match is, in words rather than an alarm.
 *
 * The report used to say "Highly similar" in red under a red and green bar,
 * which read as a verdict the team had failed. The percentage keeps its
 * colour; the words stay calm, and say what the number means.
 */
class SimilarityBand
{
    /** At or above this, the checker has always called a title highly similar. */
    public const CLOSE = 65;

    public const SOME = 40;

    public const BANDS = [
        'close' => 'Close to existing work',
        'some' => 'Some overlap',
        'little' => 'Little overlap',
        'none' => 'Not scored',
    ];

    public static function of(int|float|null $score): string
    {
        return match (true) {
            $score === null => 'none',
            $score >= self::CLOSE => 'close',
            $score >= self::SOME => 'some',
            default => 'little',
        };
    }

    public static function label(int|float|null $score): string
    {
        return self::BANDS[self::of($score)];
    }

    /**
     * How many matches fall in each band, in the order they are shown.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<string, int>
     */
    public static function counts(array $matches): array
    {
        $counts = ['close' => 0, 'some' => 0, 'little' => 0];

        foreach ($matches as $match) {
            $band = self::of(self::scoreOf($match));

            if (isset($counts[$band])) {
                $counts[$band]++;
            }
        }

        return $counts;
    }

    /** A match nothing scored carries a retrieval rank, not a similarity. */
    public static function scoreOf(array $match): int|float|null
    {
        return ($match['assessed'] ?? true) ? ($match['score'] ?? null) : null;
    }
}
