<?php

namespace App\Support;

use App\Models\CapstoneDocumentReview;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How far along a team is on a milestone.
 *
 * Everything here is worked out at read time. Three of the five item kinds
 * are answered by records that already hold the answer - the uploaded
 * document, the reviewer's verdict, the panel's outcome - so copying their
 * state onto the item would only create something to keep in step and
 * something to go stale. That is exactly what went wrong with the old
 * milestones.status, which was written once as 'todo' and never moved again.
 *
 * Late and overdue follow the same rule: both are derived from the dates, so
 * moving a deadline un-flags whatever it should with nothing to go back and
 * correct.
 *
 * Resolving is done in bulk - a handful of queries for the whole milestone,
 * not one per item.
 */
class MilestoneProgress
{
    /** Nothing handed in, and the due date has not passed. */
    public const NOT_STARTED = 'not_started';

    /** Handed in or under way, but not accepted yet. */
    public const HANDED_IN = 'handed_in';

    public const DONE = 'done';

    /** Done, but after the due date. Still counts as complete. */
    public const DONE_LATE = 'done_late';

    /** Due date passed with nothing handed in. The only state worth chasing. */
    public const OVERDUE = 'overdue';

    public const COMPLETE_STATES = [self::DONE, self::DONE_LATE];

    public static function for(Milestone $milestone): object
    {
        $items = $milestone->items()->orderBy('position')->orderBy('id')->get();
        $teamId = $milestone->team_id;

        $documents = self::documentsFor($teamId, $items);
        $approvals = self::approvalsFor($teamId, $items);
        $defenses = self::defensesFor($teamId, $items);

        $resolved = $items->map(fn (MilestoneItem $item) => self::resolve(
            $item,
            $milestone,
            $documents,
            $approvals,
            $defenses,
        ));

        $total = $resolved->count();
        $completed = $resolved->whereIn('state', self::COMPLETE_STATES)->count();

        return (object) [
            'items' => $resolved,
            'total' => $total,
            'completed' => $completed,
            'handed_in' => $resolved->where('state', self::HANDED_IN)->count(),
            'late' => $resolved->where('state', self::DONE_LATE)->count(),
            'overdue' => $resolved->where('state', self::OVERDUE)->count(),
            // A milestone with no items is not finished, it is unconfigured.
            // Callers guard on total > 0 and show nothing at all rather than
            // a percentage - 100% here would make an empty milestone read as
            // a completed one.
            'percent' => $total === 0 ? 0 : (int) round($completed / $total * 100),
        ];
    }

    private static function resolve(
        MilestoneItem $item,
        Milestone $milestone,
        Collection $documents,
        Collection $approvals,
        Collection $defenses,
    ): object {
        $due = $item->due_date ?? $milestone->due_date;

        [$closedAt, $started] = match ($item->kind) {
            MilestoneItem::DOCUMENT => [$documents->get(self::chapterKey($item->chapter)), false],
            MilestoneItem::APPROVAL => self::approvalState($item, $approvals),
            MilestoneItem::DEFENSE => self::defenseState($item, $defenses),
            default => [$item->completed_at, false],
        };

        if ($closedAt) {
            $late = $due && Carbon::parse($closedAt)->startOfDay()->gt(Carbon::parse($due)->startOfDay());

            return (object) [
                'item' => $item,
                'state' => $late ? self::DONE_LATE : self::DONE,
                'closed_at' => Carbon::parse($closedAt),
                'due_on' => $due,
                'days_late' => $late
                    ? (int) Carbon::parse($due)->startOfDay()->diffInDays(Carbon::parse($closedAt)->startOfDay())
                    : 0,
                'waiting_on' => null,
            ];
        }

        return (object) [
            'item' => $item,
            'state' => $started
                ? self::HANDED_IN
                : ($due && Carbon::parse($due)->endOfDay()->isPast() ? self::OVERDUE : self::NOT_STARTED),
            'closed_at' => null,
            'due_on' => $due,
            'days_late' => 0,
            // On an approval this names who is being waited on, which is the
            // question a team actually asks.
            'waiting_on' => $started && $item->kind === MilestoneItem::APPROVAL ? $item->role : null,
        ];
    }

    /** [closedAt, hasStarted] - a review exists but has not approved yet. */
    private static function approvalState(MilestoneItem $item, Collection $approvals): array
    {
        $row = $approvals->get(self::approvalKey($item->role, $item->chapter));

        if (! $row) {
            return [null, false];
        }

        // A verdict of needs_revision is not a close: the team still owes a
        // revision, so the line stays open with the adviser named.
        return [$row->approved_at, true];
    }

    /** [closedAt, hasStarted] - scheduled but no outcome recorded yet. */
    private static function defenseState(MilestoneItem $item, Collection $defenses): array
    {
        $row = $defenses->get($item->defense_type);

        if (! $row) {
            return [null, false];
        }

        return [$row->passed_at, true];
    }

    private static function chapterKey(?string $chapter): string
    {
        return mb_strtolower(trim((string) $chapter));
    }

    private static function approvalKey(?string $role, ?string $chapter): string
    {
        return mb_strtolower(trim((string) $role)).'|'.self::chapterKey($chapter);
    }

    /**
     * The team's uploaded drafts, keyed by chapter. Any member's upload counts
     * - the deliverable belongs to the team, not to whoever pressed the button.
     */
    private static function documentsFor(?int $teamId, Collection $items): Collection
    {
        if (! $teamId || $items->where('kind', MilestoneItem::DOCUMENT)->isEmpty()) {
            return collect();
        }

        return DB::table('capstone_documents as d')
            ->join('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->where('sy.team_id', $teamId)
            ->whereNotNull('d.chapter')
            ->select('d.chapter', DB::raw('MIN(d.created_at) as uploaded_at'))
            ->groupBy('d.chapter')
            ->get()
            ->keyBy(fn ($row) => self::chapterKey($row->chapter))
            ->map(fn ($row) => $row->uploaded_at);
    }

    /**
     * Approvals on this team's documents, keyed by role and chapter. Only a
     * verdict of approved closes anything - see CapstoneDocumentReview, where
     * 'Reviewed' means the reviewer finished looking, not that they agreed.
     */
    private static function approvalsFor(?int $teamId, Collection $items): Collection
    {
        if (! $teamId || $items->where('kind', MilestoneItem::APPROVAL)->isEmpty()) {
            return collect();
        }

        return DB::table('capstone_document_review as r')
            ->join('capstone_documents as d', 'd.file_id', '=', 'r.file_id')
            ->join('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->where('sy.team_id', $teamId)
            ->whereNotNull('r.role')
            ->select(
                'r.role',
                'd.chapter',
                DB::raw('MAX(CASE WHEN r.verdict = \''.CapstoneDocumentReview::APPROVED.'\' THEN r.verdict_at END) as approved_at'),
            )
            ->groupBy('r.role', 'd.chapter')
            ->get()
            ->keyBy(fn ($row) => self::approvalKey($row->role, $row->chapter));
    }

    /**
     * The team's defenses, keyed by stage. Passed with revision still counts
     * as passed - the team sat it and got through, which is what the
     * checklist line means.
     */
    private static function defensesFor(?int $teamId, Collection $items): Collection
    {
        if (! $teamId || $items->where('kind', MilestoneItem::DEFENSE)->isEmpty()) {
            return collect();
        }

        $passing = [DefenseOutcome::PASSED, DefenseOutcome::PASSED_WITH_REVISION];
        $placeholders = implode(',', array_fill(0, count($passing), '?'));

        return DB::table('defense_schedule')
            ->where('team_id', $teamId)
            ->select(
                'defense_type',
                DB::raw("MAX(CASE WHEN outcome IN ({$placeholders}) THEN outcome_recorded_at END) as passed_at"),
            )
            ->addBinding($passing, 'select')
            ->groupBy('defense_type')
            ->get()
            ->keyBy('defense_type');
    }
}
