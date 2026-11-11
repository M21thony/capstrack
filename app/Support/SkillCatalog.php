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

    /**
     * The two groups every capstone needs, whichever programme it is for.
     *
     * Research craft and language are about writing a capstone, not about a
     * discipline: a BLIS study and a BSCS one both need a literature review
     * and both need proofreading. Splitting these per programme would have
     * meant four copies of the same list.
     */
    private const SHARED_GROUPS = [
        'Research & Writing (Adviser)',
        'Language & Editing (Grammarian)',
    ];

    /**
     * Which programme each technical skill belongs to.
     *
     * CaPSA/TSA asked for this: the four programmes are not the same subject, and
     * offering a BLIS adviser "Embedded Systems" - or a BSCS one "Cataloguing
     * & Classification" - made the picker longer without making it more
     * useful. A skill can belong to several, because they genuinely overlap:
     * every programme here builds databases and every one of them worries
     * about security.
     *
     * The split follows what each degree actually teaches:
     *   BSCS  computation itself - algorithms, AI, systems
     *   BSIT  applying and running it - deployment, networks, infrastructure
     *   BSIS  what an organisation does with it - process, records, decisions
     *   BLIS  organising information so it can be found again
     *
     * It is a starting point and CaPSA/TSA's to correct, not a rule: an expert
     * can still type any skill at all, including one from another programme.
     */
    private const PROGRAM_SKILLS = [
        'BSCS' => [
            'Machine Learning', 'Artificial Intelligence', 'Data Mining', 'Data Analytics',
            'Data Visualization', 'Embedded Systems', 'Game Development',
            'Software Testing / QA', 'System Analysis & Design', 'API Development',
            'Database Systems', 'Desktop Applications', 'Version Control (Git)',
            'Cloud Computing', 'Cybersecurity',
        ],
        'BSIT' => [
            'Web Development', 'Mobile Apps', 'Database Systems', 'API Development',
            'Software Testing / QA', 'System Analysis & Design', 'UI/UX Design',
            'Game Development', 'Version Control (Git)', 'Network Systems',
            'Cloud Computing', 'Cybersecurity', 'Internet of Things (IoT)',
            'Embedded Systems', 'Desktop Applications',
        ],
        'BSIS' => [
            'System Analysis & Design', 'Database Systems', 'Data Analytics',
            'Data Visualization', 'Information Management', 'Records Management',
            'Web Development', 'UI/UX Design', 'Cloud Computing', 'Cybersecurity',
            'Software Testing / QA',
        ],
        'BLIS' => [
            'Information Management', 'Records Management', 'Knowledge Organization',
            'Cataloguing & Classification', 'Digital Archiving', 'Library Systems',
            'Database Systems', 'Web Development', 'Data Visualization', 'UI/UX Design',
        ],
    ];

    /** The programmes a skill is offered for, empty when it is offered to all. */
    public static function programsFor(string $skill): array
    {
        $needle = self::normalise($skill);

        $codes = [];

        foreach (self::PROGRAM_SKILLS as $code => $skills) {
            foreach ($skills as $item) {
                if (self::normalise($item) === $needle) {
                    $codes[] = $code;
                    break;
                }
            }
        }

        return $codes;
    }

    /**
     * The catalogue as one programme's adviser would want to see it.
     *
     * Given no programmes - an expert who has not said which they take teams
     * from - this is the whole catalogue, because narrowing it to nothing
     * would leave them with a picker of research skills and no technical ones.
     *
     * @param  string[]  $codes
     * @return array<string, string[]>
     */
    public static function groupsForPrograms(array $codes): array
    {
        $codes = array_values(array_intersect($codes, array_keys(self::PROGRAM_SKILLS)));

        if ($codes === []) {
            return self::GROUPS;
        }

        $allowed = [];

        foreach ($codes as $code) {
            foreach (self::PROGRAM_SKILLS[$code] as $skill) {
                $allowed[self::normalise($skill)] = true;
            }
        }

        $out = [];

        foreach (self::GROUPS as $group => $items) {
            // The shared groups are every programme's, so they are not filtered.
            if (in_array($group, self::SHARED_GROUPS, true)) {
                $out[$group] = $items;

                continue;
            }

            $kept = array_values(array_filter(
                $items,
                fn ($item) => isset($allowed[self::normalise($item)])
            ));

            if ($kept !== []) {
                $out[$group] = $kept;
            }
        }

        return $out;
    }

    /**
     * The per-programme skill lists, for a picker that narrows as programmes
     * are ticked.
     *
     * @return array<string, string[]>
     */
    public static function programSkills(): array
    {
        return self::PROGRAM_SKILLS;
    }

    /** The groups every programme shares, so a picker knows not to filter them. */
    public static function sharedGroups(): array
    {
        return self::SHARED_GROUPS;
    }

    /** The programme codes this catalogue knows about. */
    public static function programCodes(): array
    {
        return array_keys(self::PROGRAM_SKILLS);
    }

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
