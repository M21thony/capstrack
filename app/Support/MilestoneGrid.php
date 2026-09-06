<?php

namespace App\Support;

use App\Models\Milestone;
use App\Models\MilestoneItem;
use Illuminate\Support\Collection;

/**
 * A section's checklists as one grid: deliverables across, teams down.
 *
 * Each team has its own milestone with its own items, so the columns have to
 * be reconciled across them. Items that came from a template line up by
 * template_key - rename a line on the paper and the column still matches.
 * Anything a CaPSA added by hand has no key, so those line up by title, which
 * keeps the same custom line from becoming one sparse column per team.
 *
 * Teams go down rather than across because the nineteen deliverables are a
 * fixed list and the teams are not: a CaPSA with three sections has around
 * two dozen, and a page grows downwards far more gracefully than sideways.
 */
class MilestoneGrid
{
    /** Where items a CaPSA added themselves are gathered. */
    public const OWN_PHASE = 'Added by you';

    /**
     * @param  Collection<int, Milestone>  $milestones  one per team, items loaded
     * @param  Collection<int, object>  $teamRows  the teams, in display order
     */
    public static function build(Collection $milestones, Collection $teamRows): object
    {
        $byTeam = $milestones->keyBy('team_id');

        $columns = self::columnsFrom($milestones);

        $rows = $teamRows
            ->sortBy([
                ['section', 'asc'],
                ['team.team_name', 'asc'],
            ])
            ->values()
            ->map(fn ($teamRow) => self::rowFor($teamRow, $byTeam->get($teamRow->team->team_id), $columns));

        return (object) [
            'columns' => $columns,
            'phases' => self::phasesFrom($columns),
            'rows' => $rows,
            'hasAnyChecklist' => $milestones->isNotEmpty(),
        ];
    }

    /**
     * One column per distinct deliverable across every team's checklist, in
     * the order the checklist puts them.
     */
    private static function columnsFrom(Collection $milestones): Collection
    {
        $seen = [];

        foreach ($milestones as $milestone) {
            foreach ($milestone->items->sortBy([['position', 'asc'], ['id', 'asc']]) as $item) {
                $key = self::keyFor($item);

                if (isset($seen[$key])) {
                    // Keep the earliest position any team gives it, so a
                    // reordered checklist on one team does not shuffle the
                    // whole grid.
                    $seen[$key]['position'] = min($seen[$key]['position'], (int) $item->position);
                    $seen[$key]['dates'][] = $item->due_date?->toDateString();

                    continue;
                }

                $seen[$key] = [
                    'key' => $key,
                    'label' => $item->columnLabel(),
                    'full' => $item->title,
                    'kind' => $item->kind,
                    'phase' => $item->phase ?: self::OWN_PHASE,
                    'position' => (int) $item->position,
                    'ticked_by_hand' => $item->isTickedByHand(),
                    // Every date this deliverable carries across the section.
                    // A deliverable has one deadline for everyone, so if these
                    // ever disagree the header has to say so rather than pick
                    // one and quietly hide the others.
                    'dates' => [$item->due_date?->toDateString()],
                ];
            }
        }

        // Template lines first in checklist order, then anything added by
        // hand - which has no place in the paper's ordering.
        return collect($seen)
            ->sortBy(fn ($c) => sprintf(
                '%d-%06d',
                $c['phase'] === self::OWN_PHASE ? 1 : 0,
                $c['position'],
            ))
            ->values()
            ->map(function (array $c) {
                $dates = array_values(array_unique($c['dates']));

                // One agreed date, or none at all. Anything else is 'mixed',
                // which the header shows as such - a deliverable whose
                // deadline differs team to team is a mistake to surface, not
                // an average to compute.
                $c['due'] = count($dates) === 1 ? $dates[0] : 'mixed';
                unset($c['dates']);

                return (object) $c;
            });
    }

    /** The phase bands above the columns, each knowing how many it spans. */
    private static function phasesFrom(Collection $columns): Collection
    {
        // groupBy keeps first-seen order, and the columns are already in
        // checklist order, so the bands come out in the paper's sequence.
        return $columns
            ->groupBy('phase')
            ->map(fn (Collection $group, string $name) => (object) [
                'name' => $name,
                'span' => $group->count(),
                'keys' => $group->pluck('key')->all(),
            ])
            ->values();
    }

    private static function rowFor(object $teamRow, ?Milestone $milestone, Collection $columns): object
    {
        if (! $milestone) {
            return (object) [
                'team' => $teamRow->team,
                'section' => $teamRow->section,
                'milestone' => null,
                'total' => 0,
                'completed' => 0,
                'percent' => 0,
                'cells' => $columns->mapWithKeys(fn ($c) => [$c->key => null]),
            ];
        }

        $progress = $milestone->progress();
        $byKey = $progress->items->keyBy(fn ($state) => self::keyFor($state->item));

        return (object) [
            'team' => $teamRow->team,
            'section' => $teamRow->section,
            'milestone' => $milestone,
            'total' => $progress->total,
            'completed' => $progress->completed,
            'percent' => $progress->percent,
            // A null cell means this team's checklist does not carry that
            // line at all, which is different from having it and not doing it.
            'cells' => $columns->mapWithKeys(fn ($c) => [$c->key => $byKey->get($c->key)]),
        ];
    }

    /** How one deliverable is addressed across every team that carries it. */
    public static function keyFor(MilestoneItem $item): string
    {
        return $item->template_key ?: 'own:'.mb_strtolower(trim($item->title));
    }
}
