<?php

namespace App\Support;

use App\Models\ExpertEvaluation;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
    /** How long a team's list of owed evaluations is reused between pages. */
    private const CACHE_SECONDS = 300;

    public function __construct(private TeamChecklist $checklists) {}

    /**
     * What the leader still owes: one row per finished phase and engaged
     * expert, oldest phase first.
     *
     * @return Collection<int, object{milestone_id: int, milestone: string, position: int, expert_id: int, expert: string, role: string, role_label: string}>
     */
    public function due(Team $team): Collection
    {
        // Cached as plain arrays: the cache refuses to unserialize objects
        // (cache.serializable_classes), so a cached Collection comes back as
        // __PHP_Incomplete_Class.
        $rows = Cache::remember(self::cacheKey($team), self::CACHE_SECONDS, fn () => $this->owed($team)
            ->map(fn ($row) => (array) $row)
            ->all());

        return collect(is_array($rows) ? $rows : [])->map(fn (array $row) => (object) $row)->values();
    }

    /**
     * Worked out fresh: see due().
     *
     * @return Collection<int, object>
     */
    private function owed(Team $team): Collection
    {
        $experts = $this->engagedExperts($team);

        if ($experts->isEmpty()) {
            return collect();
        }

        $done = ExpertEvaluation::where('team_id', $team->team_id)->get()
            ->map(fn (ExpertEvaluation $e) => $e->checklist_milestone_id.'|'.$e->expert_id.'|'.$e->role)
            ->flip();

        return $this->finishedPhases($team)->flatMap(fn ($phase) => $experts->map(fn ($e) => (object) [
            'milestone_id' => $phase->id,
            'milestone' => $phase->name,
            'position' => $phase->position,
            'expert_id' => $e->expert_id,
            'expert' => $e->name,
            'role' => $e->role,
            'role_label' => ExpertEvaluation::ROLES[$e->role],
        ]))
            ->reject(fn ($row) => $done->has($row->milestone_id.'|'.$row->expert_id.'|'.$row->role))
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

        self::forget($team);

        return null;
    }

    /**
     * Tell the leader - bell, desktop popup and email - about each finished
     * phase that still has evaluations owed, once per phase.
     *
     * @return int how many phases were announced
     */
    public function remind(Team $team): int
    {
        $leader = $team->leader_id ? Student::find($team->leader_id) : null;

        if (! $leader) {
            return 0;
        }

        $owedPhases = $this->due($team)->groupBy('milestone_id');
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
                'Your team finished <strong>'.e($phase).'</strong>. Rate '.e($names).' from 1 to 5 stars with a comment.',
                route('student.experts', ['tab' => 'evaluations']),
                ['tag' => 'evaluation:'.$milestoneId],
            );
            $count++;
        }

        return $count;
    }

    public static function forget(Team $team): void
    {
        Cache::forget(self::cacheKey($team));
    }

    private static function cacheKey(Team $team): string
    {
        return 'expert-evaluations-due:v2:'.$team->team_id;
    }

    /**
     * The team's experts by the role they were engaged as.
     *
     * @return Collection<int, object{expert_id: int, name: string, role: string}>
     */
    private function engagedExperts(Team $team): Collection
    {
        return TeamExpertAssignment::where('team_id', $team->team_id)
            ->where('status', 'approved')
            ->with(['expert', 'expertRole.role'])
            ->get()
            ->map(fn (TeamExpertAssignment $a) => (object) [
                'expert_id' => (int) $a->expert_id,
                'name' => $a->expert?->name ?? 'Expert',
                'role' => strtolower(trim((string) $a->engaged_role_name)),
            ])
            ->filter(fn ($e) => array_key_exists($e->role, ExpertEvaluation::ROLES))
            ->unique(fn ($e) => $e->expert_id.'|'.$e->role)
            ->sortBy(fn ($e) => array_search($e->role, array_keys(ExpertEvaluation::ROLES), true))
            ->values();
    }

    /**
     * The phases of the team's checklist with every item done, in order.
     *
     * @return Collection<int, object{id: int, name: string, position: int}>
     */
    private function finishedPhases(Team $team): Collection
    {
        $checklist = $this->checklists->for($team);

        if (! $checklist) {
            return collect();
        }

        return MilestoneProgress::forTeam($checklist, (int) $team->team_id)->milestones
            ->filter(fn ($m) => $m->total > 0 && $m->completed === $m->total)
            ->map(fn ($m) => (object) [
                'id' => (int) $m->milestone->id,
                'name' => (string) $m->milestone->name,
                'position' => (int) $m->milestone->position,
            ])
            ->values();
    }
}
