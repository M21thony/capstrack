<?php

namespace App\Support;

use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Where a team stands at each defense stage.
 *
 * Failing is not something a panel does on the day - there is no "rejected"
 * outcome. A team fails by running out of time: the defense window for that
 * stage closes and they still have no pass. So this is derived from the
 * window and the recorded outcomes rather than stored anywhere.
 *
 * That matters because the CAPSA extends deadlines. Moving a window's end
 * date forward has to un-fail every team it covers, and with a stored flag
 * that would mean remembering to sweep back and clear them all. Derived, an
 * extension simply takes effect.
 */
class DefenseProgress
{
    public const PASSED = 'passed';
    public const IN_PROGRESS = 'in_progress';
    public const FAILED = 'failed';
    public const NOT_SCHEDULED = 'not_scheduled';

    /** @var Collection<string, DefenseWindow>|null */
    private ?Collection $windows = null;

    /** The stages a capstone runs through, in order. */
    public const STAGES = [
        'Pre-proposal Defense',
        'Title Defense',
        'Pre Oral Defense',
        'Final Oral Defense',
    ];

    /**
     * @param  Collection<int, DefenseSchedule>|null  $schedules  Pre-loaded, to avoid a query per team in a list.
     */
    public function stageStatus(Team $team, string $stage, ?Collection $schedules = null): string
    {
        $schedules ??= DefenseSchedule::where('team_id', $team->team_id)->get();
        $attempts = $schedules->where('defense_type', $stage);

        // A pass at any attempt is a pass - that is what a redefense is for.
        if ($attempts->contains(fn ($s) => DefenseOutcome::isPass($s->outcome))) {
            return self::PASSED;
        }

        $deadlinePassed = $this->deadlineHasPassed($stage);

        if ($attempts->isEmpty()) {
            // Never scheduled. Kept separate from "failed" on purpose: a team
            // nobody scheduled has not been given the chance to pass, and
            // that is the CAPSA's to fix, not the team's.
            return self::NOT_SCHEDULED;
        }

        // Still time on the clock, or the CAPSA has extended it.
        return $deadlinePassed ? self::FAILED : self::IN_PROGRESS;
    }

    /** @return array<string, string> stage => status */
    public function allStages(Team $team, ?Collection $schedules = null): array
    {
        $schedules ??= DefenseSchedule::where('team_id', $team->team_id)->get();

        $out = [];
        foreach (self::STAGES as $stage) {
            $out[$stage] = $this->stageStatus($team, $stage, $schedules);
        }

        return $out;
    }

    /**
     * The stage a team is currently working on - the first they have not
     * passed. Null once every stage is behind them.
     */
    public function currentStage(Team $team, ?Collection $schedules = null): ?string
    {
        foreach ($this->allStages($team, $schedules) as $stage => $status) {
            if ($status !== self::PASSED) {
                return $stage;
            }
        }

        return null;
    }

    public function hasPassed(Team $team, string $stage, ?Collection $schedules = null): bool
    {
        return $this->stageStatus($team, $stage, $schedules) === self::PASSED;
    }

    public function deadlineFor(string $stage): ?Carbon
    {
        $this->windows ??= DefenseWindow::all()->keyBy('defense_type');

        $end = $this->windows->get($stage)?->end_date;

        return $end ? Carbon::parse($end)->endOfDay() : null;
    }

    /**
     * A stage with no window set has no deadline, so nobody can miss it -
     * an unset date is not the same as a date in the past.
     */
    public function deadlineHasPassed(string $stage): bool
    {
        $deadline = $this->deadlineFor($stage);

        return $deadline !== null && $deadline->isPast();
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::PASSED => 'Passed',
            self::IN_PROGRESS => 'In progress',
            self::FAILED => 'Missed the deadline',
            self::NOT_SCHEDULED => 'Not scheduled',
            default => 'Unknown',
        };
    }
}
