<?php

namespace App\Services;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One panelist grading for others on the panel.
 *
 * Not everyone brings a laptop to a defense, and not every chair is at ease
 * with the system, so the panel can pick one of its own to grade for the rest.
 * The others join that grade: their rows hold a copy of the grader's - scores,
 * remarks, draft or submitted, abstained - kept in step on every save. Every
 * panelist still has a grade of their own in the table, so the team's average
 * is still every panelist's total over the panel, and the decision still waits
 * for the whole panel to be in.
 *
 * A joined panelist can leave and grade on their own until the decision is
 * recorded. Nobody can be joined once they have turned in a grade of their own.
 */
class SharedGrading
{
    /**
     * Who grades for whom on this defense, by expert id.
     *
     * @return array{grader: int|null, joined: list<int>}
     */
    public function arrangement(DefenseSchedule $defense): array
    {
        $joined = PanelGrade::where('defense_id', $defense->defense_id)
            ->whereNotNull('graded_by_expert_id')
            ->get(['expert_id', 'graded_by_expert_id']);

        return [
            'grader' => $joined->isEmpty() ? null : (int) $joined->first()->graded_by_expert_id,
            'joined' => $joined->pluck('expert_id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * Set who grades: one panelist for those in $joinIds, or - with no grader -
     * everyone on their own again. Anyone on the panel may set it.
     *
     * @param  list<int>  $joinIds
     * @return string|null why it was refused, or null when done
     */
    public function assign(DefenseSchedule $defense, CapstoneExpert $by, ?int $graderId, array $joinIds, GradingRubric $rubric): ?string
    {
        if ($defense->hasOutcome()) {
            return 'The panel has already recorded its decision for this defense.';
        }

        $panel = $this->panel($defense);
        $joinIds = collect($joinIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($graderId !== null && ! $panel->has($graderId)) {
            return 'The grader has to be on this panel.';
        }

        if ($graderId === null) {
            $joinIds = collect();
        }

        $joinIds = $joinIds->reject(fn (int $id) => $id === $graderId)->values();

        if ($joinIds->contains(fn (int $id) => ! $panel->has($id))) {
            return 'Only panelists on this defense can be graded for.';
        }

        $grades = PanelGrade::where('defense_id', $defense->defense_id)->get()->keyBy(fn (PanelGrade $g) => (int) $g->expert_id);

        foreach ($joinIds as $id) {
            $own = $grades->get($id);

            if ($own && ! $own->isJoined() && in_array($own->status, [PanelGrade::SUBMITTED, PanelGrade::ABSTAINED], true)) {
                $name = $panel->get($id)?->expert?->name ?? 'A panelist';

                return $name.' already turned in a grade of their own.';
            }
        }

        $newlyJoined = [];

        DB::transaction(function () use ($defense, $by, $graderId, $joinIds, $grades, $rubric, &$newlyJoined) {
            // Anyone joined to someone else, or no longer joined at all, goes
            // back to a grade of their own - and so does the grader.
            foreach ($grades as $id => $grade) {
                $keep = $graderId !== null && $joinIds->contains($id) && (int) $grade->graded_by_expert_id === $graderId;

                if ($grade->isJoined() && ! $keep) {
                    $this->resetToOwn($grade);
                }
            }

            $source = $graderId === null ? null : PanelGrade::where('defense_id', $defense->defense_id)->where('expert_id', $graderId)->first();

            foreach ($joinIds as $id) {
                $grade = $grades->get($id);

                if ($grade && (int) $grade->graded_by_expert_id === $graderId) {
                    continue;
                }

                $grade = PanelGrade::updateOrCreate(
                    ['defense_id' => $defense->defense_id, 'expert_id' => $id],
                    [
                        'graded_by_expert_id' => $graderId,
                        'joined_at' => now(),
                        'joined_set_by' => $by->expert_id,
                        'rubric_id' => $source?->rubric_id ?? $rubric->rubric_id,
                    ]
                );
                $this->copy($source, $grade);
                $newlyJoined[] = $id;
            }
        });

        $grader = $graderId === null ? null : CapstoneExpert::find($graderId);

        foreach ($newlyJoined as $id) {
            if ($id === (int) $by->expert_id) {
                continue;
            }

            Notifier::toExpert(
                $id,
                'shared_grading',
                'Someone is grading for you',
                e($grader?->name ?? 'A panelist').' is grading <strong>'.e($defense->team?->team_name ?? 'a team').'</strong> for you. Their grade counts as yours.',
                route('instructor.grading.show', $defense->defense_id),
            );
        }

        return null;
    }

    /**
     * Leave a shared grade, to grade on one's own.
     *
     * @return string|null why it was refused, or null when done
     */
    public function leave(DefenseSchedule $defense, CapstoneExpert $expert): ?string
    {
        if ($defense->hasOutcome()) {
            return 'The panel has already recorded its decision for this defense.';
        }

        $grade = PanelGrade::where('defense_id', $defense->defense_id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $grade || ! $grade->isJoined()) {
            return 'Nobody is grading for you on this defense.';
        }

        $graderId = (int) $grade->graded_by_expert_id;
        $this->resetToOwn($grade);

        Notifier::toExpert(
            $graderId,
            'shared_grading',
            'A panelist will grade on their own',
            e($expert->name).' left your shared grade for <strong>'.e($defense->team?->team_name ?? 'a team').'</strong> and will grade on their own.',
            route('instructor.grading.show', $defense->defense_id),
        );

        return null;
    }

    /**
     * Copy the grader's grade - whatever it now is - onto everyone joined to it.
     * Called after every save, submission, abstention and withdrawal.
     */
    public function sync(PanelGrade $source): void
    {
        $joined = PanelGrade::where('defense_id', $source->defense_id)
            ->where('graded_by_expert_id', $source->expert_id)
            ->get();

        if ($joined->isEmpty()) {
            return;
        }

        $turnedIn = in_array($source->status, [PanelGrade::SUBMITTED, PanelGrade::ABSTAINED], true);

        foreach ($joined as $grade) {
            $wasIn = in_array($grade->status, [PanelGrade::SUBMITTED, PanelGrade::ABSTAINED], true);
            $this->copy($source, $grade);

            if ($turnedIn && ! $wasIn) {
                $defense = $source->schedule;

                Notifier::toExpert(
                    (int) $grade->expert_id,
                    'shared_grading',
                    $source->status === PanelGrade::ABSTAINED ? 'The panel abstained' : 'Your shared grade is in',
                    e($source->expert?->name ?? 'Your grader').($source->status === PanelGrade::ABSTAINED ? ' abstained' : ' submitted the grade')
                        .' for <strong>'.e($defense?->team?->team_name ?? 'a team').'</strong>, for you as well.',
                    route('instructor.grading.show', $source->defense_id),
                );
            }
        }
    }

    /**
     * The chair records the panel's decision - or the panelist grading for
     * the chair, so a chair who is not at the keyboard does not hold it up.
     */
    public function canRecordOutcome(DefenseSchedule $defense, int $expertId): bool
    {
        $chair = $this->panel($defense)->first(fn (DefensePanel $p) => $p->panel_role === 'Panel Chair');

        if (! $chair) {
            return false;
        }

        if ((int) $chair->expert_id === $expertId) {
            return true;
        }

        return PanelGrade::where('defense_id', $defense->defense_id)
            ->where('expert_id', $chair->expert_id)
            ->where('graded_by_expert_id', $expertId)
            ->exists();
    }

    /** @return Collection<int, DefensePanel> keyed by expert id */
    private function panel(DefenseSchedule $defense): Collection
    {
        return DefensePanel::where('defense_id', $defense->defense_id)
            ->with('expert')
            ->get()
            ->keyBy(fn (DefensePanel $p) => (int) $p->expert_id);
    }

    private function copy(?PanelGrade $source, PanelGrade $grade): void
    {
        $grade->update([
            'rubric_id' => $source?->rubric_id ?? $grade->rubric_id,
            'scores' => $source?->scores ?? [],
            'total_score' => $source?->total_score,
            'verdict' => $source?->verdict,
            'remarks' => $source?->remarks,
            'status' => $source?->status ?? PanelGrade::DRAFT,
            'submitted_at' => $source?->submitted_at,
        ]);
    }

    private function resetToOwn(PanelGrade $grade): void
    {
        $grade->update([
            'graded_by_expert_id' => null,
            'joined_at' => null,
            'joined_set_by' => null,
            'scores' => [],
            'total_score' => null,
            'verdict' => null,
            'remarks' => null,
            'status' => PanelGrade::DRAFT,
            'submitted_at' => null,
        ]);
    }
}
