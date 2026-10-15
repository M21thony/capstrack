<?php

namespace App\Console\Commands;

use App\Models\TeamRatingForm;
use Illuminate\Console\Command;

/**
 * The two notices a team rating sends on its own: "it's open" on the day it
 * opens, and a reminder the day before it closes to whoever has not submitted.
 * Each goes once per rating, so running this more often is harmless.
 */
class SendTeamRatingNotices extends Command
{
    protected $signature = 'team-ratings:notify';

    protected $description = 'Announce team ratings that opened today and remind students the day before one closes';

    public function handle(): int
    {
        $opened = $reminded = 0;

        foreach (TeamRatingForm::with('items.milestone.deliverables', 'items.deliverable')->whereNull('closed_at')->get() as $form) {
            if (! $form->isOpen()) {
                continue;
            }

            if (! $form->opened_notified_at) {
                $form->announceOpen();
                $opened++;
            }

            if (! $form->reminded_at && $form->dueOn()->isSameDay(today()->addDay())) {
                $reminded += $form->remind();
                $form->update(['reminded_at' => now()]);
            }
        }

        $this->info("Announced {$opened} rating(s), reminded {$reminded} student(s).");

        return self::SUCCESS;
    }
}
