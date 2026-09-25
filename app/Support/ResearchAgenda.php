<?php

namespace App\Support;

/**
 * A pre-proposal's research agenda and priority areas, one item each.
 *
 * The form lists them as bullets, and a scan does not always keep them
 * apart: "• Applications and Systems Development • e-Services" came through
 * as one item, and showed as one long sentence with bullets in it.
 */
class ResearchAgenda
{
    /** Bullets a PDF or the model puts before an item. */
    private const BULLETS = '•●▪◦‣∙·○■□➢➤►▶✓✔\x{F0B7}\x{F0A7}';

    /**
     * Every item on its own, bullets and numbering taken off.
     *
     * @param  array<int, mixed>|string|null  $agenda
     * @return string[]
     */
    public static function items(array|string|null $agenda): array
    {
        $items = [];

        foreach ((array) $agenda as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            // A new line, a semicolon or a bullet starts the next item.
            foreach (preg_split('/\s*(?:[\r\n;]+|['.self::BULLETS.'])\s*/u', $entry, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                // "1." "a)" "- " at the start is numbering, not the item.
                $part = trim(preg_replace('/^(?:[-–—*]+|\(?[0-9]{1,2}[.)]|\(?[a-z][.)])\s+/iu', '', trim($part)));

                if ($part !== '' && ! in_array(mb_strtolower($part), array_map('mb_strtolower', $items), true)) {
                    $items[] = $part;
                }
            }
        }

        return $items;
    }
}
