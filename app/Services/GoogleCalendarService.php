<?php

namespace App\Services;

use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\DB;

class GoogleCalendarService
{
    private function getClient(int $expertId): Client
    {
        $expert = DB::table('capstone_experts')
            ->where('expert_id', $expertId)
            ->first();

        if (! $expert || ! $expert->google_token) {
            throw new \Exception('Expert has not connected Google Calendar.');
        }

        $tokenData = json_decode($expert->google_token, true);

        $client = new Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken($tokenData);

        if (app()->environment('local')) {
            $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        }

        // Auto-refresh if expired
        if ($client->isAccessTokenExpired() && ! empty($tokenData['refresh_token'])) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($tokenData['refresh_token']);

            DB::table('capstone_experts')
                ->where('expert_id', $expertId)
                ->update([
                    'google_token' => json_encode(array_merge($tokenData, $newToken)),
                    'google_token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                ]);
        }

        return $client;
    }

    public function createBookingEvent(
        int $expertId,
        string $date,
        string $startTime,
        string $endTime,
        string $slotType,
        string $teamName,
        string $studentEmail,
        string $expertEmail,
        string $topic = '',
        ?array $recurrence = null
    ): string {
        $client = $this->getClient($expertId);

        $service = new Calendar($client);

        $start = Carbon::parse("{$date} {$startTime}", 'Asia/Manila')
            ->format('Y-m-d\TH:i:sP');

        $end = Carbon::parse("{$date} {$endTime}", 'Asia/Manila')
            ->format('Y-m-d\TH:i:sP');

        $event = new Event([
            'summary' => "{$slotType} — {$teamName}",
            'description' => $topic ?: "Scheduled {$slotType} session.",
            'start' => new EventDateTime([
                'dateTime' => $start,
                'timeZone' => 'GMT+08:00',
            ]),
            'end' => new EventDateTime([
                'dateTime' => $end,
                'timeZone' => 'GMT+08:00',
            ]),
            'attendees' => [
                new EventAttendee(['email' => $expertEmail]),
                new EventAttendee(['email' => $studentEmail]),
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email',  'minutes' => 1440], // 24hrs before
                    ['method' => 'email',  'minutes' => 60],   // 1hr before
                    ['method' => 'popup',  'minutes' => 30],
                ],
            ],
        ]);

        // RRULE so a weekly/biweekly slot's placeholder actually repeats on
        // Google Calendar every future occurrence, instead of being a single
        // one-off event pinned to whichever date it happened to be created on.
        if ($recurrence) {
            $event->setRecurrence($recurrence);
        }

        $created = $service->events->insert('primary', $event, [
            'sendNotifications' => true,
        ]);

        return $created->getId();
    }

    // Maps this app's own recur values to a Google Calendar RRULE. Left
    // unbounded (no UNTIL) — these slots recur indefinitely until the
    // instructor deletes them, same as how the app itself treats them.
    public static function recurrenceRuleFor(?string $recur): ?array
    {
        return match ($recur) {
            'weekly' => ['RRULE:FREQ=WEEKLY'],
            'biweekly' => ['RRULE:FREQ=WEEKLY;INTERVAL=2'],
            default => null,
        };
    }

    // One event per connected panelist's own calendar - see
    // AdminPanelAssignmentController::syncDefensePanelToGoogleCalendar()
    // for why (an unconnected panelist can't own or receive an event, so
    // each connected panelist gets their own copy via their own client).
    public function createDefensePanelEvent(
        int $organizerExpertId,
        string $date,
        string $startTime,
        string $endTime,
        string $defenseType,
        string $teamName,
        string $venue,
        array $attendeeEmails,
    ): string {
        $client = $this->getClient($organizerExpertId);

        $service = new Calendar($client);

        $start = Carbon::parse("{$date} {$startTime}", 'Asia/Manila')
            ->format('Y-m-d\TH:i:sP');

        $end = Carbon::parse("{$date} {$endTime}", 'Asia/Manila')
            ->format('Y-m-d\TH:i:sP');

        $event = new Event([
            'summary' => "{$defenseType} — {$teamName}",
            'description' => "{$defenseType} panel for {$teamName}.",
            'location' => $venue,
            'start' => new EventDateTime([
                'dateTime' => $start,
                'timeZone' => 'GMT+08:00',
            ]),
            'end' => new EventDateTime([
                'dateTime' => $end,
                'timeZone' => 'GMT+08:00',
            ]),
            'attendees' => array_map(fn ($email) => new EventAttendee(['email' => $email]), $attendeeEmails),
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 1440],
                    ['method' => 'popup', 'minutes' => 30],
                ],
            ],
        ]);

        $created = $service->events->insert('primary', $event, [
            'sendNotifications' => true,
        ]);

        return $created->getId();
    }

    /**
     * Take one date out of a recurring event, leaving the series intact.
     *
     * Moving a single week creates a separate one-off event for the new time.
     * Without this the original series still fires on that date too, so the
     * adviser's calendar shows the slot twice - once where they moved it, and
     * once where it used to be.
     *
     * EXDATE has to name the exact start instant of the occurrence being
     * removed, not just the day, so the time comes from the event itself
     * rather than from the caller.
     */
    public function excludeDateFromSeries(int $expertId, string $googleEventId, string $date): void
    {
        $service = new Calendar($this->getClient($expertId));
        $event = $service->events->get('primary', $googleEventId);

        $recurrence = $event->getRecurrence() ?: [];
        if ($recurrence === []) {
            return; // not a series - nothing to exclude it from
        }

        $startsAt = $event->getStart()->getDateTime() ?: $event->getStart()->getDate();
        $timeOfDay = Carbon::parse($startsAt, 'Asia/Manila')->format('His');
        $exdate = 'EXDATE;TZID=Asia/Manila:'
            .Carbon::parse($date, 'Asia/Manila')->format('Ymd').'T'.$timeOfDay;

        if (in_array($exdate, $recurrence, true)) {
            return; // already excluded, and pressing skip twice must be safe
        }

        $recurrence[] = $exdate;
        $event->setRecurrence($recurrence);

        $service->events->update('primary', $googleEventId, $event, [
            'sendUpdates' => 'all',
        ]);
    }

    public function cancelBookingEvent(int $expertId, string $googleEventId): void
    {
        $client = $this->getClient($expertId);
        $service = new Calendar($client);

        $service->events->delete('primary', $googleEventId, [
            'sendNotifications' => true,
        ]);
    }

    public function updateBookingEvent(int $expertId, string $googleEventId, string $teamName): void
    {
        $client = $this->getClient($expertId);
        $service = new Calendar($client);

        $event = $service->events->get('primary', $googleEventId);
        $event->setSummary($teamName . ' — Booked');
        $event->setDescription('Consultation booked by team: ' . $teamName);

        $service->events->update('primary', $googleEventId, $event, [
            'sendNotifications' => true,
        ]);
    }
}
