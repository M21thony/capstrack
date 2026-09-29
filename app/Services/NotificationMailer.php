<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Student;
use App\Support\NotificationTypes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the Google-mail copy of an important notification.
 *
 * Every portal goes through here - a student, an expert, a CaPSA or an admin
 * is one of the two kinds of person the bell knows - so a notification
 * cannot be important in one portal and silent by email in another.
 *
 * Like Notifier, nothing here may throw: the email is a courtesy on top of
 * whatever the person was doing, and must not take it down. Failures are
 * logged at error level so they are still noticed.
 */
class NotificationMailer
{
    /** Called when a bell row is written, whoever wrote it. */
    public static function forNotification(Notification $notification): void
    {
        $person = $notification->expert_id
            ? CapstoneExpert::find($notification->expert_id)
            : Student::find($notification->student_id);

        if ($person) {
            self::send($person, $notification->type, $notification->title, $notification->message, $notification->link);
        }
    }

    /**
     * Email $person about $type if that kind is important, they have an
     * address, and they have not switched it off.
     */
    public static function send(
        Student|CapstoneExpert $person,
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null,
    ): void {
        try {
            $audience = $person->notificationAudience();

            if (! NotificationTypes::mailedByBell($audience, $type)) {
                return;
            }

            if (! $person->email || ! $person->wants(NotificationTypes::switchFor($type), 'email')) {
                return;
            }

            self::queue($person, $title, $message, $link);
        } catch (\Throwable $e) {
            Log::error('Could not email a notification', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Email without asking the type list, for callers that have already
     * decided - an announcement, which is only mailed when it is important.
     */
    public static function sendAnnouncement(Student|CapstoneExpert $person, string $title, ?string $message, ?string $link): void
    {
        try {
            if ($person->email && $person->wants('announcement', 'email')) {
                self::queue($person, $title, $message, $link);
            }
        } catch (\Throwable $e) {
            Log::error('Could not email an announcement', ['error' => $e->getMessage()]);
        }
    }

    private static function queue(Student|CapstoneExpert $person, string $title, ?string $message, ?string $link): void
    {
        Mail::to($person->email)->queue(new NotificationMail(
            $person instanceof Student ? $person->displayFirstName() : $person->name,
            $title,
            // The bell renders HTML (<strong>team name</strong>); an email
            // shows it as plain text and lets the view do the escaping.
            $message === null ? null : html_entity_decode(strip_tags($message), ENT_QUOTES),
            $link ? url($link) : null,
        ));
    }
}
