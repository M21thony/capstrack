<?php

namespace App\Console\Commands;

use App\Mail\ReviewReminderMail;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Every day, each reviewer with checklist documents still waiting on them
 * gets one email listing all of them, overdue and soonest first.
 *
 * One email per reviewer, not per document: an adviser with six teams would
 * otherwise get six emails a day and stop reading any of them. Only uploads
 * sent for a checklist line are chased - a file sent for feedback has no
 * deadline for anyone to be reminded of.
 */
class SendReviewReminders extends Command
{
    protected $signature = 'milestones:send-review-reminders';

    protected $description = 'Email each reviewer one list of the checklist documents still waiting for their review';

    public function handle(): int
    {
        $waiting = DB::table('capstone_document_review as r')
            ->join('capstone_documents as d', 'd.file_id', '=', 'r.file_id')
            ->join('deliverables as i', 'i.id', '=', 'd.deliverable_id')
            ->join('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->leftJoin('team as t', 't.team_id', '=', 'sy.team_id')
            ->whereNull('i.deleted_at')
            ->whereIn('r.review_status', [CapstoneDocumentReview::AWAITING, CapstoneDocumentReview::IN_PROGRESS])
            ->select('r.review_id', 'r.expert_id', 'i.title', 't.team_name', 'd.created_at as submitted_at', 'i.due_date as due_on')
            ->get()
            ->unique('review_id');

        $sent = 0;

        foreach ($waiting->groupBy('expert_id') as $expertId => $documents) {
            $expert = CapstoneExpert::find($expertId);

            if (! $expert?->email) {
                continue;
            }

            $documents = $documents->sortBy(fn ($d) => $d->due_on ?? '9999-12-31')->values();

            try {
                Mail::to($expert->email)->queue(new ReviewReminderMail($expert, $documents));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Review reminder email failed', ['expert_id' => $expertId, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Queued {$sent} review reminder email(s).");

        return self::SUCCESS;
    }
}
