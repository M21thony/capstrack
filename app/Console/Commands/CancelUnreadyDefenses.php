<?php

namespace App\Console\Commands;

use App\Models\DefenseSchedule;
use App\Services\CapaGo;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * On the defense day, calls off every defense whose team still has open
 * requirements above it on the checklist. The team goes back under Ready to
 * schedule, as with the CaPA's "Not ready", and the lost date stays on record.
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
                    $go->cancel($schedule, 'Its requirements were not complete by the defense date', Str::limit(implode(', ', $open), 250), null);
                    $cancelled++;
                }
            });

        $this->info("Cancelled {$cancelled} defense(s).");

        return self::SUCCESS;
    }
}
