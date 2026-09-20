<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\PushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Pushes one notification out to every browser its owner has allowed.
 *
 * Queued, because this is an HTTPS round trip to Google's or Mozilla's push
 * service per browser and the student who triggered it is sitting waiting for
 * a page. Run `php artisan queue:work` or the desktop notifications simply
 * queue up and never arrive - the bell still fills in either way.
 */
class SendWebPush implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $notificationId) {}

    public function handle(): void
    {
        $vapid = [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ];

        // No keys means push was never set up. That is a normal state - the
        // bell and the emails do not depend on it - so it is not an error.
        if (! $vapid['publicKey'] || ! $vapid['privateKey']) {
            return;
        }

        $notification = Notification::find($this->notificationId);

        if (! $notification) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->when($notification->expert_id, fn ($q) => $q->where('expert_id', $notification->expert_id))
            ->when($notification->student_id, fn ($q) => $q->where('student_id', $notification->student_id))
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode([
            'title' => $notification->title,
            'body' => $notification->message ?? '',
            'url' => $notification->link ?? '/',
            // Same tag means a second notification about the same thing
            // replaces the first on screen rather than stacking up.
            'tag' => $notification->type.':'.($notification->data['tag'] ?? $notification->id),
            'id' => $notification->id,
        ]);

        $webPush = new WebPush(['VAPID' => $vapid]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => ['p256dh' => $subscription->p256dh, 'auth' => $subscription->auth],
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            // The browser has been uninstalled, cleared, or the person
            // revoked permission. The row is dead weight and will fail
            // forever, so drop it rather than retrying it every time.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', PushSubscription::hash($report->getEndpoint()))->delete();

                continue;
            }

            Log::warning('Web push rejected', [
                'notification' => $notification->id,
                'reason' => $report->getReason(),
            ]);
        }
    }
}
