<?php

namespace App\Console\Commands;

use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\Notifier;
use App\Support\MilestoneProgress;
use App\Support\TeamChecklist;
use Illuminate\Console\Command;

/**
 * Warn a team a few days before a checklist deadline they have not met.
 *
 * One notification per team, not per deliverable: a team with three things
 * due the same day would otherwise get three, and the point of this was to
 * be less noisy than email, not more.
 *
 * Only the web channel. There is deliberately no email for this - it is the
 * one event the user asked for that never had one, and adding one would be
 * the spam they were trying to get away from.
 *
 * A milestone the CaPSA closed sends nothing: it is over, whatever is left.
 */
class SendMilestoneReminders extends Command
{
    protected $signature = 'milestones:send-due-reminders {--days=3}';

    protected $description = 'Notify each team about checklist deadlines a few days out that they have not met yet';

    public function handle(TeamChecklist $checklists): int
    {
        $days = max(0, (int) $this->option('days'));
        $target = today()->addDays($days);

        $teamIds = StudentSchoolYear::whereNotNull('team_id')->distinct()->pluck('team_id');
        $teams = Team::whereIn('team_id', $teamIds)->get();

        // Grouped by checklist so the progress pass runs once per section
        // instead of once per team - it reads uploads, reviews, defenses and
        // CaPSA checks, which is not a query to repeat forty times.
        $groups = [];

        foreach ($teams as $team) {
            $checklist = $checklists->for($team);

            if ($checklist) {
                $groups[$checklist->id]['checklist'] = $checklist;
                $groups[$checklist->id]['teams'][] = $team;
            }
        }

        $told = 0;

        foreach ($groups as $group) {
            $byTeam = MilestoneProgress::forTeams(
                $group['checklist'],
                collect($group['teams'])->pluck('team_id'),
            );

            foreach ($group['teams'] as $team) {
                $progress = $byTeam[(int) $team->team_id] ?? null;

                if (! $progress) {
                    continue;
                }

                $due = $progress->items->filter(
                    fn ($row) => $row->due_on
                        && ! $row->milestone->isClosed()
                        && $row->milestone->isOpened()
                        && $row->due_on->isSameDay($target)
                        && ! in_array($row->state, MilestoneProgress::COMPLETE_STATES, true)
                )->values();

                if ($due->isEmpty()) {
                    continue;
                }

                $titles = $due->map(fn ($row) => $row->item->title);
                $what = $due->count() === 1
                    ? '<strong>'.e($titles->first()).'</strong>'
                    : '<strong>'.$due->count().' items</strong>: '.e($titles->join(', '));

                Notifier::toStudents(
                    $team->members,
                    'milestone_due',
                    $days === 0 ? 'Due today' : 'Due in '.$days.' day'.($days === 1 ? '' : 's'),
                    $what.' '.($days === 0 ? 'is due today' : 'is due on '.$target->format('D, j M Y')).'.',
                    route('student.progress'),
                    ['team_id' => $team->team_id],
                );

                $told++;
            }
        }

        $this->info("Warned {$told} team(s) about deadlines on {$target->toDateString()}.");

        return self::SUCCESS;
    }
}
