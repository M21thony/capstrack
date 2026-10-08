<?php

namespace App\Console\Commands;

use App\Models\DefenseSchedule;
use App\Services\CapaGo;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * On the defense day, marks Cancelled every defense whose team still has
 * open requirements above it on the checklist. The defense keeps its date,
 * venue and panel; the team's next try waits under Ready to schedule.
 *
 * Only today's defenses: one already past with no outcome recorded was most
 * likely held, and its result just not entered yet.
 */
class CancelUnreadyDefenses extends Command
{
    protected $signature = 'defenses:cancel-unready';

    protected $description = 'Cancel today\'s defenses whose teams still have open requirements';

    public function handle(CapaGo $go): int
    {
        $cancelled = 0;

        DefenseSchedule::with('team')
            ->whereNull('outcome')
            ->whereDate('schedule_date', today())
            ->get()
            ->each(function (DefenseSchedule $schedule) use ($go, &$cancelled) {
                $open = $schedule->team?->checklistBlockersFor($schedule->defense_type) ?? [];

                if ($open) {
                    $go->cancelOnTheDay($schedule, Str::limit(implode(', ', $open), 250));
                    $cancelled++;
                }
            });

        $this->info("Cancelled {$cancelled} defense(s).");

        return self::SUCCESS;
    }
}
