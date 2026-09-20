<?php

namespace App\Models\Concerns;

use App\Support\NotificationTypes;

/**
 * One person's notification switches.
 *
 * Stored as a small JSON map of only the switches that are OFF, so an account
 * that has never opened the settings holds null and gets everything - which
 * is what every account did before this column existed. Nobody's experience
 * changes until they deliberately turn something off.
 */
trait HasNotificationPrefs
{
    public function initializeHasNotificationPrefs(): void
    {
        $this->mergeCasts(['notification_prefs' => 'array']);
    }

    /**
     * Does this person want to hear about $type on $channel?
     *
     * $channel is 'web' (the desktop notification and the bell) or 'email'.
     */
    public function wants(string $type, string $channel = 'web'): bool
    {
        $prefs = $this->notification_prefs ?? [];

        if (($prefs['all'] ?? true) === false) {
            return false;
        }

        return ($prefs[$channel.'.'.$type] ?? true) !== false;
    }

    /**
     * Save the switches, keeping only the OFFs.
     *
     * $on is what the form ticked: ['web.consultation_booked' => true, ...].
     * Anything absent from $on is off.
     */
    public function saveNotificationPrefs(array $on, bool $all = true): void
    {
        $prefs = $all ? [] : ['all' => false];

        foreach (array_keys(NotificationTypes::for($this->notificationAudience())) as $type) {
            foreach (['web', 'email'] as $channel) {
                $key = $channel.'.'.$type;
                if (! ($on[$key] ?? false)) {
                    $prefs[$key] = false;
                }
            }
        }

        $this->notification_prefs = $prefs;
        $this->save();
    }

    abstract public function notificationAudience(): string;
}
