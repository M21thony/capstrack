<?php

namespace App\Services;

use App\Jobs\SendWebPush;
use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

/**
 * The one way CapsTrack tells somebody something on the web.
 *
 * Three things happen per call: a row goes in the notifications table (what the
 * bell shows), a desktop notification is pushed to whatever browsers that
 * person has allowed, and - for the important kinds - a copy is emailed
 * (NotificationMailer). Each is skipped if they have turned it off.
 *
 * Nothing here is allowed to throw. A notification is a courtesy on top of
 * whatever the student was actually doing - booking a consultation, sending a
 * document - and a courtesy that fails must not take the booking down with
 * it. Failures are logged at error level so they are still noticed.
 */
class Notifier
{
    public static function toExpert(
        CapstoneExpert|int|null $expert,
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null,
        array $data = [],
    ): void {
        $expert = is_int($expert) ? CapstoneExpert::find($expert) : $expert;

        if ($expert) {
            self::write($expert, ['expert_id' => $expert->expert_id], $type, $title, $message, $link, $data);
        }
    }

    public static function toStudent(
        Student|int|null $student,
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null,
        array $data = [],
    ): void {
        $student = is_int($student) ? Student::find($student) : $student;

        if ($student) {
            self::write($student, ['student_id' => $student->student_id], $type, $title, $message, $link, $data);
        }
    }

    /** Every member of a team hears the same thing - a review, a deadline. */
    public static function toStudents(iterable $students, string $type, string $title, ?string $message = null, ?string $link = null, array $data = []): void
    {
        foreach ($students as $student) {
            self::toStudent($student, $type, $title, $message, $link, $data);
        }
    }

    private static function write(
        Student|CapstoneExpert $person,
        array $owner,
        string $type,
        string $title,
        ?string $message,
        ?string $link,
        array $data,
    ): void {
        try {
            // Bell and email are separate switches. Writing the row is what
            // sends the email (see Notification::booted), so when the bell is
            // off the email has to be sent here or it would be lost with it.
            // An expert's bell gets everything: their profile only lets them
            // choose which emails they get, so a bell switched off on an
            // older page cannot hide a team's application from them.
            if ($person instanceof Student && ! $person->wants($type, 'web')) {
                NotificationMailer::send($person, $type, $title, $message, $link);

                return;
            }

            $notification = Notification::create($owner + [
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'data' => $data ?: null,
                'is_read' => false,
                'created_at' => now(),
            ]);

            SendWebPush::dispatch($notification->id);
        } catch (\Throwable $e) {
            Log::error('Could not record a notification', [
                'type' => $type,
                'owner' => $owner,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
