<?php

namespace App\Console\Commands;

use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Services\Notifier;
use App\Support\ExpertCapacity;
use Illuminate\Console\Command;

/**
 * Tell each CaPSA when their programs have more teams needing an expert than
 * there are free expert slots - the panel's "cross-check teams and experts",
 * done on its own instead of by hand.
 *
 * Only a change is sent: the same shortage as the last notice stays quiet, so
 * a daily run does not repeat itself. The dashboard shows the live numbers.
 */
class CheckExpertCapacity extends Command
{
    public const TYPE = 'expert_slots_short';

    protected $signature = 'experts:check-capacity';

    protected $description = 'Notify each CaPSA when teams in their programs outnumber the free expert slots';

    public function handle(ExpertCapacity $capacity): int
    {
        $capsas = CapstoneExpert::where('approved_status', true)
            ->whereHas('roles', fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE))
            ->get();

        $told = 0;

        foreach ($capsas as $capsa) {
            $lines = ExpertCapacity::shortages($capacity->forPrograms($capsa->handledPrograms()));

            if ($lines === []) {
                continue;
            }

            $message = e(implode(' ', $lines));

            // Compared with the last notice only, so a shortage that clears
            // and comes back at exactly the same size is not sent again - the
            // dashboard still shows it.
            $last = Notification::where('expert_id', $capsa->expert_id)->where('type', self::TYPE)->latest('id')->value('message');

            if ($last === $message) {
                continue;
            }

            Notifier::toExpert($capsa, self::TYPE, 'Not enough expert slots', $message, route('capsa.dashboard'));
            $told++;
        }

        $this->info("Warned {$told} CaPSA(s) about expert slot shortages.");

        return self::SUCCESS;
    }
}
