<?php

namespace App\Support;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstonePreProposal;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How far along teams are on a checklist.
 *
 * Everything here is worked out at read time from the records that already
 * hold the answer - the upload, the reviewer's verdict, the panel's outcome,
 * the CaPSA's check - so nothing is copied onto the checklist to go stale.
 *
 * What closes a deliverable depends on its kind:
 *
 *   document  the team uploads it
 *   defense   the panel records a pass
 *   form, approval, custom
 *             the CaPSA checks it complete.
 *
 * The Recommended Pre-Proposal Title Form is the one form that needs no
 * paper: the CaPSA recommends a title in CapsTrack, and that closes it.
 *
 * The Signed Final Project Title Form is signed on the title the panel
 * passed, so it waits for a passed Pre-proposal Defense wherever the line
 * sits, and nothing sent or checked for it before that counts. A signed CLF is paper like a
 *             form: there is no e-signing, so the adviser's approval in
 *             Document Review is only a hint that the signed copy can follow.
 *
 * An upload belongs to a line when it names it (deliverable_id). Drafts from
 * before a file could name its line are matched by chapter.
 *
 * Late is judged on when the team first handed a thing in, and a line the team
 * is revising is never overdue: they did hand it in.
 *
 * Resolving is done in bulk - a handful of queries for every team at once.
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

    /** Due date passed with nothing handed in. */
    public const OVERDUE = 'overdue';

    public const COMPLETE_STATES = [self::DONE, self::DONE_LATE];

    /** The team owes something: a revision the reviewer asked for, or a paper the CaPSA sent back. */
    public const WAITING_ON_TEAM = 'team';

    /** A signed paper is uploaded and waits for the CaPSA's check. */
    public const WAITING_ON_CAPSA = 'capsa';

    /** The adviser approved the draft; the team still has to upload the signed CLF. */
    public const WAITING_ON_SIGNED_COPY = 'signed_copy';

    /** The panel asked for a re-defense and the next attempt is not scheduled yet. */
    public const WAITING_ON_REDEFENSE = 'redefense';

    /** The titles are submitted and the CaPSA has not recommended one yet. */
    public const WAITING_ON_TITLE_REVIEW = 'title_review';

    public static function forTeam(Checklist $checklist, int $teamId): object
    {
        return self::forTeams($checklist, [$teamId])[$teamId];
    }

    /** @return array<int, object> keyed by team id */
    public static function forTeams(Checklist $checklist, iterable $teamIds): array
    {
        $teamIds = collect($teamIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($teamIds->isEmpty()) {
            return [];
        }

        $deliverables = $checklist->milestones()->active()
            ->with(['deliverables' => fn ($query) => $query->whereNull('archived_at')])
            ->get()
            ->flatMap(fn (ChecklistMilestone $milestone) => $milestone->deliverables
                ->each(fn (Deliverable $deliverable) => $deliverable->setRelation('milestone', $milestone)))
            ->values();

        $uploads = self::uploadsFor($teamIds, $deliverables);
        $reviews = self::reviewsFor($uploads);
        $defenses = self::defensesFor($teamIds, $deliverables);
        $checks = self::checksFor($teamIds, $deliverables);
        $titles = self::titlesFor($teamIds, $deliverables);

        $out = [];

        foreach ($teamIds as $teamId) {
            $theirs = $uploads->get($teamId, collect());
            $theirReviews = $reviews->whereIn('file_id', $theirs->pluck('file_id')->all());
            $theirDefenses = $defenses->get($teamId, collect());

            $rows = $deliverables->map(fn (Deliverable $deliverable) => self::resolve(
                $deliverable,
                $deliverables,
                $theirs,
                $theirReviews,
                $theirDefenses,
                $checks->get($deliverable->id.'|'.$teamId),
                $titles->get($teamId),
            ));

            $out[$teamId] = self::summarise($rows);
        }

        return $out;
    }

    private static function summarise(Collection $rows): object
    {
        $total = $rows->count();
        $completed = $rows->whereIn('state', self::COMPLETE_STATES)->count();

        return (object) [
            'items' => $rows,
            'milestones' => self::byMilestone($rows),
            'total' => $total,
            'completed' => $completed,
            'handed_in' => $rows->where('state', self::HANDED_IN)->count(),
            'late' => $rows->where('state', self::DONE_LATE)->count(),
            'overdue' => $rows->where('state', self::OVERDUE)->count(),
            // An empty checklist is unconfigured, not finished.
            'percent' => $total === 0 ? 0 : (int) round($completed / $total * 100),
        ];
    }

    /**
     * The same items grouped into their milestones, in checklist order, each
     * with the gate worked out.
     *
     * A gated milestone is locked until every milestone above it is complete.
     * An ungated one opens together with the milestone above it, so chapters
     * can run side by side behind one gate. Nothing stores that: it is read
     * off the states here, so the grid, the team page and the student's
     * upload all read one answer.
     *
     * Each item also carries its own lock (locked, waits_for): the milestone's,
     * or the order inside it - see itemWaitsFor().
     *
     * @return Collection<int, object>
     */
    private static function byMilestone(Collection $rows): Collection
    {
        $unfinished = [];
        $above = null;

        return $rows
            ->groupBy(fn ($row) => $row->milestone->id)
            ->map(function (Collection $items) use (&$unfinished, &$above) {
                $milestone = $items->first()->milestone;
                $total = $items->count();
                $completed = $items->whereIn('state', self::COMPLETE_STATES)->count();
                $waitsFor = $milestone->requires_previous
                    ? ($unfinished ? self::names($unfinished) : null)
                    : $above;
                $locked = $waitsFor !== null;

                $before = collect();

                foreach ($items as $row) {
                    $row->waits_for = in_array($row->state, self::COMPLETE_STATES, true)
                        ? null
                        : ($waitsFor ?? self::itemWaitsFor($row, $before));
                    $row->locked = $row->waits_for !== null;
                    $before->push($row);
                }

                $summary = (object) [
                    'milestone' => $milestone,
                    'items' => $items->values(),
                    'total' => $total,
                    'completed' => $completed,
                    'overdue' => $items->where('state', self::OVERDUE)->count(),
                    'percent' => $total === 0 ? 0 : (int) round($completed / $total * 100),
                    'locked' => $locked,
                    // A milestone has no date of its own; the last thing due
                    // inside it is the date the CaPSA means by "due".
                    'due_on' => $items->pluck('due_on')->filter()->max(),
                    // Named only when it matters: what this one is waiting for.
                    'requires' => $waitsFor,
                ];

                if ($completed < $total) {
                    $unfinished[] = $milestone->name;
                }
                $above = $waitsFor;

                return $summary;
            })
            ->values();
    }

    /**
     * The order inside a milestone, the way the paper process runs:
     *
     *   a signed approval waits for every draft above it (a chapter's CLF
     *     is signed once its draft has passed)
     *   a defense waits for everything above it (the recommending CLFs and
     *     the hearing notice come first)
     *   anything below a defense waits for the defense
     *   the Signed Final Project Title Form waits for a passed Pre-proposal
     *     Defense, wherever it sits
     *
     * Forms with no defense above them are free to come in any order.
     */
    private static function itemWaitsFor(object $row, Collection $before): ?string
    {
        $open = $before->reject(fn ($r) => in_array($r->state, self::COMPLETE_STATES, true));

        $needs = (match ($row->item->kind) {
            Deliverable::DEFENSE => $open,
            Deliverable::APPROVAL => $open->where('item.kind', Deliverable::DOCUMENT),
            default => collect(),
        })->merge($open->where('item.kind', Deliverable::DEFENSE));

        if ($needs->isEmpty() && ($row->awaits_defense ?? null)) {
            return 'a passed '.$row->awaits_defense;
        }

        return $needs->isEmpty() ? null : self::names($needs->pluck('item.title')->unique()->all());
    }

    /** "A", "A and B", "A, B and C". */
    private static function names(array $names): string
    {
        $last = array_pop($names);

        return $names ? implode(', ', $names).' and '.$last : $last;
    }

    /**
     * One milestone's state as a word, a colour and an icon.
     *
     * The team page's strip and the list page's progress grid both say this,
     * so they say it from here rather than each working it out again.
     *
     * @param  object  $summary  a row of byMilestone()
     * @return array{0: string, 1: string, 2: string}
     */
    public static function milestoneState(object $summary): array
    {
        if ($summary->locked) {
            return ['Locked', 'p-gray', 'lock'];
        }

        $states = $summary->items->pluck('state');

        return match (true) {
            $summary->total > 0 && $summary->completed === $summary->total => ['Completed', 'p-green', 'check'],
            $states->contains(self::OVERDUE) => ['Overdue', 'p-red', 'bang'],
            $summary->completed > 0 || $states->contains(self::HANDED_IN) => ['In progress', 'p-gold', 'clock'],
            default => ['Not started', 'p-gray', 'dot'],
        };
    }

    /**
     * The one-line note beside an item: what it is waiting for, or when it
     * was due. Said the same way on the team page and in the student's list.
     */
    public static function itemNote(object $item, bool $locked = false, ?string $requires = null): string
    {
        $due = $item->due_on ? Carbon::parse($item->due_on)->format('M j, Y') : null;

        if ($locked) {
            return $requires ? 'Waits for '.$requires : 'Waits for the milestone before it';
        }

        return match ($item->state) {
            self::HANDED_IN => match (true) {
                $item->waiting_on === self::WAITING_ON_TEAM => 'Waiting on the revised version',
                $item->waiting_on === self::WAITING_ON_SIGNED_COPY => 'Waiting on the signed copy',
                $item->waiting_on === self::WAITING_ON_CAPSA => 'Waiting on your check',
                $item->waiting_on === self::WAITING_ON_REDEFENSE => 'Waiting on the next defense date',
                $item->waiting_on === self::WAITING_ON_TITLE_REVIEW => 'Waiting on your title recommendation',
                $item->item->kind === Deliverable::DEFENSE => 'Scheduled',
                $item->waiting_on !== null => 'Waiting on the '.$item->waiting_on,
                default => 'Waiting on your check',
            },
            self::OVERDUE => $due ? 'Was due '.$due : 'Overdue',
            self::DONE_LATE => $due ? 'Handed in after '.$due : 'Handed in late',
            self::NOT_STARTED => $due ? 'Due '.$due : '',
            default => '',
        };
    }

    /**
     * Where an item is in its own lifecycle, as a list of named steps.
     *
     * Derived from the state and who it waits on, both of which are already
     * worked out above. Nothing new is stored for this.
     *
     * Each step is ['label' => string, 'state' => 'done'|'current'|'missed'|'todo'].
     *
     * @return array<int, array{label: string, state: string}>
     */
    public static function steps(string $kind, string $state, ?string $waitingOn): array
    {
        $labels = match ($kind) {
            Deliverable::DOCUMENT => ['Handed in', 'Reviewed', 'Revised', 'Approved'],
            Deliverable::APPROVAL => ['Handed in', 'Signed', 'Checked'],
            Deliverable::DEFENSE => ['Scheduled', 'Defended', 'Passed'],
            default => ['Handed in', 'Checked'],
        };

        // Done is done, however it got there, and a late hand-in is still a
        // finished one - the lateness is said in words beside the track.
        if (in_array($state, self::COMPLETE_STATES, true)) {
            return array_map(fn ($label) => ['label' => $label, 'state' => 'done'], $labels);
        }

        // Nothing handed in: the first step is either still ahead or missed.
        if ($state !== self::HANDED_IN) {
            $first = $state === self::OVERDUE ? 'missed' : 'todo';

            return array_map(
                fn ($label, $i) => ['label' => $label, 'state' => $i === 0 ? $first : 'todo'],
                $labels,
                array_keys($labels),
            );
        }

        $at = match (true) {
            // The team owes a new version, so the reviewer has already read it.
            $waitingOn === self::WAITING_ON_TEAM => 2,
            $waitingOn === self::WAITING_ON_SIGNED_COPY => 1,
            $waitingOn === self::WAITING_ON_CAPSA => count($labels) - 1,
            $waitingOn === self::WAITING_ON_REDEFENSE => 1,
            // A defense on the calendar has been scheduled and no more.
            $kind === Deliverable::DEFENSE => 1,
            // Handed in and with a reviewer: adviser, consultant, grammarian.
            $waitingOn !== null => 1,
            default => 1,
        };

        return array_map(
            fn ($label, $i) => ['label' => $label, 'state' => match (true) {
                $i < $at => 'done',
                $i === $at => 'current',
                default => 'todo',
            }],
            $labels,
            array_keys($labels),
        );
    }

    private static function resolve(
        Deliverable $deliverable,
        Collection $all,
        Collection $uploads,
        Collection $reviews,
        Collection $defenses,
        ?DeliverableCheck $check,
        ?object $titles = null,
    ): object {
        $due = $deliverable->due_date;
        $byRecommendation = $deliverable->closesOnTitleRecommendation();

        // Signed on the title the panel passed: before that pass, an upload or a check for it does not count.
        $awaitsDefense = $deliverable->template_key === TitleRevision::FINAL_TITLE_FORM
            && ! $defenses->get(DefenseOutcome::PRE_PROPOSAL)?->passed_at;

        if ($awaitsDefense) {
            $uploads = collect();
            $check = null;
        }

        // Nothing is uploaded for a line the recommendation closes; a file sent for it before is not waited on.
        $own = $byRecommendation ? collect() : self::uploadsForDeliverable($deliverable, $uploads);

        // A CLF approves a chapter draft, so the draft's reviews are what say
        // whether the adviser has signed off - and how many rounds it took.
        $reviewed = $deliverable->kind === Deliverable::APPROVAL
            ? self::draftsFor($deliverable, $all, $uploads)->merge($own)
            : $own;

        $myReviews = $reviews->whereIn('file_id', $reviewed->pluck('file_id')->all());

        if ($deliverable->kind === Deliverable::APPROVAL) {
            $myReviews = $myReviews->where('role', $deliverable->role);
        }

        $approvedAt = $myReviews->where('verdict', CapstoneDocumentReview::APPROVED)->max('verdict_at');
        $submittedAt = $byRecommendation ? $titles?->submitted_at : $reviewed->min('created_at');

        [$closedAt, $started, $waitingOn, $changesRequested] = $byRecommendation
            ? self::titleRecommendationState($titles, $check)
            : match ($deliverable->kind) {
                Deliverable::DOCUMENT => self::documentState($own, $myReviews),
                Deliverable::DEFENSE => self::defenseState($deliverable, $defenses),
                default => self::checkedState($deliverable, $own, $reviewed, $myReviews, $approvedAt, $check),
            };

        $common = [
            'item' => $deliverable,
            'milestone' => $deliverable->milestone,
            'due_on' => $due,
            'submitted_at' => $submittedAt ? Carbon::parse($submittedAt) : null,
            // The latest upload for the line, which is what the CaPSA opens to check.
            'file_id' => $own->last()?->file_id,
            'file_path' => $own->last()?->file_path,
            // How many times it was sent back: by a reviewer, or - for a defense - by the panel asking for a re-defense.
            'revision' => $deliverable->kind === Deliverable::DEFENSE
                ? ($defenses->get($deliverable->defense_type)?->redefenses ?? 0)
                : $myReviews->where('verdict', CapstoneDocumentReview::NEEDS_REVISION)->count(),
            'changes_requested' => $changesRequested,
            'check' => $check,
            'note' => $changesRequested && $check?->status === DeliverableCheck::SENT_BACK ? $check->note : null,
            'approved_at' => $approvedAt ? Carbon::parse($approvedAt) : null,
            'awaits_defense' => $awaitsDefense ? DefenseOutcome::PRE_PROPOSAL : null,
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
            'waiting_on' => $started ? $waitingOn : null,
        ]);
    }

    /**
     * [closedAt, started, waitingOn, changesRequested] for a line the CaPSA's
     * check closes: a signed form, a signed CLF, or a task of her own.
     */
    private static function checkedState(
        Deliverable $deliverable,
        Collection $own,
        Collection $reviewed,
        Collection $reviews,
        mixed $approvedAt,
        ?DeliverableCheck $check,
    ): array {
        if ($check?->status === DeliverableCheck::COMPLETE) {
            return [$check->checked_at, true, null, false];
        }

        // Sent back, and nothing uploaded since: the team owes a new copy. An
        // upload after the send-back puts it back in the CaPSA's queue.
        $latestUpload = $own->max('created_at');

        if ($check?->status === DeliverableCheck::SENT_BACK
            && (! $latestUpload || Carbon::parse($latestUpload)->lte($check->checked_at))) {
            return [null, true, self::WAITING_ON_TEAM, true];
        }

        if ($own->isNotEmpty()) {
            return [null, true, self::WAITING_ON_CAPSA, false];
        }

        if ($deliverable->kind !== Deliverable::APPROVAL || $reviews->isEmpty()) {
            return [null, false, null, false];
        }

        if ($approvedAt) {
            return [null, true, self::WAITING_ON_SIGNED_COPY, false];
        }

        // The draft is with the reviewer, or came back asking for changes and
        // nothing has been uploaded since.
        $latest = $reviews->whereNotNull('verdict_at')->sortBy('verdict_at')->last();
        $changesRequested = $latest
            && $latest->verdict === CapstoneDocumentReview::NEEDS_REVISION
            && ! $reviewed->contains(fn ($upload) => $upload->created_at > $latest->verdict_at);

        return [null, true, $changesRequested ? self::WAITING_ON_TEAM : $deliverable->role, $changesRequested];
    }

    /**
     * [closedAt, started, waitingOn, changesRequested] for the Recommended
     * Pre-Proposal Title Form: closed once the CaPSA recommends one of the
     * team's titles. A check the CaPSA already recorded on it still counts.
     */
    private static function titleRecommendationState(?object $titles, ?DeliverableCheck $check): array
    {
        if ($check?->status === DeliverableCheck::COMPLETE) {
            return [$check->checked_at, true, null, false];
        }

        if ($titles?->recommended_at) {
            return [$titles->recommended_at, true, null, false];
        }

        if ($titles?->in_review) {
            return [null, true, self::WAITING_ON_TITLE_REVIEW, false];
        }

        return [null, false, null, false];
    }

    /**
     * [closedAt, started, waitingOn, changesRequested] for a document. It
     * runs the whole cycle - handed in, reviewed, revised, approved - and is
     * done only once the adviser's and the consultant's latest verdicts are
     * both approved. Uploading it is only the first step.
     */
    private static function documentState(Collection $own, Collection $reviews): array
    {
        if ($own->isEmpty()) {
            return [null, false, null, false];
        }

        $decided = $reviews->whereNotNull('verdict_at')->sortBy('verdict_at');

        // Asked for changes and nothing uploaded since: the team owes a new version.
        $latest = $decided->last();

        if ($latest && $latest->verdict === CapstoneDocumentReview::NEEDS_REVISION
            && ! $own->contains(fn ($upload) => $upload->created_at > $latest->verdict_at)) {
            return [null, true, self::WAITING_ON_TEAM, true];
        }

        $approvals = [];

        foreach (['adviser', 'consultant'] as $role) {
            $last = $decided->where('role', $role)->last();

            if ($last?->verdict !== CapstoneDocumentReview::APPROVED) {
                return [null, true, $role, false];
            }

            $approvals[] = $last->verdict_at;
        }

        return [max($approvals), true, null, false];
    }

    /**
     * [closedAt, started, waitingOn, changesRequested] for a defense. Only a
     * pass closes it. A panel asking for a re-defense leaves it open, waiting
     * on the team until the next attempt is on the calendar.
     */
    private static function defenseState(Deliverable $deliverable, Collection $defenses): array
    {
        $row = $defenses->get($deliverable->defense_type);

        if (! $row) {
            return [null, false, null, false];
        }

        if ($row->passed_at) {
            return [$row->passed_at, true, null, false];
        }

        return [null, true, $row->awaiting_redefense ? self::WAITING_ON_REDEFENSE : null, false];
    }

    /**
     * What the team sent for this line. An upload naming nothing predates
     * uploads naming their line, and can only have been a chapter draft.
     */
    private static function uploadsForDeliverable(Deliverable $deliverable, Collection $uploads): Collection
    {
        if ($deliverable->kind === Deliverable::DEFENSE) {
            return collect();
        }

        return $uploads->filter(fn ($upload) => $upload->deliverable_id !== null
            ? (int) $upload->deliverable_id === $deliverable->id
            : $deliverable->kind === Deliverable::DOCUMENT && self::sameChapter($upload->chapter, $deliverable->chapter)
        )->values();
    }

    /** The drafts of the chapter a CLF approves. */
    private static function draftsFor(Deliverable $clf, Collection $all, Collection $uploads): Collection
    {
        $draftIds = $all
            ->filter(fn (Deliverable $d) => $d->kind === Deliverable::DOCUMENT && self::sameChapter($d->chapter, $clf->chapter))
            ->pluck('id')
            ->all();

        return $uploads->filter(fn ($upload) => $upload->deliverable_id !== null
            ? in_array((int) $upload->deliverable_id, $draftIds, true)
            : self::sameChapter($upload->chapter, $clf->chapter)
        )->values();
    }

    private static function sameChapter(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        $key = self::chapterKey($a);

        return $key !== '' && $key === self::chapterKey($b);
    }

    /** A checklist line says "1"; an upload's detected chapter says "Chapter 1". */
    private static function chapterKey(string $chapter): string
    {
        return preg_replace('/^chapter\s*/', '', mb_strtolower(trim($chapter)));
    }

    /**
     * Every upload by each team, oldest first, grouped by team. Any member's
     * upload counts - the deliverable belongs to the team.
     *
     * @return Collection<int, Collection<int, object>>
     */
    private static function uploadsFor(Collection $teamIds, Collection $deliverables): Collection
    {
        if ($deliverables->where('kind', '!=', Deliverable::DEFENSE)->isEmpty()) {
            return collect();
        }

        return DB::table('capstone_documents as d')
            ->join('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->whereIn('sy.team_id', $teamIds->all())
            ->select('sy.team_id', 'd.file_id', 'd.deliverable_id', 'd.chapter', 'd.file_path', 'd.created_at')
            ->distinct()
            ->orderBy('d.created_at')
            ->orderBy('d.file_id')
            ->get()
            ->groupBy(fn ($upload) => (int) $upload->team_id);
    }

    /** Reviews of those uploads. Only a verdict of approved counts as approval. */
    private static function reviewsFor(Collection $uploadsByTeam): Collection
    {
        $fileIds = $uploadsByTeam->flatten(1)->pluck('file_id')->unique()->all();

        if ($fileIds === []) {
            return collect();
        }

        return DB::table('capstone_document_review')
            ->whereIn('file_id', $fileIds)
            ->select('review_id', 'file_id', 'role', 'verdict', 'verdict_at')
            ->get();
    }

    /**
     * Each team's defenses, by stage: when one was passed, how many attempts
     * the panel sent back for a re-defense, and whether the latest attempt is
     * one of those with no next attempt booked. Passed with revision still
     * counts as passed - the team sat it and got through.
     *
     * @return Collection<int, Collection<string, object>> team id -> stage -> summary
     */
    private static function defensesFor(Collection $teamIds, Collection $deliverables): Collection
    {
        if ($deliverables->where('kind', Deliverable::DEFENSE)->isEmpty()
            && $deliverables->where('template_key', TitleRevision::FINAL_TITLE_FORM)->isEmpty()) {
            return collect();
        }

        $passing = [DefenseOutcome::PASSED, DefenseOutcome::PASSED_WITH_REVISION];
        $redefense = [DefenseOutcome::REDEFENSE, DefenseOutcome::REDEFENSE_NEW_TITLE];

        return DB::table('defense_schedule')
            ->whereIn('team_id', $teamIds->all())
            ->select('team_id', 'defense_type', 'attempt', 'outcome', 'outcome_recorded_at')
            ->get()
            ->groupBy(fn ($row) => (int) $row->team_id)
            ->map(fn (Collection $rows) => $rows->groupBy('defense_type')->map(function (Collection $attempts) use ($passing, $redefense) {
                $latest = $attempts->sortBy(fn ($a) => (int) $a->attempt)->last();

                return (object) [
                    'passed_at' => $attempts->whereIn('outcome', $passing)->max('outcome_recorded_at'),
                    'redefenses' => $attempts->whereIn('outcome', $redefense)->count(),
                    'awaiting_redefense' => in_array($latest->outcome, $redefense, true),
                ];
            }));
    }

    /**
     * Where each team's submitted pre-proposal titles stand, for the line the
     * CaPSA's recommendation closes.
     *
     * @return Collection<int, object{submitted_at: mixed, recommended_at: mixed, in_review: bool}> keyed by team id
     */
    private static function titlesFor(Collection $teamIds, Collection $deliverables): Collection
    {
        if (! $deliverables->contains(fn (Deliverable $d) => $d->closesOnTitleRecommendation())) {
            return collect();
        }

        return CapstonePreProposal::whereIn('team_id', $teamIds->all())
            ->whereNotNull('submitted_at')
            ->get(['team_id', 'approved_status', 'rejection_reason', 'submitted_at', 'retired_at', 'updated_at'])
            ->groupBy('team_id')
            ->map(fn (Collection $proposals) => (object) [
                'submitted_at' => $proposals->min('submitted_at'),
                'recommended_at' => $proposals->where('approved_status', true)->min('updated_at'),
                'in_review' => $proposals->contains(fn (CapstonePreProposal $p) => ! $p->retired_at
                    && ! $p->approved_status && $p->rejection_reason === null),
            ]);
    }

    /** @return Collection<string, DeliverableCheck> keyed "deliverable|team" */
    private static function checksFor(Collection $teamIds, Collection $deliverables): Collection
    {
        if ($deliverables->isEmpty()) {
            return collect();
        }

        return DeliverableCheck::whereIn('deliverable_id', $deliverables->pluck('id')->all())
            ->whereIn('team_id', $teamIds->all())
            ->get()
            ->keyBy(fn (DeliverableCheck $check) => $check->deliverable_id.'|'.$check->team_id);
    }
}
