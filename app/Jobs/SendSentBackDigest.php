<?php

namespace App\Jobs;

use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * One "Document Review" email for every paper sent back to a student.
 *
 * A CaPSA/TSA checking a team's papers sends them back one at a time, a
 * minute or two apart. An email each would be three emails for one sitting,
 * so the first one sent back starts a short wait and whatever else is sent
 * back in it goes out together. Each paper still has its own bell notice.
 *
 * Unique per student until it starts, so the papers sent back during the
 * wait join this email and one sent back after it starts another.
 */
class SendSentBackDigest implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const TYPE = 'paper_sent_back';

    /** How long to wait for more papers before the email goes out. */
    public const WAIT_MINUTES = 10;

    public function __construct(public int $studentId) {}

    public function uniqueId(): string
    {
        return (string) $this->studentId;
    }

    public function handle(): void
    {
        try {
            $student = Student::find($this->studentId);

            if (! $student?->email || ! $student->wants(self::TYPE, 'email')) {
                return;
            }

            // Unread, and not in an earlier email. One they already opened
            // in CapsTrack is not news to them.
            $notices = Notification::where('student_id', $student->student_id)
                ->where('type', self::TYPE)
                ->where('is_read', false)
                ->whereNull('data->emailed_at')
                ->orderBy('id')
                ->get();

            if ($notices->isEmpty()) {
                return;
            }

            $count = $notices->count();

            Mail::to($student->email)->queue(new NotificationMail(
                $student->displayFirstName(),
                'Document Review: '.$count.' '.Str::plural('paper', $count).' sent back',
                ($count === 1 ? 'This paper was sent back for you to fix:' : 'These papers were sent back for you to fix:')."\n\n"
                    .$notices->map(fn (Notification $notice) => '• '.html_entity_decode(strip_tags((string) $notice->message), ENT_QUOTES))->implode("\n\n"),
                url($notices->first()->link ?: route('student.progress')),
            ));

            foreach ($notices as $notice) {
                $notice->update(['data' => ['emailed_at' => now()->toIso8601String()] + ($notice->data ?? [])]);
            }
        } catch (\Throwable $e) {
            Log::error('Could not email the papers sent back', ['student_id' => $this->studentId, 'error' => $e->getMessage()]);
        }
    }
}
