<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The same bell, for the other half of CapsTrack.
 *
 * Experts log in on their own guard, so "who is this" is answered differently
 * - but listing, counting and marking read are identical, and stay inherited
 * rather than copied.
 */
class ExpertNotificationController extends NotificationController
{
    protected function ownerColumn(): string
    {
        return 'expert_id';
    }

    protected function person(Request $request)
    {
        return auth('expert')->user();
    }

    protected function ownerId(Request $request): int
    {
        return (int) $this->person($request)->expert_id;
    }

    /** See all notifications: everything the bell's latest 15 leaves out. */
    public function page(Request $request): View
    {
        return view('instructor.notifications', [
            'notifications' => Notification::where('expert_id', $this->ownerId($request))
                ->latest('created_at')
                ->paginate(20),
        ]);
    }
}
