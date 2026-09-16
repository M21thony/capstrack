<?php

namespace App\Console\Commands;

use App\Models\CapstoneExpert;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Take open consultation slots off experts' Google Calendars.
 *
 * Slots used to be pushed to the calendar as "Available Slot" placeholders.
 * Only booked consultations belong there now, so the placeholders left behind
 * by the old behaviour are deleted and the slot stops pointing at them.
 *
 *   php artisan consultations:clear-slot-events           (says what it would do)
 *   php artisan consultations:clear-slot-events --force   (actually deletes)
 */
class ClearConsultationSlotEvents extends Command
{
    protected $signature = 'consultations:clear-slot-events {--force : Delete the events instead of only listing them}';

    protected $description = "Remove leftover open-slot placeholder events from experts' Google Calendars";

    public function handle(GoogleCalendarService $calendar): int
    {
        $slots = DB::table('consultation_schedule')->whereNotNull('google_event_id')->get();

        if ($slots->isEmpty()) {
            $this->info('No open slots are left on any Google Calendar.');

            return self::SUCCESS;
        }

        $experts = CapstoneExpert::whereIn('expert_id', $slots->pluck('expert_id')->unique())->get()->keyBy('expert_id');

        if (! $this->option('force')) {
            foreach ($slots->groupBy('expert_id') as $expertId => $theirs) {
                $expert = $experts[$expertId] ?? null;
                $this->line(sprintf(
                    '%-28s %2d event(s)%s',
                    $expert?->name ?? "Expert #{$expertId}",
                    $theirs->count(),
                    $expert?->google_token ? '' : '  (calendar not connected - the link is only cleared)'
                ));
            }

            $this->newLine();
            $this->info($slots->count().' placeholder event(s) would be removed. Run again with --force to do it.');

            return self::SUCCESS;
        }

        $deleted = $failed = 0;

        foreach ($slots as $slot) {
            $expert = $experts[$slot->expert_id] ?? null;
            $clear = true;

            if ($expert?->google_token) {
                try {
                    $calendar->cancelBookingEvent((int) $slot->expert_id, $slot->google_event_id);
                    $deleted++;
                } catch (\Throwable $e) {
                    // The event is still on their calendar, so the link stays:
                    // clearing it would strand the event with nothing pointing
                    // at it. Reconnect the calendar and run this again.
                    $failed++;
                    $clear = false;
                    $this->warn("Could not delete event for slot {$slot->schedule_id}: ".$e->getMessage());
                }
            }

            if ($clear) {
                DB::table('consultation_schedule')->where('schedule_id', $slot->schedule_id)->update(['google_event_id' => null]);
            }
        }

        $this->info("Removed {$deleted} event(s) from Google Calendar".($failed ? ", {$failed} could not be deleted" : '').'.');

        return self::SUCCESS;
    }
}
