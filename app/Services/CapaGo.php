<?php

namespace App\Services;

use App\Models\CapstoneExpert;
use App\Models\DefenseCancellation;
use App\Models\DefenseSchedule;
use App\Models\StudentSchoolYear;
use App\Support\DefenseBoard;
use App\Support\DefenseOutcome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The CaPA's go for a Title, Pre Oral or Final Oral Defense.
 *
 * The team's adviser (CaPA) says whether the team is ready for the date it
 * was given. "Go" is kept on the defense. "Not ready" calls the date off: the
 * date it lost is kept in defense_cancellations, the slot is freed, and the
 * team waits under Ready to schedule for its CaPSA to give it a new one.
 * The CaPSA may record an answer the CaPA gave in person. On the defense day,
 * a team whose requirements are still open is cancelled too, but that one
 * keeps its date (see cancelOnTheDay()).
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
            $schedule->schedule_date->isPast() => 'This defense has already started.',
            default => null,
        };
    }

    public function answer(DefenseSchedule $schedule, string $answer, ?string $note, CapstoneExpert $by): void
    {
        if ($answer === self::GO) {
            $schedule->update(['capa_answer' => DefenseSchedule::CAPA_GO, 'capa_answered_at' => now(), 'capa_answered_by' => $by->expert_id]);

            return;
        }

        $this->cancel($schedule, 'The CaPA said the team is not ready yet', $note, $by->expert_id);
    }

    /** Call the date off for "Not ready" and put the team back under Ready to schedule. */
    public function cancel(DefenseSchedule $schedule, string $reason, ?string $note, ?int $cancelledBy): void
    {
        $schedule->loadMissing('panels.expert', 'team');
        $was = $schedule->schedule_date->copy();

        DefenseCancellation::create([
            'defense_id' => $schedule->defense_id,
            'team_id' => $schedule->team_id,
            'defense_type' => $schedule->defense_type,
            'was_scheduled_at' => $was,
            'venue' => $schedule->venue,
            'note' => $note ? trim($note) : null,
            'cancelled_by' => $cancelledBy,
        ]);

        $this->freeCalendars($schedule);
        $schedule->update(['schedule_date' => null, 'end_date' => null, 'venue' => null, 'capa_answer' => null, 'capa_answered_at' => null, 'capa_answered_by' => null]);
        $this->notifyCancelled($schedule, $was, $reason, $note);
    }

    /**
     * Cancel a defense on its own day because its requirements are still
     * open. Unlike "Not ready" it keeps its date, venue and panel, with the
     * status Cancelled, as the department's sheet does; the team's next try
     * opens with no date, under Ready to schedule, for the CaPSA to give one.
     */
    public function cancelOnTheDay(DefenseSchedule $schedule, string $open): void
    {
        $schedule->loadMissing('panels.expert', 'team');
        $schedule->update(['outcome' => DefenseOutcome::CANCELLED, 'outcome_remarks' => $open, 'outcome_recorded_at' => now()]);

        $this->freeCalendars($schedule);
        app(DefenseOutcomeRecorder::class)->openNextAttempt($schedule);
        $this->notifyCancelled($schedule, $schedule->schedule_date, 'Its requirements were not complete by the defense date', $open);
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

    private function notifyCancelled(DefenseSchedule $schedule, Carbon $was, string $reason, ?string $note): void
    {
        $team = $schedule->team?->team_name ?? 'The team';
        $title = $schedule->defense_type.' cancelled: '.$team;
        $message = $team.'\'s '.$schedule->defense_type.' on '.$was->format('M j, g:i A').' is cancelled. '.$reason
            .($note ? ' ('.trim($note).')' : '').'. The CaPSA will set a new date.';

        Notifier::toStudents(
            StudentSchoolYear::where('team_id', $schedule->team_id)->whereNotNull('student_id')->pluck('student_id')->unique()->map(fn ($id) => (int) $id),
            'defense_scheduled', $title, $message, route('student.dashboard'),
        );

        foreach ($schedule->panels as $panel) {
            Notifier::toExpert((int) $panel->expert_id, 'defense_scheduled', $title, $message, route('instructor.grading'));
        }
    }
}
