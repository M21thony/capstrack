<?php

namespace App\Support;

use App\Models\ArchivedDocument;
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
 * the CaPSA/TSA's check - so nothing is copied onto the checklist to go stale.
 *
 * What closes a deliverable depends on its kind:
 *
 *   document  the team uploads it
 *   defense   the panel records a pass
 *   form, approval, custom
 *             the CaPSA/TSA checks it complete.
 *
 * The Recommended Pre-Proposal Title Form is the one form that needs no
 * paper: the CaPSA/TSA recommends a title in CapsTrack, and that closes it.
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

    /** The team owes something: a revision the reviewer asked for, or a paper the CaPSA/TSA sent back. */
    public const WAITING_ON_TEAM = 'team';

    /** A signed paper is uploaded and waits for the CaPSA/TSA's check. */
    public const WAITING_ON_CAPSA = 'capsa';

    /** A Bluebook line: the dean's office checks it, so it is never in the CaPSA/TSA's queue. */
    public const WAITING_ON_DEAN = 'dean';

    /** The adviser approved the draft; the team still has to upload the signed CLF. */
    public const WAITING_ON_SIGNED_COPY = 'signed_copy';

    /** The panel asked for a re-defense and the next attempt is not scheduled yet. */
    public const WAITING_ON_REDEFENSE = 'redefense';

    /** The titles are submitted and the CaPSA/TSA has not recommended one yet. */
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
        $same = self::sameLines($deliverables, $uploads->flatten(1)->pluck('deliverable_id'));
        $uploads->flatten(1)->each(fn ($upload) => $upload->deliverable_id = $same[$upload->deliverable_id] ?? $upload->deliverable_id);
        $reviews = self::reviewsFor($uploads);
        $defenses = self::defensesFor($teamIds, $deliverables);
        $checks = self::checksFor($teamIds, $deliverables);
        $titles = self::titlesFor($teamIds, $deliverables);
        $archives = self::archivesFor($teamIds, $deliverables);
        $owed = ExpertEvaluations::owedForTeams($teamIds);

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
                $archives->get($teamId),
            ));

            $out[$teamId] = self::summarise($rows, $owed[$teamId] ?? collect());
        }

        return $out;
    }

    /** @param  Collection<int, object>  $owed  the team's owed evaluations, see ExpertEvaluations::owedForTeams() */
    private static function summarise(Collection $rows, Collection $owed): object
    {
        $total = $rows->count();
        $completed = $rows->whereIn('state', self::COMPLETE_STATES)->count();

        return (object) [
            'items' => $rows,
            'milestones' => self::byMilestone($rows, $owed),
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
     * The CaPSA/TSA opens each milestone for the whole section; one she has not
     * opened is locked for every team. Passing the defense above it is not
     * asked for, so a chapter opens on time even for a team still finishing
     * the one before. It is read off the states here,
     * so the grid, the team page and the student's upload all read one answer.
     *
     * A passed defense still holds the next gate shut while the leader owes
     * the expert evaluation for it (ExpertEvaluations): evaluating the
     * experts is the last step of passing a defense.
     *
     * Each item also carries its own lock (locked, waits_for): the milestone's,
     * or the order inside it - see itemWaitsFor().
     *
     * @return Collection<int, object>
     */
    private static function byMilestone(Collection $rows, Collection $owed): Collection
    {
        // The expert evaluation the last passed defense above still owes, if any.
        $gate = null;

        return $rows
            ->groupBy(fn ($row) => $row->milestone->id)
            ->map(function (Collection $items) use (&$gate, $owed) {
                $milestone = $items->first()->milestone;
                $total = $items->count();
                $completed = $items->whereIn('state', self::COMPLETE_STATES)->count();
                $notOpen = ! $milestone->isOpened();
                $waitsFor = $notOpen ? 'your CaPSA/TSA to open it' : $gate;
                $locked = $waitsFor !== null;
                $dueOn = $milestone->due_on ?? $items->pluck('due_on')->filter()->max();
                // The defenses this milestone holds; once it is finished,
                // whatever is owed for them keeps it from counting as done.
                $stages = $items->filter(fn ($row) => $row->item->kind === Deliverable::DEFENSE && $row->item->defense_type)
                    ->map(fn ($row) => mb_strtolower($row->item->defense_type))->unique()->all();
                $evaluationsOwed = $total > 0 && $completed === $total && $stages !== []
                    ? $owed->filter(fn ($e) => in_array(mb_strtolower($e->stage), $stages, true))->values()
                    : collect();

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
                    'not_open' => $notOpen,
                    // The milestone's own date when the CaPSA/TSA set one;
                    // otherwise the last thing due inside it.
                    'due_on' => $dueOn,
                    // Its date has passed and the team has not finished it.
                    // A closed milestone is over, so it is not called past due.
                    'past_due' => $dueOn !== null
                        && ! $milestone->isClosed()
                        && $completed < $total
                        && Carbon::parse($dueOn)->endOfDay()->isPast(),
                    'closed' => $milestone->isClosed(),
                    // Named only when it matters: what this one is waiting for.
                    'requires' => $waitsFor,
                    // Its defense is passed, but the leader has not evaluated
                    // these experts for it yet - so the next gate stays shut.
                    'evaluations_owed' => $evaluationsOwed,
                ];

                foreach ($items->filter(fn ($row) => $row->item->kind === Deliverable::DEFENSE && $row->item->defense_type) as $row) {
                    $stage = $row->item->defense_type;
                    $evaluations = $owed->filter(fn ($e) => mb_strtolower($e->stage) === mb_strtolower($stage));
                    $gate = $evaluations->isNotEmpty() ? 'the expert evaluation for '.$stage : null;
                }

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
        if ($summary->not_open ?? false) {
            return ['Not open yet', 'p-gray', 'lock'];
        }

        if ($summary->locked) {
            return ['Locked', 'p-gray', 'lock'];
        }

        $states = $summary->items->pluck('state');

        return match (true) {
            $summary->total > 0 && $summary->completed === $summary->total => ['Completed', 'p-green', 'check'],
            $summary->closed ?? false => ['Closed', 'p-gray', 'check'],
            $summary->past_due ?? false => ['Past due', 'p-red', 'bang'],
            $states->contains(self::OVERDUE) => ['Overdue', 'p-red', 'bang'],
            $summary->completed > 0 || $states->contains(self::HANDED_IN) => ['In progress', 'p-gold', 'clock'],
            default => ['Not started', 'p-gray', 'dot'],
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
            Deliverable::DOCUMENT => ['Handed in', 'Reviewed', 'Revised', 'Approved', 'Checked'],
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
        ?ArchivedDocument $archive = null,
    ): object {
        // A line with no date of its own falls due with its milestone.
        $due = $deliverable->due_date ?? $deliverable->milestone?->due_on;
        $byRecommendation = $deliverable->closesOnTitleRecommendation();
        $byArchive = $deliverable->closesOnArchiveEntry();

        // Signed on the title the panel passed: before that pass, an upload or a check for it does not count.
        $awaitsDefense = $deliverable->template_key === TitleRevision::FINAL_TITLE_FORM
            && $all->contains(fn (Deliverable $d) => $d->kind === Deliverable::DEFENSE && $d->defense_type === DefenseOutcome::PRE_PROPOSAL)
            && ! $defenses->get(DefenseOutcome::PRE_PROPOSAL)?->passed_at;

        if ($awaitsDefense) {
            $uploads = collect();
            $check = null;
        }

        // Nothing is uploaded for a line the recommendation closes; a file sent for it before is not waited on.
        $own = $byRecommendation || $byArchive || $deliverable->closesOnBluebookClearance()
            ? collect()
            : self::uploadsForDeliverable($deliverable, $uploads);

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
        $submittedAt = match (true) {
            $byRecommendation => $titles?->submitted_at,
            $byArchive => $archive?->submitted_at,
            default => $reviewed->min('created_at'),
        };

        [$closedAt, $started, $waitingOn, $changesRequested] = match (true) {
            $byRecommendation => self::titleRecommendationState($titles, $check),
            $byArchive => self::archiveEntryState($archive, $check),
            (bool) $deliverable->sendsTo() => self::recipientsState($deliverable->sendsTo(), $own, $myReviews, $check),
            default => match ($deliverable->kind) {
                Deliverable::DOCUMENT => self::documentState($own, $myReviews, $check),
                Deliverable::DEFENSE => self::defenseState($deliverable, $defenses),
                default => self::checkedState($deliverable, $own, $reviewed, $myReviews, $approvedAt, $check),
            },
        };

        if ($deliverable->kind === Deliverable::BLUEBOOK && $waitingOn === self::WAITING_ON_CAPSA) {
            $waitingOn = self::WAITING_ON_DEAN;
        }

        $common = [
            'item' => $deliverable,
            'milestone' => $deliverable->milestone,
            'due_on' => $due,
            'submitted_at' => $submittedAt ? Carbon::parse($submittedAt) : null,
            // The latest upload for the line, which is what the CaPSA/TSA opens to check.
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
     * [closedAt, started, waitingOn, changesRequested] for a line the CaPSA/TSA's
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
        // upload after the send-back puts it back in the CaPSA/TSA's queue.
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
     * Pre-Proposal Title Form: closed once the CaPSA/TSA recommends one of the
     * team's titles. A check the CaPSA/TSA already recorded on it still counts.
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
     * [closedAt, started, waitingOn, changesRequested] for the Archive Entry:
     * closed once the CaPSA/TSA publishes it. A check the CaPSA/TSA recorded on it
     * still counts.
     */
    private static function archiveEntryState(?ArchivedDocument $archive, ?DeliverableCheck $check): array
    {
        if ($check?->status === DeliverableCheck::COMPLETE) {
            return [$check->checked_at, true, null, false];
        }

        if ($archive?->submission_status === ArchivedDocument::PUBLISHED) {
            // ponytail: no published_at column, so it counts as closed when sent; add one if the date matters.
            return [$archive->submitted_at, true, null, false];
        }

        if ($archive) {
            return [null, true, self::WAITING_ON_CAPSA, false];
        }

        return [null, false, null, false];
    }

    /**
     * [closedAt, started, waitingOn, changesRequested] for a document. It
     * runs the whole cycle - handed in, reviewed, revised, approved - and is
     * done only once the adviser's latest verdict is approved, and the
     * [closedAt, started, waitingOn, changesRequested] for a line whose
     * uploads go to the people the CaPSA/TSA chose: done once every one of them
     * approved the latest upload. One asking for changes sends it back. The
     * CaPSA/TSA's own check still closes it, as on any line.
     *
     * @param  array<int, string>  $roles
     */
    private static function recipientsState(array $roles, Collection $own, Collection $reviews, ?DeliverableCheck $check): array
    {
        if ($own->isEmpty()) {
            return [null, false, null, false];
        }

        if ($check?->status === DeliverableCheck::COMPLETE) {
            return [$check->checked_at, true, null, false];
        }

        if ($check?->status === DeliverableCheck::SENT_BACK
            && Carbon::parse($own->max('created_at'))->lte($check->checked_at)) {
            return [null, true, self::WAITING_ON_TEAM, true];
        }

        $latest = $reviews->whereNotNull('verdict_at')->sortBy('verdict_at')->last();

        if ($latest && $latest->verdict === CapstoneDocumentReview::NEEDS_REVISION
            && ! $own->contains(fn ($upload) => $upload->created_at > $latest->verdict_at)) {
            return [null, true, self::WAITING_ON_TEAM, true];
        }

        // Everyone the latest upload went to, panelists one by one.
        $onLatest = $reviews->where('file_id', $own->last()->file_id);

        foreach ($roles as $role) {
            if ($onLatest->where('role', $role)->contains(fn ($r) => $r->verdict !== CapstoneDocumentReview::APPROVED)) {
                return [null, true, $role, false];
            }
        }

        // Someone she chose has nobody to send it to: only her check can close it.
        if ($onLatest->isEmpty() || collect($roles)->contains(fn ($role) => $onLatest->where('role', $role)->isEmpty())) {
            return [null, true, self::WAITING_ON_CAPSA, false];
        }

        return [$onLatest->max('verdict_at'), true, null, false];
    }

    /**
     * [closedAt, started, waitingOn, changesRequested] for a document. It
     * runs the whole cycle - handed in, reviewed, revised, approved - and is
     * done only once the adviser's latest verdict is approved, and the
     * consultant's too when it was sent to them. Uploading it is only the
     * first step.
     */
    private static function documentState(Collection $own, Collection $reviews, ?DeliverableCheck $check): array
    {
        if ($own->isEmpty()) {
            return [null, false, null, false];
        }

        if ($check?->status === DeliverableCheck::COMPLETE) {
            return [$check->checked_at, true, null, false];
        }

        // Sent back by the CaPSA/TSA, and nothing uploaded since: the team owes a new version.
        if ($check?->status === DeliverableCheck::SENT_BACK
            && Carbon::parse($own->max('created_at'))->lte($check->checked_at)) {
            return [null, true, self::WAITING_ON_TEAM, true];
        }

        $decided = $reviews->whereNotNull('verdict_at')->sortBy('verdict_at');

        // Asked for changes and nothing uploaded since: the team owes a new version.
        $latest = $decided->last();

        if ($latest && $latest->verdict === CapstoneDocumentReview::NEEDS_REVISION
            && ! $own->contains(fn ($upload) => $upload->created_at > $latest->verdict_at)) {
            return [null, true, self::WAITING_ON_TEAM, true];
        }

        // The adviser always, and the consultant when the draft went to them.
        // Chapters 1, 2 and 5 and both manuscripts go to the adviser and
        // the CaPSA/TSA, not the consultant (SubmissionRecipients::rulesFor), so
        // waiting on a consultant's verdict for them kept a team on Chapter 1
        // for good.
        $sentTo = $reviews->pluck('role')->all();
        $deciders = array_values(array_filter(['adviser', 'consultant'], fn ($role) => $role === 'adviser' || in_array($role, $sentTo, true)));

        foreach ($deciders as $role) {
            $last = $decided->where('role', $role)->last();

            if ($last?->verdict !== CapstoneDocumentReview::APPROVED) {
                return [null, true, $role, false];
            }

        }

        // Approved by the reviewers; the CaPSA/TSA's check is what closes it.
        return [null, true, self::WAITING_ON_CAPSA, false];
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
     * upload counts - the deliverable belongs to the team - but only one sent
     * for this team: a member's files from a team they left stay there. A
     * file from before uploads recorded their team counts when it went up
     * after the team was made.
     *
     * @return Collection<int, Collection<int, object>>
     */
    private static function uploadsFor(Collection $teamIds, Collection $deliverables): Collection
    {
        if ($deliverables->where('kind', '!=', Deliverable::DEFENSE)->isEmpty()) {
            return collect();
        }

        $ids = $teamIds->all();

        return DB::table('capstone_documents as d')
            ->leftJoin('student_s_y as sy', fn ($join) => $join->on('sy.student_id', '=', 'd.student_id')->whereNull('d.team_id'))
            ->leftJoin('team as t', 't.team_id', '=', 'sy.team_id')
            ->where(fn ($q) => $q->whereIn('d.team_id', $ids)
                ->orWhere(fn ($q) => $q->whereNull('d.team_id')->whereIn('sy.team_id', $ids)->whereColumn('d.created_at', '>=', 't.created_at')))
            ->selectRaw('COALESCE(d.team_id, sy.team_id) as team_id')
            ->addSelect('d.file_id', 'd.deliverable_id', 'd.chapter', 'd.file_path', 'd.created_at')
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
     * CaPSA/TSA's recommendation closes.
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

    /**
     * Each team's own archive entry, for the line publishing it closes.
     *
     * @return Collection<int, ArchivedDocument> keyed by team id
     */
    private static function archivesFor(Collection $teamIds, Collection $deliverables): Collection
    {
        if (! $deliverables->contains(fn (Deliverable $d) => $d->closesOnArchiveEntry())) {
            return collect();
        }

        return ArchivedDocument::whereIn('team_id', $teamIds->all())
            ->whereNotNull('submitted_by')
            ->get(['team_id', 'submitted_at', 'submission_status'])
            ->keyBy('team_id');
    }

    /** @return Collection<string, DeliverableCheck> keyed "deliverable|team" */
    private static function checksFor(Collection $teamIds, Collection $deliverables): Collection
    {
        if ($deliverables->isEmpty()) {
            return collect();
        }

        $checks = DeliverableCheck::whereIn('team_id', $teamIds->all())->get();
        $same = self::sameLines($deliverables, $checks->pluck('deliverable_id'));

        // A check made on this list's own line is keyed last, so it wins.
        return $checks->sortBy(fn (DeliverableCheck $check) => isset($same[$check->deliverable_id]) ? 0 : 1)
            ->keyBy(fn (DeliverableCheck $check) => ($same[$check->deliverable_id] ?? $check->deliverable_id).'|'.$check->team_id);
    }

    /**
     * Work a team did under another list - last year's, or another CaPSA/TSA's -
     * is not done again: a line there stands for the line here of the same name.
     *
     * ponytail: matched by title, so a renamed line does not carry over; match
     * on a shared key if lists start to drift apart in wording.
     *
     * @return array<int, int> their line id => this list's line id
     */
    private static function sameLines(Collection $deliverables, Collection $ids): array
    {
        $foreign = $ids->filter()->map(fn ($id) => (int) $id)->unique()->diff($deliverables->pluck('id'));

        if ($foreign->isEmpty()) {
            return [];
        }

        $key = fn (Deliverable $d) => mb_strtolower(trim((string) $d->title));
        $here = $deliverables->keyBy($key);

        return Deliverable::withTrashed()->whereIn('id', $foreign->all())->get()
            ->filter(fn (Deliverable $d) => $here->has($key($d)))
            ->mapWithKeys(fn (Deliverable $d) => [$d->id => $here[$key($d)]->id])
            ->all();
    }
}
