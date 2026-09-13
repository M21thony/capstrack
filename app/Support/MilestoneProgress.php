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
 * Everything here is worked out at read time. Most item kinds are answered by
 * records that already hold the answer - the uploaded document, the
 * reviewer's verdict, the panel's outcome - so copying their state onto the
 * item would only create something to keep in step and something to go
 * stale. That is exactly what went wrong with the old milestones.status,
 * which was written once as 'todo' and never moved again.
 *
 * An upload belongs to a line when it names it (milestone_item_id). Uploads
 * from before that existed are matched by chapter, as they always were.
 *
 * Late and overdue follow the same rule: both are derived from the dates, so
 * moving a deadline un-flags whatever it should with nothing to go back and
 * correct. Late is judged on when the team first handed a thing in, not on
 * when somebody else finished with it - a chapter sent on time and sent back
 * twice for revision was not late. And a line the team is revising is never
 * overdue: they did hand it in.
 *
 * Resolving is done in bulk - a handful of queries for the whole milestone,
 * not one per item.
 */
class MilestoneProgress
{
    /** Nothing handed in, and the due date has not passed. */
    public const NOT_STARTED = 'not_started';

    /** Handed in or under way, but not closed yet. waiting_on says by whom. */
    public const HANDED_IN = 'handed_in';

    public const DONE = 'done';

    /** Done, but handed in after the due date. Still counts as complete. */
    public const DONE_LATE = 'done_late';

    /** Due date passed with nothing handed in. The only state worth chasing. */
    public const OVERDUE = 'overdue';

    public const COMPLETE_STATES = [self::DONE, self::DONE_LATE];

    /** waiting_on when a reviewer asked for changes: the team owes a revision. */
    public const WAITING_ON_TEAM = 'team';

    /** waiting_on when a signed copy or an approval waits for the CaPSA's check. */
    public const WAITING_ON_CAPSA = 'capsa';

    public static function for(Milestone $milestone): object
    {
        $items = $milestone->items()->orderBy('position')->orderBy('id')->get();
        $teamId = $milestone->team_id;

        $uploads = self::uploadsFor($teamId, $items);
        $reviews = self::reviewsFor($uploads);
        $defenses = self::defensesFor($teamId, $items);

        $resolved = $items->map(fn (MilestoneItem $item) => self::resolve(
            $item,
            $milestone,
            $items,
            $uploads,
            $reviews,
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
        Collection $items,
        Collection $uploads,
        Collection $reviews,
        Collection $defenses,
    ): object {
        $due = $item->due_date ?? $milestone->due_date;

        $mine = self::uploadsForItem($item, $items, $uploads);
        $myReviews = $reviews->whereIn('file_id', $mine->pluck('file_id')->all());

        // The adviser's CLF and the consultant's are separate lines, so an
        // approval only listens to reviews in its own role.
        if ($item->kind === MilestoneItem::APPROVAL) {
            $myReviews = $myReviews->where('role', $item->role);
        }

        $submittedAt = $mine->min('created_at');

        [$closedAt, $started, $waitingOn, $changesRequested] = match ($item->kind) {
            MilestoneItem::DOCUMENT => [$submittedAt, false, null, false],
            MilestoneItem::APPROVAL => self::approvalState($item, $mine, $myReviews),
            MilestoneItem::DEFENSE => self::defenseState($item, $defenses),
            // A form or a CaPSA's own line: closed by the CaPSA checking it. A
            // scan or photo already uploaded means it is waiting on them.
            default => [$item->completed_at, $mine->isNotEmpty(), $mine->isNotEmpty() ? self::WAITING_ON_CAPSA : null, false],
        };

        $common = [
            'item' => $item,
            'due_on' => $due,
            'submitted_at' => $submittedAt ? Carbon::parse($submittedAt) : null,
            // The latest upload, which is the one a CaPSA would open to check.
            'file_id' => $mine->last()?->file_id,
            // How many times a reviewer sent it back. The upload after the Nth
            // request is the team's Nth revision.
            'revision' => $myReviews->where('verdict', CapstoneDocumentReview::NEEDS_REVISION)->count(),
            'changes_requested' => $changesRequested,
        ];

        if ($closedAt) {
            $basis = Carbon::parse($submittedAt ?? $closedAt)->startOfDay();
            $late = $due && $basis->gt(Carbon::parse($due)->startOfDay());

            return (object) ($common + [
                'state' => $late ? self::DONE_LATE : self::DONE,
                'closed_at' => Carbon::parse($closedAt),
                'days_late' => $late ? (int) Carbon::parse($due)->startOfDay()->diffInDays($basis) : 0,
                'waiting_on' => null,
            ]);
        }

        return (object) ($common + [
            'state' => $started
                ? self::HANDED_IN
                : ($due && Carbon::parse($due)->endOfDay()->isPast() ? self::OVERDUE : self::NOT_STARTED),
            'closed_at' => null,
            'days_late' => 0,
            // Who is holding it, which is the question a team actually asks.
            'waiting_on' => $started ? $waitingOn : null,
        ]);
    }

    /** [closedAt, started, waitingOn, changesRequested] */
    private static function approvalState(MilestoneItem $item, Collection $uploads, Collection $reviews): array
    {
        $approvedAt = $reviews->where('verdict', CapstoneDocumentReview::APPROVED)->max('verdict_at');

        if ($approvedAt) {
            if ($item->needs_check && ! $item->completed_at) {
                return [null, true, self::WAITING_ON_CAPSA, false];
            }

            return [$item->needs_check ? $item->completed_at : $approvedAt, true, null, false];
        }

        // Asked for changes, and nothing uploaded since: the team owes a
        // revision. An upload after the request puts it back with the reviewer.
        $latest = $reviews->whereNotNull('verdict_at')->sortBy('verdict_at')->last();
        $changesRequested = $latest
            && $latest->verdict === CapstoneDocumentReview::NEEDS_REVISION
            && ! $uploads->contains(fn ($upload) => $upload->created_at > $latest->verdict_at);

        $started = $reviews->isNotEmpty() || $uploads->contains('milestone_item_id', $item->id);

        return [null, $started, $changesRequested ? self::WAITING_ON_TEAM : $item->role, $changesRequested];
    }

    /** [closedAt, started, waitingOn, changesRequested] - scheduled but no outcome yet. */
    private static function defenseState(MilestoneItem $item, Collection $defenses): array
    {
        $row = $defenses->get($item->defense_type);

        return [$row?->passed_at, (bool) $row, null, false];
    }

    /**
     * The uploads that count for one line.
     *
     * An upload naming the line counts. A chapter draft also counts for that
     * chapter's CLF, because the CLF is the adviser approving that very draft.
     * An upload naming nothing is matched by chapter - that is every upload
     * made before a file could name its line - and never counts for a form.
     */
    private static function uploadsForItem(MilestoneItem $item, Collection $items, Collection $uploads): Collection
    {
        if ($item->kind === MilestoneItem::DEFENSE) {
            return collect();
        }

        return $uploads->filter(function ($upload) use ($item, $items) {
            if ($upload->milestone_item_id !== null) {
                if ((int) $upload->milestone_item_id === $item->id) {
                    return true;
                }

                $draft = $items->firstWhere('id', (int) $upload->milestone_item_id);

                return $item->kind === MilestoneItem::APPROVAL
                    && $draft?->kind === MilestoneItem::DOCUMENT
                    && self::sameChapter($draft->chapter, $item->chapter);
            }

            return in_array($item->kind, [MilestoneItem::DOCUMENT, MilestoneItem::APPROVAL], true)
                && self::sameChapter($upload->chapter, $item->chapter);
        })->values();
    }

    private static function sameChapter(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        $key = self::chapterKey($a);

        return $key !== '' && $key === self::chapterKey($b);
    }

    /**
     * A checklist line says "1"; an upload's detected chapter says "Chapter 1".
     * Comparing them raw meant no real upload ever matched a line.
     */
    private static function chapterKey(string $chapter): string
    {
        return preg_replace('/^chapter\s*/', '', mb_strtolower(trim($chapter)));
    }

    /**
     * Every upload by the team, oldest first. Any member's upload counts - the
     * deliverable belongs to the team, not to whoever pressed the button.
     */
    private static function uploadsFor(?int $teamId, Collection $items): Collection
    {
        if (! $teamId || $items->where('kind', '!=', MilestoneItem::DEFENSE)->isEmpty()) {
            return collect();
        }

        return DB::table('capstone_documents as d')
            ->join('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->where('sy.team_id', $teamId)
            ->select('d.file_id', 'd.milestone_item_id', 'd.chapter', 'd.created_at')
            ->distinct()
            ->orderBy('d.created_at')
            ->get();
    }

    /**
     * Reviews of those uploads. Only a verdict of approved closes anything -
     * see CapstoneDocumentReview, where 'Reviewed' means the reviewer finished
     * looking, not that they agreed.
     */
    private static function reviewsFor(Collection $uploads): Collection
    {
        if ($uploads->isEmpty()) {
            return collect();
        }

        return DB::table('capstone_document_review')
            ->whereIn('file_id', $uploads->pluck('file_id')->all())
            ->select('review_id', 'file_id', 'role', 'verdict', 'verdict_at')
            ->get();
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
