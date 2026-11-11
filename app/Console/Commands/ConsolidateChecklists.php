<?php

namespace App\Console\Commands;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Milestone;
use App\Support\SectionScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replace every team's own copy of the checklist with its CaPSA/TSA's one list.
 *
 * Copies are grouped by the CaPSA/TSA covering the team's section and the program
 * that section belongs to. The fullest copy in a group becomes the checklist;
 * every team's ticks become checks on it, and uploads that named a line of a
 * copy are pointed at the matching deliverable.
 *
 * Reports only, unless --apply is given. Safe to run again: whatever already
 * exists is found and skipped. The database is MyISAM, so a run that dies
 * halfway cannot be rolled back - back it up before --apply on a shared one.
 * The old tables are left untouched.
 */
class ConsolidateChecklists extends Command
{
    protected $signature = 'checklists:consolidate {--apply : Write the changes. Without it, only report what would happen.}';

    protected $description = "Replace each team's own checklist copy with its CaPSA/TSA's single checklist";

    /** Where lines a CaPSA/TSA added without a phase go. */
    private const OWN_PHASE = 'Added by you';

    public function handle(SectionScope $sections): int
    {
        $apply = (bool) $this->option('apply');
        $capsas = CapstoneExpert::whereNotNull('sections')->get();
        $problems = [];
        $groups = [];

        $copies = Milestone::whereNotNull('team_id')
            ->with(['team', 'items' => fn ($query) => $query->orderBy('position')->orderBy('id')])
            ->get();

        foreach ($copies as $copy) {
            if (! $copy->team) {
                $problems[] = "Checklist copy #{$copy->id} belongs to a team that no longer exists.";

                continue;
            }

            $section = $sections->sectionFor($copy->team);
            $covering = $section === ''
                ? collect()
                : $capsas->filter(fn (CapstoneExpert $capsa) => in_array($section, $capsa->sections ?? [], true));

            if ($covering->count() !== 1) {
                $problems[] = $covering->isEmpty()
                    ? "{$copy->team->team_name}: no CaPSA/TSA covers section ".($section ?: '(none, incomplete profile)').'.'
                    : "{$copy->team->team_name}: more than one CaPSA/TSA covers {$section}, so it was skipped.";

                continue;
            }

            $groups[$covering->first()->expert_id.'|'.explode('-', $section)[0]][] = $copy;
        }

        $totals = ['checklists' => 0, 'milestones' => 0, 'deliverables' => 0, 'checks' => 0, 'uploads' => 0];

        foreach ($groups as $groupKey => $group) {
            [$capsaId, $program] = explode('|', $groupKey);
            $source = collect($group)->sort(fn ($a, $b) => [$b->items->count(), $a->id] <=> [$a->items->count(), $b->id])->first();

            $checklist = Checklist::where('capsa_id', $capsaId)->where('program', $program)->first();

            if (! $checklist?->milestones()->exists()) {
                $totals['checklists'] += $checklist ? 0 : 1;
                $totals['milestones'] += $source->items->map(fn ($item) => $item->phase ?: self::OWN_PHASE)->unique()->count();
                $totals['deliverables'] += $source->items->count();

                if ($apply) {
                    $checklist = $this->build($checklist, (int) $capsaId, $program, $source);
                }
            }

            // Which deliverable each line key lands on. Before a dry run has
            // built anything there are no ids yet, only the keys it would have.
            $ids = $checklist?->milestones()->exists()
                ? $checklist->deliverables()->get()->mapWithKeys(fn (Deliverable $d) => [Deliverable::keyFor($d->template_key, $d->title) => $d->id])->all()
                : $source->items->mapWithKeys(fn ($item) => [Deliverable::keyFor($item->template_key, $item->title) => null])->all();

            foreach ($group as $copy) {
                foreach ($copy->items as $item) {
                    $key = Deliverable::keyFor($item->template_key, $item->title);

                    if (! array_key_exists($key, $ids)) {
                        $problems[] = "{$copy->team->team_name}: \"{$item->title}\" is not on the {$program} checklist that was kept"
                            .($item->completed_at ? ', so its tick was not carried over.' : '.');

                        continue;
                    }

                    if (! $item->completed_at) {
                        continue;
                    }

                    if ($ids[$key] && DeliverableCheck::where('deliverable_id', $ids[$key])->where('team_id', $copy->team_id)->exists()) {
                        continue;
                    }

                    $totals['checks']++;

                    if ($apply) {
                        DeliverableCheck::create([
                            'deliverable_id' => $ids[$key],
                            'team_id' => $copy->team_id,
                            'status' => DeliverableCheck::COMPLETE,
                            'checked_by' => (int) $capsaId,
                            'checked_at' => $item->completed_at,
                        ]);
                    }
                }
            }

            $itemKeys = collect($group)->flatMap(fn ($copy) => $copy->items)
                ->mapWithKeys(fn ($item) => [$item->id => Deliverable::keyFor($item->template_key, $item->title)]);

            $uploads = DB::table('capstone_documents')
                ->whereIn('milestone_item_id', $itemKeys->keys()->all())
                ->whereNull('deliverable_id')
                ->get(['file_id', 'milestone_item_id']);

            foreach ($uploads as $upload) {
                $key = $itemKeys[$upload->milestone_item_id];

                if (! array_key_exists($key, $ids)) {
                    continue;
                }

                $totals['uploads']++;

                if ($apply) {
                    DB::table('capstone_documents')->where('file_id', $upload->file_id)->update(['deliverable_id' => $ids[$key]]);
                }
            }
        }

        $this->table(
            ['Checklists', 'Milestones', 'Deliverables', 'Ticks to checks', 'Uploads linked'],
            [array_values($totals)],
        );

        foreach (array_unique($problems) as $problem) {
            $this->warn($problem);
        }

        $this->info($apply ? 'Done.' : 'Dry run: nothing was written. Run again with --apply to write this.');

        return self::SUCCESS;
    }

    /** The checklist, its milestones in phase order, and its deliverables, from one team's copy. */
    private function build(?Checklist $checklist, int $capsaId, string $program, Milestone $source): Checklist
    {
        $checklist ??= Checklist::create(['capsa_id' => $capsaId, 'program' => $program, 'title' => $source->title]);

        foreach ($source->items->groupBy(fn ($item) => $item->phase ?: self::OWN_PHASE)->values() as $position => $lines) {
            $milestone = $checklist->milestones()->create([
                'name' => $lines->first()->phase ?: self::OWN_PHASE,
                'position' => $position,
            ]);

            foreach ($lines->values() as $at => $item) {
                $milestone->deliverables()->create($item->only(['template_key', 'title', 'kind', 'role', 'chapter', 'defense_type']) + [
                    'due_date' => $item->dueOn(),
                    'position' => $at,
                ]);
            }
        }

        return $checklist;
    }
}
