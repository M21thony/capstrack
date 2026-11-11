<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * What the signed-in CaPSA/TSA may touch: her own checklists, and the teams in
 * her sections. One place, so no endpoint re-implements it.
 */
class ChecklistAccess
{
    public function __construct(private SectionScope $sections) {}

    public function capsa(): CapstoneExpert
    {
        return auth('expert')->user();
    }

    public function checklist(?Checklist $checklist): void
    {
        abort_unless($checklist && (int) $checklist->capsa_id === $this->capsa()->expert_id, 403);
    }

    public function milestone(?ChecklistMilestone $milestone): void
    {
        $this->checklist($milestone?->checklist);
    }

    public function deliverable(Deliverable $deliverable): void
    {
        $this->milestone($deliverable->milestone);
    }

    /**
     * The deliverables behind a list of ids, refused unless every one of them
     * is on one of her checklists.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Deliverable>
     */
    public function deliverables(array $ids, bool $withTrashed = false): Collection
    {
        $found = Deliverable::query()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->with(['milestone' => fn ($query) => $query->withTrashed()->with('checklist')])
            ->whereIn('id', $ids)
            ->get();

        abort_unless(
            $found->count() === count(array_unique($ids))
                && $found->every(fn (Deliverable $d) => (int) $d->milestone?->checklist?->capsa_id === $this->capsa()->expert_id),
            403,
        );

        return $found;
    }

    /** A team in one of her sections; returns that section. */
    public function team(Team $team): string
    {
        $section = $this->sections->sectionFor($team);

        abort_unless($section !== '' && in_array($section, $this->capsa()->sections ?? [], true), 403);

        return $section;
    }
}
