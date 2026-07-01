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
        string $topic = ''
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

        $created = $service->events->insert('primary', $event, [
            'sendNotifications' => true,
        ]);

        return $created->getId();
    }

    public function cancelBookingEvent(int $expertId, string $googleEventId): void
    {
        $client = $this->getClient($expertId);
        $service = new Calendar($client);

        $service->events->delete('primary', $googleEventId, [
            'sendNotifications' => true,
        ]);
    }
}
