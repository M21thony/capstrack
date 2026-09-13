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
            ->join('milestone_items as i', 'i.id', '=', 'd.milestone_item_id')
            ->join('milestones as m', 'm.id', '=', 'i.milestone_id')
            ->leftJoin('team as t', 't.team_id', '=', 'm.team_id')
            ->whereIn('r.review_status', [CapstoneDocumentReview::AWAITING, CapstoneDocumentReview::IN_PROGRESS])
            ->select('r.expert_id', 'i.title', 't.team_name', 'd.created_at as submitted_at', DB::raw('COALESCE(i.due_date, m.due_date) as due_on'))
            ->get();

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
