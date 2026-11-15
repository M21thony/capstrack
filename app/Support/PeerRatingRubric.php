<?php

namespace App\Support;

/**
 * A peer-rating rubric: its criteria, each scored 10/7/5/3/1.
 *
 * The constants are the department's rubric, from FINALS-PARTICIPATION.xlsx,
 * and what a rating uses when the CaPSA/TSA has written none of her own.
 * Her own (TeamRatingRubric) come through fromArray(). A rating keeps a copy
 * of the rubric it was made with (TeamRatingForm::rubric()), so editing one
 * never changes the criteria under ratings already given.
 */
final class PeerRatingRubric
{
    public const DEFAULT_NAME = "Member's Participation Rubric";

    /** How many criteria a rubric may have. */
    public const MAX_CRITERIA = 12;

    /**
     * @param  array<string, list<string>>  $criteria  Criterion => its five descriptors, best first.
     * @param  list<string>  $short  Column headings, one per criterion.
     */
    private function __construct(public readonly string $name, private array $criteria, private array $short, public readonly bool $isDefault) {}

    /** The department's rubric. */
    public static function default(): self
    {
        return new self(self::DEFAULT_NAME, self::CRITERIA, self::SHORT, true);
    }

    /**
     * A rubric as it is stored: ['name' => ..., 'criteria' => [{name, short,
     * levels}]]. Nothing usable in it reads as the department's.
     *
     * @param  array{name?: string, criteria?: array<int, array{name?: string, short?: string, levels?: array<int, string>}>}|null  $stored
     */
    public static function fromArray(?array $stored): self
    {
        $criteria = [];
        $short = [];

        foreach ($stored['criteria'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '' || isset($criteria[$name])) {
                continue;
            }

            $levels = array_map(fn ($d) => trim((string) $d), array_values((array) ($row['levels'] ?? [])));
            $criteria[$name] = array_pad(array_slice($levels, 0, count(self::LEVELS)), count(self::LEVELS), '');
            $short[] = trim((string) ($row['short'] ?? '')) ?: $name;
        }

        return $criteria === [] ? self::default() : new self(trim((string) ($stored['name'] ?? '')) ?: 'Team Rating rubric', $criteria, $short, false);
    }

    /** @return array<string, list<string>> Criterion => its five descriptors, best first. */
    public function criteria(): array
    {
        return $this->criteria;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->criteria);
    }

    /** @return list<string> */
    public function short(): array
    {
        return $this->short;
    }

    public function count(): int
    {
        return count($this->criteria);
    }

    /** A perfect activity: the top score on every criterion. */
    public function max(): int
    {
        return $this->count() * max(array_keys(self::LEVELS));
    }

    /** One valid score for every criterion of this rubric. */
    public function complete(array $scores): bool
    {
        return count($scores) === $this->count() && collect($scores)->every(fn ($s) => self::isScore($s));
    }

    /**
     * The form it is stored in, and the copy a rating keeps.
     *
     * @return array{name: string, criteria: list<array{name: string, short: string, levels: list<string>}>}
     */
    public function toArray(): array
    {
        $rows = [];

        foreach ($this->names() as $i => $name) {
            $rows[] = ['name' => $name, 'short' => $this->short[$i] ?? $name, 'levels' => $this->criteria[$name]];
        }

        return ['name' => $this->name, 'criteria' => $rows];
    }

    /** Score => level name, best first. Each criterion's descriptors follow the same order. */
    public const LEVELS = [10 => 'Excellent', 7 => 'Good', 5 => 'Fair', 3 => 'Needs Improvement', 1 => 'Poor'];

    public const MAX = 70;

    /** Column headings where seven full names will not fit. */
    public const SHORT = ['Discussions', 'Tasks', 'Quality', 'Projects', 'Participation', 'Responsibility', 'Teamwork'];

    /** Criterion => its five descriptors, Excellent to Poor. */
    public const CRITERIA = [
        'Contribution to Group Discussions' => [
            'Actively contributes insightful ideas, suggestions, and feedback during group discussions. Engages with peers respectfully and constructively. Demonstrates excellent communication and collaboration skills.',
            'Regularly contributes valuable ideas, suggestions, and feedback during group discussions. Engages with peers in a positive and respectful manner. Demonstrates good communication and collaboration skills.',
            'Occasionally contributes ideas, suggestions, and feedback during group discussions. Engages with peers but may lack consistency in participation. Demonstrates basic communication and collaboration skills.',
            'Rarely contributes ideas, suggestions, or feedback during group discussions. Engages minimally with peers, often remaining silent or disengaged. Demonstrates poor communication and collaboration skills.',
            'Does not contribute any ideas, suggestions, or feedback during group discussions. Does not engage with peers, displaying a lack of communication and collaboration skills.',
        ],
        'Completion of Assigned Tasks' => [
            'Consistently completes assigned tasks on time and to a high standard. Demonstrates initiative and accountability in fulfilling responsibilities.',
            'Mostly completes assigned tasks on time and meets expectations. Shows some initiative and accountability in fulfilling responsibilities.',
            'Occasionally completes assigned tasks on time but may require reminders or follow-up. Demonstrates limited initiative and accountability in fulfilling responsibilities.',
            'Rarely completes assigned tasks on time and often fails to meet expectations. Shows little initiative and accountability in fulfilling responsibilities.',
            'Does not complete assigned tasks on time or at all. Shows no initiative or accountability in fulfilling responsibilities.',
        ],
        'Quality of Work' => [
            'Produces work of consistently high quality, demonstrating thoroughness, accuracy, and attention to detail. Shows creativity and critical thinking in problem-solving.',
            'Produces work of good quality, with occasional lapses in thoroughness, accuracy, or attention to detail. Shows some creativity and critical thinking in problem-solving.',
            'Produces work of satisfactory quality but may lack consistency in thoroughness, accuracy, or attention to detail. Shows limited creativity and critical thinking in problem-solving.',
            'Produces work of poor quality, with frequent lapses in thoroughness, accuracy, or attention to detail. Shows little creativity and critical thinking in problem-solving.',
            'Produces work of very poor quality, with significant deficiencies in thoroughness, accuracy, or attention to detail. Shows no creativity or critical thinking in problem-solving.',
        ],
        'Contribution to Group Projects' => [
            'Makes significant contributions to group projects, taking on leadership roles when necessary and facilitating collaboration among team members. Demonstrates initiative and resourcefulness in advancing project goals.',
            'Makes substantial contributions to group projects, actively participating in discussions and tasks assigned, and supporting team members as needed. Demonstrates good teamwork skills.',
            'Makes some contributions to group projects, but may require guidance or direction from others. Demonstrates limited teamwork skills.',
            'Makes minimal contributions to group projects, often relying on others to complete tasks. Demonstrates poor teamwork skills.',
            'Makes no contributions to group projects, failing to fulfill assigned roles or responsibilities. Demonstrates no teamwork skills.',
        ],
        'Overall Participation' => [
            'Consistently demonstrates exceptional participation, contributing positively to group dynamics and fostering a collaborative and productive environment.',
            'Generally demonstrates good participation, contributing positively to group dynamics and maintaining a cooperative environment.',
            'Demonstrates satisfactory participation but may occasionally disrupt group dynamics or hinder progress.',
            'Demonstrates limited participation, often hindering group dynamics and impeding progress.',
            'Demonstrates poor participation, consistently hindering group dynamics and impeding progress.',
        ],
        'Responsibility' => [
            'Consistently takes initiative and fulfills assigned tasks promptly and thoroughly. Demonstrates reliability and accountability in meeting deadlines and commitments.',
            'Mostly takes initiative and completes assigned tasks in a timely manner. Demonstrates reliability in meeting deadlines and commitments, with occasional lapses.',
            'Completes assigned tasks, but may require reminders or follow-up to ensure timely completion. Demonstrates some reliability in meeting deadlines and commitments.',
            'Rarely takes initiative and frequently fails to complete assigned tasks on time. Demonstrates poor reliability in meeting deadlines and commitments.',
            'Fails to take initiative and consistently fails to complete assigned tasks on time or at all. Demonstrates no reliability in meeting deadlines and commitments.',
        ],
        'Teamwork' => [
            'Collaborates effectively with team members, demonstrating flexibility and willingness to compromise when necessary. Communicates openly and respectfully, fostering a positive team environment.',
            'Generally collaborates well with team members, but may occasionally encounter challenges in communication or cooperation.',
            'Shows some willingness to collaborate with team members, but may struggle with communication or cooperation at times.',
            'Demonstrates limited willingness to collaborate with team members and frequently encounters challenges in communication or cooperation.',
            'Does not collaborate with team members and consistently encounters challenges in communication or cooperation.',
        ],
    ];

    public static function isScore(mixed $score): bool
    {
        return is_int($score) && array_key_exists($score, self::LEVELS);
    }
}
