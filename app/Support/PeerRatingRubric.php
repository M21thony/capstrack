<?php

namespace App\Support;

/**
 * The one peer-rating rubric, from FINALS-PARTICIPATION.xlsx.
 *
 * Hardcoded like the Title Defense rubric: there is one official rubric, and
 * a builder would let someone change the criteria under ratings already
 * given. Each criterion is scored 10/7/5/3/1, so 70 is a perfect activity.
 */
final class PeerRatingRubric
{
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

    /** One valid score for every criterion. */
    public static function isComplete(array $scores): bool
    {
        return count($scores) === count(self::CRITERIA)
            && collect($scores)->every(fn ($s) => self::isScore($s));
    }
}
