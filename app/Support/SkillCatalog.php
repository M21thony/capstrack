<?php

namespace App\Support;

/**
 * The curated list of capstone skills, grouped by area.
 *
 * Experts type their own expertise as free text, which is deliberate - it
 * lets someone record a skill nobody anticipated. The cost is drift: the
 * same skill arrives as "Web Development", "Web Dev" and "web development",
 * and a raw list of everything in use runs to dozens of near-duplicates.
 *
 * This catalogue is the shape imposed on that: the expert profile offers it
 * as suggestions when someone types their expertise, and the student expert
 * list uses it to group the skills actually in use into a handful of
 * browsable sections instead of one long alphabetical dump. Anything that
 * matches nothing here still shows up - under "Other skills" - so a genuinely
 * new expertise is never hidden, only ungrouped.
 *
 * Both consumers read from here so the wording can't drift apart between
 * the screen that collects skills and the screen that filters by them.
 */
class SkillCatalog
{
    public const OTHER_GROUP = 'Other skills';

    /** @var array<string, string[]> */
    private const GROUPS = [
        'Research & Writing (Adviser)' => [
            'Research Methodology', 'Quantitative Research', 'Qualitative Research',
            'Statistical Analysis', 'Data Gathering & Instruments', 'Survey Design',
            'Literature Review', 'Research Ethics', 'Thesis / Capstone Writing',
            'Citation & Referencing (APA)', 'Technical Writing', 'Defense Preparation',
        ],
        'Language & Editing (Grammarian)' => [
            'Grammar & Syntax', 'Copy Editing', 'Proofreading', 'Academic Tone & Style',
            'Sentence Structure', 'Punctuation & Mechanics', 'Clarity & Conciseness',
            'Plagiarism Checking', 'Document Formatting', 'APA Format Compliance',
        ],
        'Software & Development' => [
            'Web Development', 'Mobile Apps', 'Desktop Applications', 'Database Systems',
            'API Development', 'Software Testing / QA', 'System Analysis & Design',
            'UI/UX Design', 'Game Development', 'Version Control (Git)',
        ],
        'Data, AI & Systems' => [
            'Machine Learning', 'Artificial Intelligence', 'Data Analytics',
            'Data Mining', 'Data Visualization', 'Internet of Things (IoT)',
            'Cloud Computing', 'Cybersecurity', 'Network Systems', 'Embedded Systems',
        ],
        'Information & Library Science' => [
            'Information Management', 'Records Management', 'Knowledge Organization',
            'Cataloguing & Classification', 'Digital Archiving', 'Library Systems',
        ],
    ];

    /** @return array<string, string[]> */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    public static function groupNames(): array
    {
        return array_keys(self::GROUPS);
    }

    /** Every curated skill, flat. */
    public static function allSkills(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public static function itemsIn(string $group): array
    {
        return self::GROUPS[$group] ?? [];
    }

    public static function isGroup(string $group): bool
    {
        return array_key_exists($group, self::GROUPS);
    }

    /**
     * Which group a free-text skill belongs to.
     *
     * Exact wording wins; failing that, either string containing the other
     * catches the common drift ("Web Dev (React)" -> Software & Development,
     * "Machine Learning" -> Data, AI & Systems). Longer curated names are
     * tried first so "Data Analytics" isn't claimed by a shorter entry that
     * happens to be a substring of it.
     */
    public static function groupFor(string $skill): string
    {
        $needle = self::normalise($skill);

        if ($needle === '') {
            return self::OTHER_GROUP;
        }

        foreach (self::GROUPS as $group => $items) {
            foreach ($items as $item) {
                if (self::normalise($item) === $needle) {
                    return $group;
                }
            }
        }

        $candidates = [];
        foreach (self::GROUPS as $group => $items) {
            foreach ($items as $item) {
                $candidates[] = [$group, self::normalise($item)];
            }
        }

        usort($candidates, fn ($a, $b) => mb_strlen($b[1]) <=> mb_strlen($a[1]));

        foreach ($candidates as [$group, $item]) {
            if ($item !== '' && (str_contains($needle, $item) || str_contains($item, $needle))) {
                return $group;
            }
        }

        return self::OTHER_GROUP;
    }

    public static function normalise(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
