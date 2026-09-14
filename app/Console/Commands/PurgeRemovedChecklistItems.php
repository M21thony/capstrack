<?php

namespace App\Console\Commands;

use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes for good the milestones and deliverables a CaPSA removed more than
 * KEEP_DAYS ago, with the checks recorded on them. Until then Undo can still
 * bring them back.
 *
 * Deleted by hand rather than by foreign key: the tables are MyISAM, which
 * ignores cascades.
 */
class PurgeRemovedChecklistItems extends Command
{
    public const KEEP_DAYS = 5;

    protected $signature = 'checklists:purge-removed';

    protected $description = 'Permanently delete checklist milestones and deliverables removed more than '.self::KEEP_DAYS.' days ago';

    public function handle(): int
    {
        $cutoff = now()->subDays(self::KEEP_DAYS);

        $milestoneIds = ChecklistMilestone::onlyTrashed()->where('deleted_at', '<', $cutoff)->pluck('id');

        $deliverableIds = Deliverable::withTrashed()
            ->where(fn ($query) => $query
                ->whereIn('checklist_milestone_id', $milestoneIds)
                ->orWhere(fn ($removed) => $removed->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoff)))
            ->pluck('id');

        DeliverableCheck::whereIn('deliverable_id', $deliverableIds)->delete();
        DB::table('capstone_documents')->whereIn('deliverable_id', $deliverableIds)->update(['deliverable_id' => null]);
        Deliverable::withTrashed()->whereIn('id', $deliverableIds)->forceDelete();
        ChecklistMilestone::withTrashed()->whereIn('id', $milestoneIds)->forceDelete();

        $this->info("Purged {$milestoneIds->count()} milestone(s) and {$deliverableIds->count()} deliverable(s).");

        return self::SUCCESS;
    }
}
