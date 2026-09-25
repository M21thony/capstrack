<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\Setting;

/**
 * The sections the department runs.
 *
 * Sections used to be worked out from the students enrolled in them, which is
 * true once a term is under way and useless before it: a fresh install has no
 * students, so it has no sections, so nobody can be made a CaPSA - and a
 * CaPSA is who uploads the students. That is a knot with no end to pull.
 *
 * An Admin now states them up front. What a student is enrolled in still
 * counts, so an install that has been running keeps every section it already
 * had without anybody typing them in.
 */
class SectionCatalog
{
    public const KEY = 'sections';

    /** Section letters an Admin may choose between. */
    public const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** Year levels a section label can carry. */
    public const YEARS = [1, 2, 3, 4];

    /**
     * The year a section is added for. Capstone starts in the second
     * semester of 3rd year, so that is where an Admin's sections begin; a
     * 4th-year section comes from the student list (or Year Promotion) once
     * students are in it, and its CaPSA is assigned then - often not the
     * same one as in 3rd year.
     */
    public const STARTING_YEAR = 3;

    /**
     * The sections an Admin has written down.
     *
     * @return string[]
     */
    public static function defined(): array
    {
        $raw = Setting::get(self::KEY);

        if (! $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @param  string[]  $sections */
    public static function store(array $sections): void
    {
        Setting::set(self::KEY, json_encode(self::tidy($sections)));
    }

    /**
     * Every label this generates, for one program, one year, over a range.
     *
     * "BSIT", 4, A..H gives BSIT-4A through BSIT-4H - the shape
     * Student::computeSectionLabel() produces from an enrolled student, so a
     * section written down here and the same section arriving on a student
     * list are the same string.
     *
     * @return string[]
     */
    public static function range(string $program, int $year, string $from, string $to): array
    {
        $program = strtoupper(trim($program));
        $from = strtoupper(substr(trim($from), 0, 1));
        $to = strtoupper(substr(trim($to), 0, 1));

        $start = strpos(self::LETTERS, $from);
        $end = strpos(self::LETTERS, $to);

        if (! in_array($program, CapstoneExpert::PROGRAMS, true)
            || ! in_array($year, self::YEARS, true)
            || $start === false || $end === false) {
            return [];
        }

        // Given B to A, read it the way round it was obviously meant.
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $out = [];

        for ($i = $start; $i <= $end; $i++) {
            $out[] = $program.'-'.$year.self::LETTERS[$i];
        }

        return $out;
    }

    /**
     * Sorted, de-duplicated, and in the order a person reads them: by
     * program, then year, then letter - so BSIT-4B follows BSIT-4A rather
     * than BSIT-10A, which is what a plain string sort would give.
     *
     * @param  string[]  $sections
     * @return string[]
     */
    public static function tidy(array $sections): array
    {
        $clean = array_values(array_unique(array_filter(array_map(
            fn ($s) => strtoupper(trim((string) $s)),
            $sections
        ))));

        usort($clean, function ($a, $b) {
            $pa = self::parts($a);
            $pb = self::parts($b);

            return [$pa[0], $pa[1], $pa[2]] <=> [$pb[0], $pb[1], $pb[2]];
        });

        return $clean;
    }

    /** @return array{0: string, 1: int, 2: string} program, year, letter */
    private static function parts(string $label): array
    {
        if (preg_match('/^([A-Z]+)-(\d+)([A-Z]*)$/', $label, $m)) {
            return [$m[1], (int) $m[2], $m[3]];
        }

        return [$label, 0, ''];
    }
}
