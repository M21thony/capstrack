<?php

namespace App\Support;

use App\Models\DefenseSchedule;
use Illuminate\Support\Collection;

/**
 * Each team's score at every defense it has sat, for the people allowed to see
 * it besides the team: their section's CaPSA/TSA and their adviser.
 *
 * The score is DefenseSchedule::panelAverage() - every panelist's total added
 * up and divided by the size of the panel (65 + 85 + 75, over 3). It stays
 * empty until the whole panel has graded, so nobody reads a partial number as
 * the final one.
 */
class DefenseScores
{
    private const SHORT = [
        'Pre-proposal Defense' => 'Pre-Proposal',
        'Title Defense' => 'Title',
        'Pre Oral Defense' => 'POD',
        'Final Oral Defense' => 'FOD',
    ];

    /**
     * @param  iterable<int>  $teamIds
     * @return Collection<int, Collection<int, object>> keyed by team_id, in stage order
     */
    public static function forTeams(iterable $teamIds): Collection
    {
        $ids = collect($teamIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $order = array_flip(DefenseProgress::STAGES);

        return DefenseSchedule::whereIn('team_id', $ids)
            ->with(['panels', 'grades'])
            ->get()
            // No panel yet means nobody to grade it - nothing to show.
            ->filter(fn (DefenseSchedule $s) => $s->panelSize() > 0)
            ->map(fn (DefenseSchedule $s) => (object) [
                'team_id' => (int) $s->team_id,
                'defense_type' => $s->defense_type,
                'label' => self::SHORT[$s->defense_type] ?? $s->defense_type,
                'attempt' => max(1, (int) $s->attempt),
                'average' => $s->panelAverage(),
                'graded' => $s->gradesInCount(),
                'panel_size' => $s->panelSize(),
            ])
            // One line per stage: the latest attempt only. A failed attempt is
            // an auto-fail in this flow - its score says nothing the redefense
            // does not - so the older rows only repeated the column. The
            // current attempt's "x/y graded" is what shows the panel still
            // owes grades before anybody decides on it.
            ->groupBy(fn ($r) => $r->team_id.'|'.$r->defense_type)
            ->map(fn ($attempts) => $attempts->sortByDesc('attempt')->first())
            ->sortBy(fn ($r) => sprintf('%02d-%02d', $order[$r->defense_type] ?? 99, $r->attempt))
            ->groupBy('team_id');
    }

    /** 82.50 reads as 82.5, and 80.00 as 80. */
    public static function format(?float $average): string
    {
        return $average === null ? '' : rtrim(rtrim(number_format($average, 2), '0'), '.');
    }
}
