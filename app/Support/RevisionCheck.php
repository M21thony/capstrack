<?php

namespace App\Support;

use App\Models\CapstoneDocument;
use App\Models\Deliverable;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Is this the file the student means to send? Asked before a draft goes up,
 * so Chapter 3 sent as the next version of Chapter 1 is caught while it can
 * still be swapped - not by an adviser whose comparison then shows every word
 * as changed.
 *
 * Two questions, each only a warning (a student may really have rewritten a
 * chapter):
 *
 *   heading   the first pages name a different chapter from the line picked
 *   unrelated the file shares almost nothing with the team's last upload for
 *             that line
 *
 * "Shares" is measured in three-word phrases. A revision keeps most of its
 * phrases; another chapter of the same study shares only a handful ("of the
 * system"), however many single words they have in common.
 */
class RevisionCheck
{
    /** Below this share of phrases in common, it is not a revision of the last one. */
    public const MIN_SHARED = 0.25;

    /** Too little text to judge - a cover page, a scan with a few stray words. */
    private const MIN_PHRASES = 20;

    private const NUMBERS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6];

    /**
     * @param  int[]  $teamStudentIds  whose earlier uploads count as "the last one"
     * @return array<int, array{type: string, message: string}>
     */
    public static function warnings(string $path, string $fileName, Deliverable $item, array $teamStudentIds): array
    {
        if ($item->kind !== Deliverable::DOCUMENT) {
            return [];
        }

        $pages = self::pages($path);

        if ($pages === []) {
            return [];
        }

        $warnings = [];
        $expected = self::chapterNumber($item->chapter);
        $found = self::chapterHeading($pages);

        if ($expected !== null && $found !== null && $found !== $expected) {
            $warnings[] = [
                'type' => 'heading',
                'message' => "The first page of {$fileName} says Chapter {$found}, but you are sending it as “{$item->title}”.",
            ];
        }

        $last = CapstoneDocument::whereIn('student_id', $teamStudentIds)
            ->where('deliverable_id', $item->id)
            ->orderByDesc('file_version')
            ->first();

        if ($last && str_ends_with(strtolower($last->file_path), '.pdf') && Storage::disk('local')->exists($last->file_path)) {
            $shared = self::shared(implode(' ', self::pages(Storage::disk('local')->path($last->file_path))), implode(' ', $pages));

            if ($shared !== null && $shared < self::MIN_SHARED) {
                $warnings[] = [
                    'type' => 'unrelated',
                    'message' => "{$fileName} shares almost no text with your last “{$item->title}” (version {$last->file_version}). "
                        .'A revision keeps most of its words, so this may be the wrong file.',
                ];
            }
        }

        return $warnings;
    }

    /** @return string[] each page's text, or none when the PDF cannot be read */
    public static function pages(string $path): array
    {
        try {
            return array_map(fn ($page) => (string) $page->getText(), (new Parser)->parseFile($path)->getPages());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The chapter the first pages name, or null when they name none - or
     * several, as a table of contents does.
     *
     * @param  string[]  $pages
     */
    public static function chapterHeading(array $pages): ?int
    {
        foreach (array_slice($pages, 0, 2) as $text) {
            preg_match_all('/\bchapter\s+([0-9]{1,2}|[ivx]{1,4}|one|two|three|four|five|six)\b/i', $text, $m);
            $numbers = array_values(array_unique(array_filter(array_map(self::chapterNumber(...), $m[1]))));

            if (count($numbers) === 1) {
                return $numbers[0];
            }

            if (count($numbers) > 1) {
                return null;
            }
        }

        return null;
    }

    /** "3", "III", "three" and "Chapter 3" are all 3; "revised" and "final" are not a number. */
    public static function chapterNumber(?string $chapter): ?int
    {
        $word = strtolower(trim(preg_replace('/^chapter\s*/i', '', (string) $chapter)));

        if (ctype_digit($word) && $word !== '') {
            return (int) $word;
        }

        if (isset(self::NUMBERS[$word])) {
            return self::NUMBERS[$word];
        }

        $roman = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10];

        return $roman[$word] ?? null;
    }

    /**
     * The share of three-word phrases two texts have in common, out of the
     * shorter one's; null when either is too short to tell.
     */
    public static function shared(string $a, string $b): ?float
    {
        $phrasesA = self::phrases($a);
        $phrasesB = self::phrases($b);

        if (count($phrasesA) < self::MIN_PHRASES || count($phrasesB) < self::MIN_PHRASES) {
            return null;
        }

        return count(array_intersect_key($phrasesA, $phrasesB)) / min(count($phrasesA), count($phrasesB));
    }

    /** @return array<string, true> */
    private static function phrases(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $m);
        $words = $m[0];
        $out = [];

        for ($i = 0; $i + 2 < count($words); $i++) {
            $out[$words[$i].' '.$words[$i + 1].' '.$words[$i + 2]] = true;
        }

        return $out;
    }
}
