<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * What a team can bring to a consultation, by who they are seeing: their
 * adviser looks at the paper or the system, their consultant at the
 * diagrams or the system, and their grammarian always checks the whole
 * manuscript, so a grammarian booking needs no choice at all.
 */
class ConsultationTopics
{
    public const PAPER = 'paper';

    public const SYSTEM = 'system';

    public const DIAGRAMS = 'diagrams';

    public const MANUSCRIPT = 'manuscript';

    public const LABELS = [
        self::PAPER => 'Paper',
        self::SYSTEM => 'System',
        self::DIAGRAMS => 'Diagrams',
        self::MANUSCRIPT => 'Manuscript',
    ];

    /** The order they are offered and read in, whoever offers them. */
    private const ORDER = [self::PAPER, self::DIAGRAMS, self::SYSTEM, self::MANUSCRIPT];

    private const BY_ROLE = [
        'adviser' => [self::PAPER, self::SYSTEM],
        'consultant' => [self::DIAGRAMS, self::SYSTEM],
        'grammarian' => [self::MANUSCRIPT],
    ];

    /**
     * The topics a team picks from for an expert holding these roles on it.
     * Someone who is both adviser and consultant offers all three.
     *
     * @param  list<string>  $roles
     * @return list<string>
     */
    public static function choicesFor(array $roles): array
    {
        $choices = [];

        foreach (self::ORDER as $topic) {
            foreach ($roles as $role) {
                if (in_array($topic, self::BY_ROLE[strtolower($role)] ?? [], true)) {
                    $choices[] = $topic;
                    break;
                }
            }
        }

        return $choices;
    }

    /**
     * The one topic there is nothing to choose about: a grammarian only.
     *
     * @param  list<string>  $roles
     */
    public static function fixedFor(array $roles): ?string
    {
        $choices = self::choicesFor($roles);

        return $choices === [self::MANUSCRIPT] ? self::MANUSCRIPT : null;
    }

    /**
     * The roles an expert was engaged in by this team.
     *
     * @return list<string>
     */
    public static function rolesOf(int $expertId, ?int $teamId): array
    {
        if (! $teamId) {
            return [];
        }

        return DB::table('capstone_expert_application as a')
            ->join('expert_roles as er', 'er.expert_role_id', '=', 'a.expert_role_id')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('a.team_id', $teamId)
            ->where('a.expert_id', $expertId)
            ->where('a.status', 'approved')
            ->pluck('r.role_name')
            ->map(fn ($role) => strtolower((string) $role))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * "Paper, System" - the topics as people read them, in the order offered.
     *
     * @param  list<string>|null  $topics
     */
    public static function label(?array $topics): string
    {
        return collect(self::ORDER)
            ->filter(fn ($topic) => in_array($topic, $topics ?? [], true))
            ->map(fn ($topic) => self::LABELS[$topic])
            ->implode(', ');
    }
}
