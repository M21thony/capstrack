<?php

use App\Models\Checklist;
use App\Models\StudentSchoolYear;
use App\Support\MilestoneProgress;
use App\Support\MilestoneTemplate;
use App\Support\TeamChecklist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CaPSA/TSA opens each milestone for her whole section, and a team is held
 * back only by the defense above it. Live checklists keep open every
 * milestone up to the furthest one a team has started on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('closed_at');
        });

        Checklist::query()->each(function (Checklist $checklist) {
            $teams = StudentSchoolYear::whereIn('student_id', app(TeamChecklist::class)->studentsFollowing($checklist)->pluck('student_id'))
                ->whereNotNull('team_id')->pluck('team_id');

            $reached = collect(MilestoneProgress::forTeams($checklist, $teams))
                ->flatMap(fn ($progress) => $progress->items)
                ->reject(fn ($row) => in_array($row->state, [MilestoneProgress::NOT_STARTED, MilestoneProgress::OVERDUE], true))
                ->map(fn ($row) => $row->milestone->position)
                ->max();

            foreach ($checklist->milestones()->get() as $milestone) {
                $open = in_array($milestone->name, MilestoneTemplate::OPEN_FROM_THE_START, true)
                    || $milestone->closed_at
                    || ($reached !== null && $milestone->position <= $reached);

                $milestone->forceFill([
                    'opened_at' => $open ? ($milestone->closed_at ?? now()) : null,
                    'requires_previous' => in_array($milestone->name, MilestoneTemplate::GATES, true),
                ])->save();
            }
        });
    }

    public function down(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->dropColumn('opened_at');
        });
    }
};
