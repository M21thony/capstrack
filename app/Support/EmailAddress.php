<?php

namespace App\Support;

/**
 * An email address as it came off a spreadsheet or out of a form, made into
 * the address it means.
 *
 * Lists copied from a website, Word or Google Forms carry characters nobody
 * can see - a non-breaking space, a zero-width space, a byte-order mark -
 * that trim() leaves in place. The address then looks right on screen and
 * fails every check ("'marempillo@my.cspc.edu.ph' is not a valid email").
 * No email address can hold a space of any kind, so all of them go.
 */
class EmailAddress
{
    public static function clean(?string $raw): string
    {
        // \p{Z}: every kind of space; \p{Cf}: zero-width and other invisible
        // format characters (including the byte-order mark); \s: tabs and
        // line breaks from a cell edited with Alt+Enter.
        return mb_strtolower((string) preg_replace('/[\p{Z}\p{Cf}\s]+/u', '', (string) $raw));
    }
}
