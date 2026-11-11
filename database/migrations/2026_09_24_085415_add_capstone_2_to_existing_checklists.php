<?php

use App\Models\Checklist;
use App\Support\MilestoneTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Checklists made before Capstone 2 existed end at Title Defense. Carry each
 * one on with the Capstone 2 milestones, below whatever the CaPSA/TSA has, so a
 * team's checklist runs through to Final Oral Defense.
 */
return new class extends Migration
{
    public function up(): void
    {
        Checklist::query()->each(fn (Checklist $checklist) => MilestoneTemplate::appendCapstone2($checklist));
    }

    /** Lines a team may already have handed in against are not taken away. */
    public function down(): void {}
};
