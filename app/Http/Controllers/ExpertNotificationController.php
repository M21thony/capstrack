<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
