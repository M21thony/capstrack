<?php

use App\Models\Checklist;
use App\Support\MilestoneTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Checklists with Capstone 2 from before the Archive Entry existed end at the
 * final manuscript. Give each one the Archive Entry milestone below it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Checklist::query()->each(fn (Checklist $checklist) => MilestoneTemplate::appendArchiveEntry($checklist));
    }

    /** Entries a team may already have sent are not taken away. */
    public function down(): void {}
};
