<?php

namespace App\Support;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\ExpertRemovalRequest;
use App\Models\MemberLeaveRequest;
use App\Models\PanelGrade;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Models\TitleChangeRequest;
use App\Services\CapaGo;
use App\Services\RecipientResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything waiting on one person, in one list.
 *
 * Titles to review, teams to verify, papers to check, leave and expert
 * requests, invitations, chapters to review and score sheets due used to sit
 * on as many screens. Each item names its team, says why it is here and
 * links to - or carries - the one action that clears it.
 *
 * An item is an object with:
 *   kind     go|title|verify|paper|signed|leave|expert|invite|adviser_title|title_change|review|grade
 *   group    the heading it is listed under
 *   hat      capsa, or the seat the person holds on the team as an expert
 *   teamId, teamName, section, teamUrl
 *   what, why, at (when it arrived), url (the place it is done)
 *   label    the main button's words
 *   decide   null, or how the Approve/Decline buttons send the decision
 *   read     null, or [what, id]: the application or title the Read button
 *            opens in the PDF window
 */
class TeamTodo
{
    public const GROUPS = [
        'go' => 'Defenses waiting for your go',
        'title' => 'Titles',
        'adviser_title' => 'Titles',
        'title_change' => 'Titles',
        'verify' => 'Teams to verify',
        'paper' => 'Papers',
        'signed' => 'Papers',
        'review' => 'Papers',
        'leave' => 'Requests from teams',
        'expert' => 'Requests from teams',
        'invite' => 'Team applications',
        'grade' => 'Grading',
    ];

    /** Days an item can wait before it is called out as old. */
    public const LATE_AFTER_DAYS = 3;

    /**
     * What on one team waits on this person.
     *
     * @param  Collection<int, object>  $milestones  The team's milestone resolution.
     * @return Collection<int, object>
     */
    public function forRow(object $row, Collection $milestones, string $portal): Collection
    {
        $team = $row->team;
        $items = collect();
        $teamUrl = route($portal === 'capsa' ? 'capsa.team_management.show' : 'instructor.teams.show', $team->team_id);
        $item = fn (array $fields) => $this->item($fields + [
            'teamId' => (int) $team->team_id,
            'teamName' => $team->team_name,
            'section' => $row->section,
            'teamUrl' => $teamUrl,
        ]);

        if ($row->inSections) {
            $pending = $row->proposals->filter(fn ($p) => ! $p->approved_status && ! $p->rejection_reason);

            if ($pending->isNotEmpty()) {
                $items->push($item([
                    'kind' => 'title', 'hat' => 'capsa',
                    'what' => 'Review '.$pending->count().' '.Str::plural('title', $pending->count()),
                    'why' => 'Turned in with their duplication reports. The team cannot ask an adviser until you recommend one.',
                    'at' => $pending->max('submitted_at'),
                    'label' => 'Review titles',
                    'url' => route('capsa.team_management.title', [$team->team_id, $pending->sortBy('slot')->first()->id]),
                ]));
            }

            if ($row->canVerify) {
                $adviser = $row->engagedRoles->get('adviser');
                $items->push($item([
                    'kind' => 'verify', 'hat' => 'capsa',
                    'what' => 'Verify the team',
                    'why' => $row->members->count().' members · '.($adviser ? 'adviser '.$adviser->name : 'no adviser yet').' · final title chosen',
                    'at' => $team->updated_at,
                    'label' => 'Verify',
                    'url' => route('capsa.team_management.pre_proposals', $team->team_id),
                ]));
            }

            foreach ($row->formatChecks->where('review_status', '!=', 'Reviewed') as $paper) {
                $items->push($item([
                    'kind' => 'paper', 'hat' => 'capsa',
                    'what' => self::paperTitle($paper),
                    'why' => self::manuscriptRange($paper).$paper->file_name,
                    'at' => $paper->submitted_at,
                    'label' => 'Review',
                    'url' => route('instructor.docreview.annotate', $paper->review_id),
                ]));
            }

            foreach ($milestones as $milestone) {
                foreach ($milestone->items as $entry) {
                    if ($entry->waiting_on === MilestoneProgress::WAITING_ON_CAPSA
                        && in_array($entry->item->kind, Deliverable::CHECKED_BY_CAPSA, true)) {
                        $items->push($item([
                            'kind' => 'signed', 'hat' => 'capsa',
                            'what' => 'Check a signed paper',
                            'why' => $entry->item->title.' · '.$milestone->milestone->name,
                            'at' => $entry->submitted_at ?? null,
                            'label' => 'Check',
                            'url' => route('capsa.team_management', [
                                'tab' => 'milestones', 'section' => $row->section,
                                'team' => $team->team_id, 'check' => $entry->item->id,
                                'return' => route('capsa.team_management.show', $team->team_id, false),
                            ]),
                        ]));
                    }
                }
            }
        }

        if ($row->myRole) {
            foreach ($row->reviews->where('review_status', '!=', 'Reviewed') as $paper) {
                $items->push($item([
                    'kind' => 'review', 'hat' => $row->myRole,
                    'what' => self::paperTitle($paper),
                    'why' => self::manuscriptRange($paper).$paper->file_name.' · version '.$paper->file_version,
                    'at' => $paper->submitted_at,
                    'label' => 'Review',
                    'url' => route('instructor.docreview.annotate', $paper->review_id),
                ]));
            }
        }

        return $items;
    }

    /**
     * What a paper is, so the row says it before the button: one chapter, a
     * manuscript (Chapters 1-3 or 1-5 together), or the diagrams.
     */
    private static function paperTitle(object $paper): string
    {
        if (preg_match('/\.(drawio|vsdx)$/i', (string) $paper->file_name)) {
            return 'Review Diagrams';
        }

        if (self::manuscriptRange($paper) !== '') {
            return 'Review Manuscript';
        }

        return $paper->chapter ? 'Review '.$paper->chapter : 'Review paper';
    }

    /** "Chapters 1–3 · " for the revised proposal, "Chapters 1–5 · " for the final one, else "". */
    private static function manuscriptRange(object $paper): string
    {
        $last = match (true) {
            ($paper->line_chapter ?? null) === 'revised' => 3,
            ($paper->line_chapter ?? null) === 'final' => 5,
            (bool) preg_match('/chapters?[\s_-]*1\s*(?:-|–|to)\s*([35])\b/i', (string) $paper->file_name, $m) => (int) $m[1],
            default => null,
        };

        return $last ? 'Chapters 1–'.$last.' · ' : '';
    }

    /**
     * Everything waiting on a CaPSA as CaPSA, across her teams.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function forCapsa(CapstoneExpert $capsa, Collection $rows): Collection
    {
        $items = $rows->filter(fn ($row) => $row->inSections)
            ->flatMap(fn ($row) => $this->forRow($row, $row->milestones?->milestones ?? collect(), 'capsa')
                ->where('hat', 'capsa'));

        $teamUrl = fn ($team) => route('capsa.team_management.show', $team->team_id);
        $sectionOf = $rows->mapWithKeys(fn ($row) => [$row->team->team_id => $row->section]);

        $leaves = MemberLeaveRequest::visibleTo($capsa, MemberLeaveRequest::with(['team', 'student', 'requester'])
            ->where('status', MemberLeaveRequest::PENDING)->get());

        foreach ($leaves as $request) {
            $member = $this->nameOf($request->student) ?: 'A member';
            $isLeave = $request->type === MemberLeaveRequest::LEAVE;
            $items->push($this->item([
                'kind' => 'leave', 'hat' => 'capsa',
                'teamId' => (int) $request->team_id, 'teamName' => $request->team->team_name,
                'section' => $sectionOf[$request->team_id] ?? '', 'teamUrl' => $teamUrl($request->team),
                'what' => $isLeave ? $member.' asks to leave' : 'Remove '.$member.' from the team',
                'why' => '"'.Str::limit((string) $request->reason, 120).'"'.($isLeave ? '' : ' · asked by the leader'),
                'at' => $request->created_at,
                'label' => 'Approve',
                'url' => route('capsa.team_management', ['tab' => 'leave_requests']),
                'decide' => [
                    'url' => route('capsa.leave_requests.update', $request),
                    'field' => 'status', 'approve' => MemberLeaveRequest::APPROVED, 'decline' => MemberLeaveRequest::DECLINED,
                    'note' => 'note', 'min' => 1,
                    'effect' => $isLeave
                        ? $member.' leaves the team. The team and its adviser are told.'
                        : $member.' is taken off the team. The team and its adviser are told.',
                ],
            ]));
        }

        $changes = ExpertRemovalRequest::visibleTo($capsa, ExpertRemovalRequest::with(['team', 'expert', 'assignment.expertRole.role'])
            ->where('status', ExpertRemovalRequest::PENDING)->get());

        foreach ($changes as $request) {
            $role = $request->assignment?->engaged_role_name ?: 'expert';
            $items->push($this->item([
                'kind' => 'expert', 'hat' => 'capsa',
                'teamId' => (int) $request->team_id, 'teamName' => $request->team->team_name,
                'section' => $sectionOf[$request->team_id] ?? '', 'teamUrl' => $teamUrl($request->team),
                'what' => 'Replace their '.$role,
                'why' => ($request->expert?->name ?? 'Their '.$role).' · "'.Str::limit((string) $request->reason, 100).'"',
                'at' => $request->created_at,
                'label' => 'Approve',
                'url' => route('capsa.team_management', ['tab' => 'leave_requests']),
                'decide' => [
                    'url' => route('capsa.expert_removal_requests.update', $request),
                    'field' => 'status', 'approve' => ExpertRemovalRequest::APPROVED, 'decline' => ExpertRemovalRequest::DECLINED,
                    'note' => 'note', 'min' => 1,
                    'effect' => 'The '.$role.' seat opens and the team can ask a new '.$role.'. Everyone involved is told.',
                ],
            ]));
        }

        return $this->order($items);
    }

    /**
     * Everything waiting on someone as an adviser, consultant, grammarian or
     * panelist.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function forExpert(CapstoneExpert $expert, Collection $rows, string $portal): Collection
    {
        $expertId = (int) $expert->expert_id;
        $byTeam = $rows->keyBy(fn ($row) => (int) $row->team->team_id);
        $teamUrl = fn (int $teamId) => $byTeam->has($teamId)
            ? route($portal === 'capsa' ? 'capsa.team_management.show' : 'instructor.teams.show', $teamId)
            : null;

        $items = $rows->filter(fn ($row) => $row->myRole)
            ->flatMap(fn ($row) => $this->forRow($row, collect(), $portal)->where('hat', '!=', 'capsa'));

        $invites = TeamExpertAssignment::where('expert_id', $expertId)
            ->where('status', 'pending')
            ->with(['team', 'expertRole.role', 'role'])
            ->get()
            ->filter(fn ($assignment) => $assignment->team);

        foreach ($invites as $assignment) {
            $role = $assignment->engaged_role_name ?: 'expert';
            $items->push($this->item([
                'kind' => 'invite', 'hat' => $role,
                'teamId' => (int) $assignment->team_id, 'teamName' => $assignment->team->team_name,
                'section' => $byTeam->get((int) $assignment->team_id)?->section ?? '',
                'teamUrl' => $teamUrl((int) $assignment->team_id),
                'what' => 'Invited as '.$role,
                'why' => $assignment->team->project_title ?: 'They asked you to take them on',
                'at' => $assignment->created_at,
                'label' => 'Accept',
                'url' => route('instructor.teams', ['tab' => 'applications']),
                'read' => ['application', $assignment->assignment_id],
                'decide' => [
                    'url' => route('instructor.assignments.update', $assignment->assignment_id),
                    'field' => 'status', 'approve' => 'approved', 'decline' => 'rejected',
                    'note' => 'reason', 'min' => 20,
                    'effect' => 'You become the '.$role.' of Team '.$assignment->team->team_name.'. The team is told.',
                ],
            ]));
        }

        $resolver = app(RecipientResolver::class);
        $titles = CapstonePreProposal::where('adviser_status', TitleRevision::PENDING)
            ->whereNull('retired_at')
            ->with('team')
            ->get()
            ->filter(fn ($p) => $p->team && $resolver->resolveTeamRoleExpert((int) $p->team_id, 'adviser') === $expertId);

        foreach ($titles as $proposal) {
            $items->push($this->item([
                'kind' => 'adviser_title', 'hat' => 'adviser',
                'teamId' => (int) $proposal->team_id, 'teamName' => $proposal->team->team_name,
                'section' => $byTeam->get((int) $proposal->team_id)?->section ?? '',
                'teamUrl' => $teamUrl((int) $proposal->team_id),
                'what' => 'Confirm their new title',
                'why' => Str::limit((string) $proposal->title, 90).' · you are their adviser',
                'at' => $proposal->submitted_at ?? $proposal->updated_at,
                'label' => 'Confirm title',
                'url' => route('instructor.teams', ['tab' => 'todo']),
                'read' => ['title', $proposal->id],
                'decide' => [
                    'url' => route('instructor.title-confirmations.update', $proposal),
                    'field' => 'decision', 'approve' => 'approve', 'decline' => 'decline',
                    'declineLabel' => 'Ask for another',
                    'note' => 'note', 'min' => 10,
                    'effect' => 'It becomes the title of Team '.$proposal->team->team_name.'. The team is told.',
                ],
            ]));
        }

        // A passed team asking to change part of its title. Only an approval
        // changes it.
        $changes = TitleChangeRequest::where('status', TitleChangeRequest::PENDING)
            ->with(['team', 'requester'])
            ->get()
            ->filter(fn ($c) => $c->team && $resolver->resolveTeamRoleExpert((int) $c->team_id, 'adviser') === $expertId);

        foreach ($changes as $change) {
            $items->push($this->item([
                'kind' => 'title_change', 'hat' => 'adviser',
                'teamId' => (int) $change->team_id, 'teamName' => $change->team->team_name,
                'section' => $byTeam->get((int) $change->team_id)?->section ?? '',
                'teamUrl' => $teamUrl((int) $change->team_id),
                'what' => 'Approve a title change',
                'why' => ($change->requester ? 'asked by '.trim($change->requester->f_name.' '.$change->requester->l_name) : 'asked by the team')
                    .($change->reason ? ' · '.Str::limit((string) $change->reason, 80) : ''),
                'titleChange' => ['now' => $change->current_title, 'next' => $change->new_title],
                'at' => $change->created_at,
                'label' => 'Approve change',
                'url' => route('instructor.teams', ['tab' => 'todo']),
                'decide' => [
                    'url' => route('instructor.title-changes.update', $change),
                    'field' => 'decision', 'approve' => 'approve', 'decline' => 'decline',
                    'declineLabel' => 'Keep the title',
                    'note' => 'note', 'min' => 10,
                    'effect' => 'The title of Team '.$change->team->team_name.' changes. The team is told.',
                ],
            ]));
        }

        // The CaPA's go: a team defends Title, Pre Oral or Final Oral only once
        // its adviser says it is ready. Not ready cancels the date, and the
        // CaPSA gives the team a new one.
        $defenses = DefenseSchedule::whereIn('defense_type', DefenseBoard::PAID_STAGES)
            ->whereNull('outcome')
            ->whereNull('capa_answer')
            ->where('schedule_date', '>=', now())
            ->with('team')
            ->get()
            ->filter(fn ($d) => $d->team && $resolver->resolveTeamRoleExpert((int) $d->team_id, 'adviser') === $expertId);

        foreach ($defenses as $defense) {
            $items->push($this->item([
                'kind' => 'go', 'hat' => 'adviser',
                'teamId' => (int) $defense->team_id, 'teamName' => $defense->team->team_name,
                'section' => $byTeam->get((int) $defense->team_id)?->section ?? '',
                'teamUrl' => $teamUrl((int) $defense->team_id),
                'what' => 'Give your go: '.$defense->defense_type,
                'why' => $defense->schedule_date->format('D, M j, g:i A').($defense->venue ? ' · '.$defense->venue : ''),
                // Waiting on the adviser since the date was set.
                'at' => $defense->created_at,
                'label' => 'Go',
                'url' => route('instructor.teams', ['tab' => 'todo']),
                'decide' => [
                    'url' => route('instructor.defense-go.update', $defense),
                    'field' => 'answer', 'approve' => CapaGo::GO, 'decline' => CapaGo::NOT_READY,
                    'declineLabel' => 'Not ready',
                    'note' => 'note', 'min' => 0, 'noteOptional' => true,
                    'declineEffect' => 'The '.$defense->defense_type.' is cancelled and the CaPSA gives the team a new date.',
                    'effect' => 'Team '.$defense->team->team_name.' defends on '.$defense->schedule_date->format('M j').'.',
                ],
            ]));
        }

        $handled = PanelGrade::where('expert_id', $expertId)
            ->whereIn('status', [PanelGrade::SUBMITTED, PanelGrade::ABSTAINED])
            ->pluck('defense_id')->all();
        $grades = DefensePanel::where('expert_id', $expertId)->with('schedule.team')->get()
            ->filter(fn ($panel) => $panel->schedule?->schedule_date && $panel->schedule->schedule_date->lte(now()))
            ->reject(fn ($panel) => in_array($panel->defense_id, $handled, true))
            ->unique('defense_id');

        foreach ($grades as $panel) {
            $schedule = $panel->schedule;
            $items->push($this->item([
                'kind' => 'grade', 'hat' => 'panel',
                'teamId' => (int) $schedule->team_id, 'teamName' => $schedule->team?->team_name ?? 'A team',
                'section' => $byTeam->get((int) $schedule->team_id)?->section ?? '',
                'teamUrl' => $teamUrl((int) $schedule->team_id),
                'what' => 'Submit your score sheet',
                'why' => $schedule->defense_type.' · you sat on the panel',
                'at' => $schedule->schedule_date,
                'label' => 'Open score sheet',
                'url' => route('instructor.grading.show', $schedule->defense_id),
            ]));
        }

        return $this->order($items);
    }

    /**
     * What a CaPSA decided lately, newest first.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function decidedByCapsa(CapstoneExpert $capsa, Collection $rows): Collection
    {
        $done = collect();

        foreach (MemberLeaveRequest::visibleTo($capsa, MemberLeaveRequest::with(['team', 'student'])
            ->whereIn('status', [MemberLeaveRequest::APPROVED, MemberLeaveRequest::DECLINED])
            ->latest('updated_at')->limit(30)->get()) as $request) {
            $done->push($this->done($request->team, 'Leave request from '.($this->nameOf($request->student) ?: 'a member'),
                ucfirst($request->status), $request->decided_at ?? $request->updated_at, 'capsa'));
        }

        foreach (ExpertRemovalRequest::visibleTo($capsa, ExpertRemovalRequest::with(['team', 'expert'])
            ->whereIn('status', [ExpertRemovalRequest::APPROVED, ExpertRemovalRequest::DECLINED])
            ->latest('updated_at')->limit(30)->get()) as $request) {
            $done->push($this->done($request->team, 'Change of '.($request->expert?->name ?? 'adviser'),
                ucfirst($request->status), $request->decided_at ?? $request->updated_at, 'capsa'));
        }

        foreach ($rows->filter(fn ($row) => $row->inSections) as $row) {
            foreach ($row->proposals->filter(fn ($p) => $p->approved_status || $p->rejection_reason) as $proposal) {
                $done->push($this->done($row->team, 'Title "'.Str::limit((string) $proposal->title, 48).'"',
                    $proposal->approved_status ? 'Recommended' : 'Turned down', $proposal->updated_at, 'capsa'));
            }
        }

        return $done->filter(fn ($d) => $d->at)->sortByDesc(fn ($d) => $d->at->getTimestamp())->take(30)->values();
    }

    /**
     * What an expert decided lately, newest first.
     *
     * @return Collection<int, object>
     */
    public function decidedByExpert(CapstoneExpert $expert): Collection
    {
        $expertId = (int) $expert->expert_id;
        $done = collect();

        TeamExpertAssignment::where('expert_id', $expertId)
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['team', 'expertRole.role', 'role'])
            ->latest('updated_at')->limit(20)->get()
            ->filter(fn ($a) => $a->team)
            ->each(fn ($a) => $done->push($this->done($a->team, ucfirst($a->engaged_role_name ?: 'expert').' invitation',
                $a->status === 'approved' ? 'Accepted' : 'Declined', $a->updated_at, $a->engaged_role_name ?: 'expert')));

        $reviews = CapstoneDocumentReview::where('expert_id', $expertId)
            ->where('role', '!=', 'subject_adviser')
            ->reviewed()
            ->whereNotNull('reviewed_at')
            ->with('document')
            ->latest('reviewed_at')->limit(20)->get();
        $teamNames = Team::whereIn('team_id', $reviews->pluck('document.team_id')->filter()->unique())->pluck('team_name', 'team_id');

        foreach ($reviews as $review) {
            $teamId = $review->document?->team_id;
            $done->push((object) [
                'teamId' => $teamId, 'teamName' => $teamNames[$teamId] ?? null,
                'what' => $review->document?->chapter ?: ($review->document?->file_name ?: 'A paper'),
                'result' => 'Reviewed', 'at' => Carbon::parse($review->reviewed_at), 'hat' => 'expert',
            ]);
        }

        PanelGrade::where('expert_id', $expertId)
            ->whereIn('status', [PanelGrade::SUBMITTED, PanelGrade::ABSTAINED])
            ->with('schedule.team')
            ->latest('updated_at')->limit(20)->get()
            ->each(fn ($grade) => $done->push($this->done($grade->schedule?->team, 'Score sheet · '.($grade->schedule?->defense_type ?? 'defense'),
                $grade->status === PanelGrade::SUBMITTED ? 'Submitted' : 'Abstained', $grade->submitted_at ?? $grade->updated_at, 'panel')));

        return $done->filter(fn ($d) => $d->at)->sortByDesc(fn ($d) => $d->at->getTimestamp())->take(30)->values();
    }

    /**
     * Grouped as the list shows them: groups in the order their first item
     * comes, and inside each group the item that has waited longest first.
     *
     * @param  Collection<int, object>  $items
     * @return Collection<string, Collection<int, object>>
     */
    public static function grouped(Collection $items): Collection
    {
        return $items->groupBy('group')->map(fn ($group) => $group->values());
    }

    /**
     * @param  Collection<int, object>  $items
     * @return Collection<int, object>
     */
    public function order(Collection $items): Collection
    {
        $groups = array_values(array_unique(self::GROUPS));

        return $items->sortBy([
            fn ($a, $b) => array_search($a->group, $groups, true) <=> array_search($b->group, $groups, true),
            fn ($a, $b) => ($a->at?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->at?->getTimestamp() ?? PHP_INT_MAX),
        ])->values();
    }

    private function item(array $fields): object
    {
        $at = $fields['at'] ?? null;
        $at = $at ? Carbon::parse($at) : null;

        return (object) array_merge([
            'decide' => null,
            'read' => null,
            'titleChange' => null,
            'teamUrl' => null,
            'section' => '',
        ], $fields, [
            'group' => self::GROUPS[$fields['kind']],
            'at' => $at,
            'age' => $at ? (int) floor($at->diffInDays(now(), true)) : 0,
            'fresh' => $at !== null && $at->gt(now()->subDay()),
        ]);
    }

    private function done(?object $team, string $what, string $result, $at, string $hat): object
    {
        return (object) [
            'teamId' => $team?->team_id,
            'teamName' => $team?->team_name,
            'what' => $what,
            'result' => $result,
            'at' => $at ? Carbon::parse($at) : null,
            'hat' => $hat,
        ];
    }

    private function nameOf(?object $student): string
    {
        return $student ? trim(($student->f_name ?? '').' '.($student->l_name ?? '')) : '';
    }
}
