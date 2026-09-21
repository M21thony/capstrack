<?php

namespace App\Console\Commands;

use App\Models\CapstoneExpert;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Take open consultation slots off experts' Google Calendars.
 *
 * Slots used to be pushed to the calendar as "Available Slot" placeholders,
 * repeating weekly with no end and emailing the expert a day and an hour
 * before every occurrence. Only booked consultations belong there now, so
 * the placeholders left behind by the old behaviour are deleted.
 *
 * Two kinds are cleaned up. A slot row that still names its event is deleted
 * and the name cleared. The rest are orphans - the row no longer names the
 * event, so the only way to find them is to search the calendar itself - and
 * those are the ones still arriving as mail, because nothing could see them
 * to clear them.
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
        $force = (bool) $this->option('force');
        $slots = DB::table('consultation_schedule')->whereNotNull('google_event_id')->get();

        $experts = CapstoneExpert::whereNotNull('google_token')
            ->orWhereIn('expert_id', $slots->pluck('expert_id')->unique())
            ->get()->keyBy('expert_id');

        $deleted = $failed = 0;

        // 1. The ones the database still points at.
        foreach ($slots as $slot) {
            $expert = $experts[$slot->expert_id] ?? null;

            if (! $force) {
                continue;
            }

            $clear = true;

            if ($expert?->google_token) {
                try {
                    $calendar->deleteEventQuietly((int) $slot->expert_id, $slot->google_event_id);
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

        if (! $force && $slots->isNotEmpty()) {
            foreach ($slots->groupBy('expert_id') as $expertId => $theirs) {
                $expert = $experts[$expertId] ?? null;
                $this->line(sprintf(
                    '%-28s %2d linked event(s)%s',
                    $expert?->name ?? "Expert #{$expertId}",
                    $theirs->count(),
                    $expert?->google_token ? '' : '  (calendar not connected - the link is only cleared)'
                ));
            }
        }

        // 2. The orphans, found by searching each connected calendar.
        $orphans = 0;

        foreach ($experts->filter(fn ($e) => $e->google_token) as $expertId => $expert) {
            try {
                $placeholders = $calendar->findSlotPlaceholders((int) $expertId);
            } catch (\Throwable $e) {
                $this->warn(($expert->name ?? "Expert #{$expertId}").": could not read the calendar - ".$e->getMessage());

                continue;
            }

            if ($placeholders === []) {
                continue;
            }

            $orphans += count($placeholders);

            if (! $force) {
                $this->line(sprintf('%-28s %2d placeholder(s) still on the calendar', $expert->name ?? "Expert #{$expertId}", count($placeholders)));

                foreach ($placeholders as $summary) {
                    $this->line('      '.$summary);
                }

                continue;
            }

            foreach (array_keys($placeholders) as $eventId) {
                try {
                    $calendar->deleteEventQuietly((int) $expertId, $eventId);
                    $deleted++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn(($expert->name ?? "Expert #{$expertId}").": could not delete {$eventId} - ".$e->getMessage());
                }
            }
        }

        $total = $slots->count() + $orphans;

        if ($total === 0) {
            $this->info('No open slots are left on any Google Calendar.');

            return self::SUCCESS;
        }

        if (! $force) {
            $this->newLine();
            $this->info($total.' placeholder event(s) would be removed. Run again with --force to do it.');

            return self::SUCCESS;
        }

        $this->info("Removed {$deleted} event(s) from Google Calendar".($failed ? ", {$failed} could not be deleted" : '').'.');

        return self::SUCCESS;
    }
}
