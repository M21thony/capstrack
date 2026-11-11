<?php

namespace App\Services;

use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\StudentSchoolYear;
use App\Support\DefenseBoard;
use App\Support\DefenseOutcome;
use Illuminate\Support\Facades\Log;

/**
 * The CaPA's go for a Title, Pre Oral or Final Oral Defense.
 *
 * The team's adviser (CaPA) says whether the team is ready for the date it
 * was given. "Go" is kept on the defense. "Not ready" cancels it (see
 * cancel()). The CaPSA/TSA may record an answer the CaPA gave in person. Either
 * may answer any time before or on the defense day; nothing cancels by itself.
 */
class CapaGo
{
    public const GO = 'go';

    public const NOT_READY = 'not_ready';

    public function __construct(private GoogleCalendarService $calendar, private RecipientResolver $resolver) {}

    /** The team's adviser, who gives the go. */
    public function capaOf(DefenseSchedule $schedule): ?int
    {
        return $this->resolver->resolveTeamRoleExpert((int) $schedule->team_id, 'adviser');
    }

    /** Why this defense can't be answered now, or null when it can. */
    public function cannotAnswer(DefenseSchedule $schedule): ?string
    {
        return match (true) {
            ! DefenseBoard::needsCapaGo($schedule->defense_type) => 'The '.$schedule->defense_type.' needs no go from the CaPA.',
            ! $schedule->schedule_date => 'This defense has no date to say go to.',
            $schedule->outcome !== null => 'This defense has already been decided.',
            $schedule->schedule_date->copy()->endOfDay()->isPast() => 'The day of this defense has passed.',
            default => null,
        };
    }

    public function answer(DefenseSchedule $schedule, string $answer, ?string $note, CapstoneExpert $by): void
    {
        if ($answer === self::GO) {
            $schedule->update(['capa_answer' => DefenseSchedule::CAPA_GO, 'capa_answered_at' => now(), 'capa_answered_by' => $by->expert_id]);

            return;
        }

        $this->cancel($schedule, 'The CaPA said the team is not ready yet', $note ? trim($note) : null, $by->expert_id);
    }

    /**
     * Cancel the defense. It keeps its date, venue and panel with the status
     * Cancelled, as the department's sheet does; the team's next try opens
     * with no date, under Ready to schedule, for the CaPSA/TSA to give one.
     * $by is whoever said "Not ready" (null on cancels the system made before
     * the CaPA alone decided).
     */
    public function cancel(DefenseSchedule $schedule, string $reason, ?string $note, ?int $by = null): void
    {
        $schedule->loadMissing('panels.expert', 'team');
        $schedule->update(['outcome' => DefenseOutcome::CANCELLED, 'outcome_remarks' => $note, 'outcome_recorded_by' => $by, 'outcome_recorded_at' => now()]);

        $this->freeCalendars($schedule);
        app(DefenseOutcomeRecorder::class)->openNextAttempt($schedule);
        $this->notifyCancelled($schedule, $reason, $note);
    }

    /** Take the defense off the panelists' Google Calendars. */
    private function freeCalendars(DefenseSchedule $schedule): void
    {
        foreach ($schedule->panels->filter(fn ($p) => $p->google_event_id) as $panel) {
            try {
                $this->calendar->cancelBookingEvent($panel->expert_id, $panel->google_event_id);
            } catch (\Throwable $e) {
                Log::warning('Defense panel Google Calendar cancel failed', ['defense_id' => $schedule->defense_id, 'expert_id' => $panel->expert_id, 'error' => $e->getMessage()]);
            }
            $panel->update(['google_event_id' => null]);
        }
    }

    private function notifyCancelled(DefenseSchedule $schedule, string $reason, ?string $note): void
    {
        $team = $schedule->team?->team_name ?? 'The team';
        $title = $schedule->defense_type.' cancelled: '.$team;
        $message = $team.'\'s '.$schedule->defense_type.' on '.$schedule->schedule_date->format('M j, g:i A').' is cancelled. '.$reason
            .($note ? ' ('.$note.')' : '').'. The CaPSA/TSA will set a new date.';

        Notifier::toStudents(
            StudentSchoolYear::where('team_id', $schedule->team_id)->whereNotNull('student_id')->pluck('student_id')->unique()->map(fn ($id) => (int) $id),
            'defense_scheduled', $title, $message, route('student.dashboard'),
        );

        foreach ($schedule->panels as $panel) {
            Notifier::toExpert((int) $panel->expert_id, 'defense_scheduled', $title, $message, route('instructor.grading'));
        }
    }
}
