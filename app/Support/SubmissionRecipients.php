<?php

namespace App\Support;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Services\RecipientResolver;
use Illuminate\Support\Collection;

/**
 * Who a file goes to, decided here and not by the team ticking boxes.
 *
 * A first upload starts from the line's rule - Chapters 1-2 go to the
 * adviser (CaPA) and the CaPSA/TSA, Chapter 3 to the adviser and the consultant,
 * the manuscripts to the defense panel as well - and the team may add or
 * drop an optional reviewer. A file for no line has no one ticked: the team
 * picks from the CaPA, consultant and grammarian. Any later version goes back to exactly the
 * experts who got the version before it, with nothing to pick: advisers
 * found revisions landing with somebody who had never seen the first one.
 */
class SubmissionRecipients
{
    public const ROLES = ['adviser', 'consultant', 'grammarian', 'subject_adviser', 'panel'];

    /** What each role is called on the pop-up. */
    public const LABELS = [
        'adviser' => 'CaPA',
        'consultant' => 'Consultant',
        'grammarian' => 'Grammarian',
        'subject_adviser' => 'CaPSA/TSA',
        'panel' => 'Panel',
    ];

    /** Ticked when the pop-up opens. */
    public const ON = 'on';

    /** Shown for the team to add. */
    public const OPTIONAL = 'optional';

    /** The panel whose members read each manuscript. */
    private const PANEL_FOR = [
        'revised' => DefenseOutcome::TITLE_DEFENSE,
        'final' => 'Final Oral Defense',
    ];

    public function __construct(private RecipientResolver $resolver) {}

    /**
     * The starting rule for a line: role => on|optional, plus a short note
     * for a role that is only there for one thing.
     *
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function rulesFor(?Deliverable $item): array
    {
        $formatOnly = [self::OPTIONAL, 'review if asked'];

        // The CaPSA/TSA chose who gets this line: all of them, every time.
        if ($chosen = $item?->sendsTo()) {
            return array_fill_keys(array_values(array_intersect(self::ROLES, $chosen)), [self::ON, null]);
        }

        return match (self::chapterKey($item)) {
            '1', '2' => ['adviser' => [self::ON, null], 'subject_adviser' => [self::ON, null]],
            '3' => ['adviser' => [self::ON, null], 'consultant' => [self::ON, null], 'subject_adviser' => $formatOnly],
            '4' => ['adviser' => [self::ON, null], 'consultant' => [self::ON, 'diagrams'], 'subject_adviser' => $formatOnly],
            '5' => ['adviser' => [self::ON, null], 'subject_adviser' => $formatOnly],
            'revised' => ['panel' => [self::ON, null], 'adviser' => [self::ON, null], 'subject_adviser' => [self::ON, null]],
            'final' => ['panel' => [self::ON, null], 'adviser' => [self::ON, null], 'subject_adviser' => [self::ON, null], 'grammarian' => [self::ON, null]],
            // A file that is not a checklist line: the team picks who reads it,
            // with nobody ticked for them. The CaPSA/TSA is not offered - what they
            // want to check, they add as a line on Progress Tracker.
            default => [
                'adviser' => [self::OPTIONAL, null],
                'consultant' => [self::OPTIONAL, null],
                'grammarian' => [self::OPTIONAL, null],
            ],
        };
    }

    /** "1".."5", "revised", "final", or null for a line that names no chapter. */
    public static function chapterKey(?Deliverable $item): ?string
    {
        $chapter = strtolower(trim((string) $item?->chapter));

        return $chapter === '' ? null : $chapter;
    }

    /**
     * Who this upload goes to, for the pop-up and for the send itself.
     *
     * @return array{
     *   locked: bool,
     *   people: array<int, array{role: string, label: string, name: ?string, note: ?string, expert_ids: array<int, int>, on: bool, available: bool}>
     * }
     */
    public function plan(?Deliverable $item, int $teamId, ?CapstoneDocument $revises = null): array
    {
        // A revision goes back to whoever had the last version: of its line,
        // or - for a file for no line - of the upload it revises, so a revised
        // diagram can't be sent to someone who never had it.
        $previous = match (true) {
            $item !== null => $this->previousReviewers($item, $teamId),
            $revises !== null => $this->reviewersOf($revises),
            default => collect(),
        };

        if ($previous->isNotEmpty()) {
            return [
                'locked' => true,
                'people' => $previous->map(fn (Collection $reviews, string $role) => $this->person(
                    $role,
                    $reviews->pluck('expert_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
                    true,
                ))->values()->all(),
            ];
        }

        $people = [];

        foreach (self::rulesFor($item) as $role => [$rule, $note]) {
            $person = $this->person($role, $this->expertsFor($role, $teamId, $item), $rule === self::ON, $note);
            $people[] = $person;
        }

        return ['locked' => false, 'people' => $people];
    }

    /**
     * The experts the send goes to: role => expert ids. A revision ignores
     * whatever was asked for and goes to the same people as last time; a first
     * upload keeps only the roles its line offers.
     *
     * @param  array<int, string>  $asked
     * @return array<string, array<int, int>>
     */
    public function resolve(?Deliverable $item, int $teamId, array $asked, ?CapstoneDocument $revises = null): array
    {
        $plan = $this->plan($item, $teamId, $revises);
        $chosen = [];

        foreach ($plan['people'] as $person) {
            if (! $person['available']) {
                continue;
            }

            if ($plan['locked'] || $item?->sendsTo() || in_array($person['role'], $asked, true)) {
                $chosen[$person['role']] = $person['expert_ids'];
            }
        }

        return $chosen;
    }

    /**
     * The reviews on the team's latest upload for this line, by role.
     *
     * @return Collection<string, Collection<int, CapstoneDocumentReview>>
     */
    private function previousReviewers(Deliverable $item, int $teamId): Collection
    {
        $last = CapstoneDocument::ofTeam($teamId)
            ->where('deliverable_id', $item->id)
            ->orderByDesc('file_version')
            ->first();

        return $last ? $this->reviewersOf($last) : collect();
    }

    /**
     * The reviews on one upload, by role.
     *
     * @return Collection<string, Collection<int, CapstoneDocumentReview>>
     */
    private function reviewersOf(CapstoneDocument $document): Collection
    {
        return CapstoneDocumentReview::where('file_id', $document->file_id)
            ->whereNotNull('expert_id')
            ->whereIn('role', self::ROLES)
            ->get()
            ->groupBy('role')
            ->sortBy(fn ($reviews, $role) => array_search($role, self::ROLES, true));
    }

    /** @return array<int, int> */
    private function expertsFor(string $role, int $teamId, ?Deliverable $item): array
    {
        if ($role === 'panel') {
            return $this->panelFor($teamId, self::PANEL_FOR[self::chapterKey($item)] ?? null);
        }

        $id = $this->resolver->resolve($role, $teamId);

        return $id ? [(int) $id] : [];
    }

    /**
     * The panelists of the team's latest try at a defense. A line that is not
     * a manuscript names no defense: it goes to the newest panel the team has,
     * whichever defense that is for.
     *
     * @return array<int, int>
     */
    private function panelFor(int $teamId, ?string $defenseType): array
    {
        $schedule = DefenseSchedule::where('team_id', $teamId)
            ->when(
                $defenseType,
                fn ($q) => $q->where('defense_type', $defenseType)->latestAttempt(),
                fn ($q) => $q->whereHas('panels')->orderByDesc('defense_id'),
            )
            ->first();

        return $schedule
            ? $schedule->panels()->pluck('expert_id')->map(fn ($id) => (int) $id)->unique()->values()->all()
            : [];
    }

    /**
     * @param  array<int, int>  $expertIds
     * @return array{role: string, label: string, name: ?string, note: ?string, expert_ids: array<int, int>, on: bool, available: bool}
     */
    private function person(string $role, array $expertIds, bool $on, ?string $note = null): array
    {
        $names = CapstoneExpert::whereIn('expert_id', $expertIds)->get()->pluck('name')->filter()->values();

        $name = match (true) {
            $role === 'panel' && $names->isNotEmpty() => $names->count().' '.($names->count() === 1 ? 'panelist' : 'panelists'),
            $names->isNotEmpty() => $names->first(),
            default => null,
        };

        return [
            'role' => $role,
            'label' => self::LABELS[$role],
            'name' => $name,
            'note' => $note ?? ($role === 'panel' && $expertIds === [] ? 'no panel yet' : ($expertIds === [] ? 'not assigned yet' : null)),
            'expert_ids' => $expertIds,
            'on' => $on && $expertIds !== [],
            'available' => $expertIds !== [],
        ];
    }
}
