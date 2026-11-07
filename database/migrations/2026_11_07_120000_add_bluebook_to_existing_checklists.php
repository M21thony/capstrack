<?php

use App\Models\Checklist;
use App\Support\MilestoneTemplate;
use Illuminate\Database\Migrations\Migration;

/** Give every Capstone 2 checklist the Bluebook Clearance milestone at the bottom. Rows only, no new table. */
return new class extends Migration
{
    public function up(): void
    {
        Checklist::query()->each(fn (Checklist $checklist) => MilestoneTemplate::appendBluebook($checklist));
    }

    /** Files a team may already have sent are not taken away. */
    public function down(): void {}
};
