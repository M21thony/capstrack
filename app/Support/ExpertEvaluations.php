<?php

namespace App\Support;

use App\Models\ExpertEvaluation;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The leader's evaluation of the team's experts, phase by phase.
 *
 * Every time the team finishes a milestone of the Progress Tracker - Getting
 * Started through Final Manuscript - the leader owes each of the team's
 * experts (adviser, consultant, grammarian) a rating of one to five stars and
 * a comment for that phase. Nothing marks a phase finished: it is read off
 * the checklist the way the Progress Tracker reads it (MilestoneProgress), so
 * a phase is due the moment every item in it is done.
 *
 * What is owed is worked out here and nowhere else - the popup, the Experts
 * page and the reminder all ask due(), so they cannot disagree.
 */
class ExpertEvaluations
{
    public function __construct(private TeamChecklist $checklists) {}

    /**
     * What the leader still owes: one row per finished phase and engaged
     * expert, oldest phase first.
     *
     * @return Collection<int, object{milestone_id: int, milestone: string, position: int, expert_id: int, expert: string, role: string, role_label: string}>
     */
    public function due(Team $team): Collection
    {
        // Not cached: the next phase's lock is read fresh, and a list that
        // lagged behind it would show a locked phase with nothing to rate.
        return $this->owed($team);
    }

    /**
     * MilestoneProgress already knows, since a
     * phase whose experts are not rated yet is not finished and holds the
     * next gate shut - this only lays its answer out as rows.
     *
     * @return Collection<int, object>
     */
    private function owed(Team $team): Collection
    {
        $checklist = $this->checklists->for($team);

        if (! $checklist) {
            return collect();
        }

        return MilestoneProgress::forTeam($checklist, (int) $team->team_id)->milestones
            ->flatMap(fn ($m) => $m->evaluations_owed->map(fn ($e) => (object) [
                'milestone_id' => (int) $m->milestone->id,
                'milestone' => (string) $m->milestone->name,
                'position' => (int) $m->milestone->position,
                'expert_id' => $e->expert_id,
                'expert' => $e->name,
                'role' => $e->role,
                'role_label' => ExpertEvaluation::ROLES[$e->role],
            ]))
            ->values();
    }

    /**
     * What the team has already sent, newest first.
     *
     * @return Collection<int, ExpertEvaluation>
     */
    public function history(Team $team): Collection
    {
        return ExpertEvaluation::where('team_id', $team->team_id)
            ->with(['expert', 'milestone'])
            ->latest()
            ->get();
    }

    /**
     * Save the leader's rating. Only what is actually owed can be sent, and
     * only by the team's leader.
     *
     * @return string|null why it was refused, or null when it was saved
     */
    public function submit(Student $leader, Team $team, int $milestoneId, int $expertId, string $role, int $rating, string $comment): ?string
    {
        if ((int) $team->leader_id !== (int) $leader->student_id) {
            return 'Only your team leader evaluates the experts.';
        }

        $owed = $this->due($team)->first(fn ($row) => $row->milestone_id === $milestoneId
            && $row->expert_id === $expertId && $row->role === $role);

        if (! $owed) {
            return 'That evaluation is not due, or it was already sent.';
        }

        ExpertEvaluation::create([
            'team_id' => $team->team_id,
            'expert_id' => $expertId,
            'checklist_milestone_id' => $milestoneId,
            'role' => $role,
            'rating' => max(1, min(5, $rating)),
            'comment' => trim($comment),
            'student_id' => $leader->student_id,
        ]);

        return null;
    }

    /**
     * Tell the leader - bell, desktop popup and email - about each finished
     * phase that still has evaluations owed, once per phase.
     *
     * The caller may pass what due() already worked out for this team, so a
     * page that also shows it does not work it out twice.
     *
     * @param  Collection<int, object>|null  $due
     * @return int how many phases were announced
     */
    public function remind(Team $team, ?Collection $due = null): int
    {
        $leader = $team->leader_id ? Student::find($team->leader_id) : null;

        if (! $leader) {
            return 0;
        }

        $owedPhases = ($due ?? $this->due($team))->groupBy('milestone_id');
        $told = DB::table('expert_evaluation_reminders')->where('team_id', $team->team_id)
            ->pluck('checklist_milestone_id')->map(fn ($id) => (int) $id)->all();

        $count = 0;
        foreach ($owedPhases as $milestoneId => $rows) {
            if (in_array((int) $milestoneId, $told, true)) {
                continue;
            }

            DB::table('expert_evaluation_reminders')->insertOrIgnore([
                'team_id' => $team->team_id,
                'checklist_milestone_id' => $milestoneId,
                'notified_at' => now(),
            ]);

            $phase = $rows->first()->milestone;
            $names = $rows->map(fn ($r) => $r->expert.' ('.$r->role_label.')')->unique()->implode(', ');

            Notifier::toStudent(
                $leader,
                'evaluation_due',
                'Evaluate your experts: '.$phase,
                'Your team finished <strong>'.e($phase).'</strong>. Rate '.e($names).' from 1 to 5 stars with a comment. The next phase stays locked until you do.',
                route('student.experts', ['tab' => 'evaluations']),
                ['tag' => 'evaluation:'.$milestoneId],
            );
            $count++;
        }

        return $count;
    }

    /**
     * For each team: the experts it engaged (by the role it engaged them as)
     * and the ratings already sent, keyed "milestone|expert|role". Read in
     * bulk, for MilestoneProgress to work out which finished phases still owe
     * a rating.
     *
     * @param  Collection<int, int>  $teamIds
     * @return array<int, array{experts: Collection<int, object{expert_id: int, name: string, role: string}>, done: array<string, true>}>
     */
    public static function ledgers(Collection $teamIds): array
    {
        $assignments = TeamExpertAssignment::whereIn('team_id', $teamIds)
            ->where('status', 'approved')
            ->with(['expert', 'expertRole.role'])
            ->get()
            ->groupBy('team_id');

        $sent = ExpertEvaluation::whereIn('team_id', $teamIds)
            ->get(['team_id', 'checklist_milestone_id', 'expert_id', 'role'])
            ->groupBy('team_id');

        $roleOrder = array_keys(ExpertEvaluation::ROLES);
        $out = [];

        foreach ($teamIds as $teamId) {
            $out[$teamId] = [
                'experts' => $assignments->get($teamId, collect())
                    ->map(fn (TeamExpertAssignment $a) => (object) [
                        'expert_id' => (int) $a->expert_id,
                        'name' => $a->expert?->name ?? 'Expert',
                        'role' => strtolower(trim((string) $a->engaged_role_name)),
                    ])
                    ->filter(fn ($e) => array_key_exists($e->role, ExpertEvaluation::ROLES))
                    ->unique(fn ($e) => $e->expert_id.'|'.$e->role)
                    ->sortBy(fn ($e) => array_search($e->role, $roleOrder, true))
                    ->values(),
                'done' => $sent->get($teamId, collect())
                    ->mapWithKeys(fn (ExpertEvaluation $e) => [$e->checklist_milestone_id.'|'.$e->expert_id.'|'.$e->role => true])
                    ->all(),
            ];
        }

        return $out;
    }
}
