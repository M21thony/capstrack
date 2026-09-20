<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records a browser that has agreed to receive CapsTrack notifications.
 *
 * One row per browser per person: the same expert on a laptop and a desktop
 * is two rows, and gets the notification on both. The endpoint the browser
 * hands over is its address at Google's or Mozilla's push service - it is not
 * a secret, but it is the only thing that can reach that browser, so it is
 * stored against whoever is logged in and nobody else.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
            'p256dh' => ['required', 'string', 'max:255'],
            'auth' => ['required', 'string', 'max:255'],
        ]);

        $expert = auth('expert')->user();
        $owner = $expert
            ? ['expert_id' => $expert->expert_id, 'student_id' => null]
            : ['student_id' => $request->user()?->student_id, 'expert_id' => null];

        if (! $owner['expert_id'] && ! $owner['student_id']) {
            abort(401);
        }

        // updateOrCreate on the hash, so a browser that re-registers on every
        // visit keeps one row rather than collecting one per login.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($validated['endpoint'])],
            $owner + [
                'endpoint' => $validated['endpoint'],
                'p256dh' => $validated['p256dh'],
                'auth' => $validated['auth'],
                'created_at' => now(),
            ],
        );

        return response()->json(['success' => true]);
    }
}
