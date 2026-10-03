<?php

namespace App\Services;

use App\Mail\DefenseScheduledMail;
use App\Models\Announcement;
use App\Models\DefenseSchedule;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Support\DefenseBoard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells everyone a defense date concerns, the moment it is set or moved.
 *
 * The panel already got a Google Calendar event (see
 * AdminPanelAssignmentController::syncDefensePanelToGoogleCalendar). What was
 * missing was the team hearing about it, and anyone at all hearing by email.
 * Now: the team gets a notification and an announcement on their dashboard,
 * and every member and panelist gets an email.
 *
 * Only when the date or venue actually changed. Re-saving a defense window
 * walks every schedule in it, and a team must not be told the same date twice.
 */
class DefenseScheduleNotifier
{
    public function scheduled(DefenseSchedule $schedule): void
    {
        if (! $schedule->schedule_date || ! $schedule->venue) {
            return;
        }

        if (! $schedule->wasChanged(['schedule_date', 'venue'])) {
            return;
        }

        $schedule->loadMissing('team', 'panels.expert');
        $team = $schedule->team;

        if (! $team) {
            return;
        }

        $rescheduled = ($schedule->getPrevious()['schedule_date'] ?? null) !== null;
        $when = $schedule->schedule_date->format('F j, Y \a\t g:i A');
        $headline = ($rescheduled ? 'Rescheduled: ' : '').$schedule->defense_type.' for '.$team->team_name;
        $body = $team->team_name."'s ".$schedule->defense_type
            .($rescheduled ? ' has moved to ' : ' is on ').$when.' in '.$schedule->venue.'.';

        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->unique()
            ->values();

        foreach ($memberIds as $studentId) {
            Notification::create([
                'student_id' => $studentId,
                'type' => 'defense_scheduled',
                'title' => $headline,
                'message' => e($body),
                'link' => route('student.dashboard'),
            ]);
        }

        // An announcement naming the team shows on each member's dashboard
        // whatever section it is aimed at (DashboardController).
        // Posted as whoever set the date - the CaPSA - or, when a schedule
        // is placed without anyone signed in, as the Panel Chair.
        $authorId = auth('expert')->id()
            ?? $schedule->panels->firstWhere('panel_role', 'Panel Chair')?->expert_id;

        if ($authorId) {
            Announcement::create([
                'admin_id' => $authorId,
                'title' => $headline,
                'body' => $body,
                'priority' => 'important',
                'target_audience' => ['student'],
                'team_id' => $team->team_id,
                'is_active' => true,
            ]);
        }

        $this->email(
            Student::whereIn('student_id', $memberIds)->get()
                ->filter(fn (Student $student) => $student->wants('defense_scheduled', 'email'))
                ->pluck('email'),
            $schedule, $rescheduled, false,
        );
        $this->email($schedule->panels->pluck('expert.email'), $schedule, $rescheduled, true);

        // Title, Pre Oral and Final Oral go ahead on the CaPA's word.
        if (DefenseBoard::needsCapaGo($schedule->defense_type) && $schedule->capa_answer === null) {
            Notifier::toExpert(
                app(RecipientResolver::class)->resolveTeamRoleExpert((int) $team->team_id, 'adviser'),
                'defense_scheduled',
                'Is '.$team->team_name.' ready? '.$schedule->defense_type.' on '.$schedule->schedule_date->format('M j'),
                'Give the go, or say the team is not ready and the date is cancelled.',
                route('instructor.assignments'),
            );
        }
    }

    /** Queued, and never allowed to stop the schedule itself from saving. */
    private function email(iterable $addresses, DefenseSchedule $schedule, bool $rescheduled, bool $forPanelist): void
    {
        foreach (collect($addresses)->filter()->unique() as $address) {
            try {
                Mail::to($address)->queue(new DefenseScheduledMail($schedule, $rescheduled, $forPanelist));
            } catch (\Throwable $e) {
                Log::warning('Defense schedule email failed', [
                    'defense_id' => $schedule->defense_id, 'to' => $address, 'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
