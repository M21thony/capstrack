<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\DefenseCancellation;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Defense Scheduling page's summary: what CCS has always kept as a
 * spreadsheet per stage (see the sample "TD_2526 - Schedule - CCS.xlsx"),
 * built from the same records Defense Scheduling and Panel Assignment read.
 *
 * One row-group per team, in the sample's own shape: a block of rows - one
 * per member - with the team-level facts (section, schedule, venue, status,
 * title, similarity) merged down the whole block, and the three expert and
 * three panel roles placed on its first three rows regardless of how many
 * members there are. A team with fewer than three members still gets three
 * rows, so every role has one to sit on; a team with more gets extra rows
 * that carry only a member name.
 *
 * The web page and the spreadsheet both read this - one build, so they
 * cannot disagree about who is going where.
 */
class DefenseScheduleExport
{
    /** Rows are at least this tall, so the three expert and panel roles always have a place. */
    private const MIN_ROWS = 3;

    public function __construct(private DefenseBoard $board, private DefenseProgress $progress) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(CapstoneExpert $capsa, string $stage): array
    {
        ['teams' => $teams, 'sections' => $sections] = $this->board->teamsFor($capsa);

        if ($teams->isEmpty()) {
            return [];
        }

        // Not part of DefenseBoard::teamsFor()'s own eager loads - those
        // serve every caller, and only a schedule export reads a similarity
        // score. teamsFor() hands back a plain Collection (it is built with
        // collect()->push(), not a query), so it has no load() of its own;
        // wrapping the same Team instances in an Eloquent one gives it one.
        EloquentCollection::make($teams->all())->load('selectedPreProposal.duplicationCheck');

        $experts = $this->expertsFor($teams->pluck('team_id'));
        $paidStudentIds = in_array($stage, DefenseBoard::PAID_STAGES, true) ? $this->paidStudentIds($teams, $stage) : null;
        $deadline = $this->progress->deadlineFor($stage);
        $cancelled = DefenseCancellation::whereIn('team_id', $teams->pluck('team_id'))
            ->where('defense_type', $stage)
            ->orderByDesc('was_scheduled_at')
            ->get()
            ->groupBy('defense_id');

        return $teams->flatMap(function (Team $team) use ($stage, $sections, $experts, $paidStudentIds, $deadline, $cancelled) {
            $attempts = $team->defenseSchedules->where('defense_type', $stage)->sortBy('attempt');
            $schedule = $attempts->last();
            $lastCancelled = $schedule && ! $schedule->schedule_date ? $cancelled->get($schedule->defense_id)?->first() : null;
            $earlier = $schedule && ! $schedule->schedule_date && $schedule->attempt > 1
                ? $team->defenseSchedules->where('defense_type', $stage)->where('attempt', $schedule->attempt - 1)->first()
                : null;
            $panel = $schedule ? $this->board->panelOf($schedule) : null;
            $panelNames = $panel ? DefenseBoard::names([['panel' => $panel]]) : [];

            $members = $team->schoolYears
                ->map(fn (StudentSchoolYear $sy) => $sy->student)
                ->filter()
                ->map(fn ($student) => [
                    'name' => $this->studentName($student),
                    'paid' => $paidStudentIds === null ? null : $paidStudentIds->contains($student->student_id),
                    'returnee' => (bool) $student->is_returnee,
                ])
                ->values();

            $row = [
                'id' => (int) $team->team_id,
                'section' => $sections[$team->team_id],
                'alias' => $team->team_name,
                'title' => $team->project_title,
                'schedule' => $schedule?->schedule_date ? [
                    'date' => $schedule->schedule_date,
                    'end' => DefenseBoard::endOf($schedule),
                ] : null,
                'venue' => $schedule?->venue,
                'status' => $this->statusOf($schedule, $stage, $deadline, $lastCancelled, $earlier),
                // What a team waiting for a date lost, for the Schedule column.
                'note' => match (true) {
                    $lastCancelled !== null => 'Was '.$lastCancelled->was_scheduled_at->format('M j, g:i A').'. CaPA: not ready'.($lastCancelled->note ? ' ('.$lastCancelled->note.')' : '').'.',
                    $earlier?->schedule_date !== null => 'Defended '.$earlier->schedule_date->format('M j').'.',
                    default => null,
                },
                'members' => $members,
                'experts' => $experts->get($team->team_id, collect(['adviser' => null, 'consultant' => null, 'grammarian' => null])),
                'panel' => [
                    'chair' => $panel && $panel['chair'] ? $panelNames[$panel['chair']] ?? null : null,
                    'm1' => $panel && $panel['m1'] ? $panelNames[$panel['m1']] ?? null : null,
                    'm2' => $panel && $panel['m2'] ? $panelNames[$panel['m2']] ?? null : null,
                ],
                'similarity' => $team->selectedPreProposal?->duplicationCheck?->top_score,
                'row_count' => max(self::MIN_ROWS, $members->count()),
            ];

            // A defense cancelled on the day keeps its own row on its own
            // date, as the department's sheet does ("Cancelled - Resched Oct 14").
            $cancelledOnTheDay = $attempts
                ->filter(fn ($s) => $s->outcome === DefenseOutcome::CANCELLED && $s->schedule_date)
                ->map(function ($s) use ($row, $attempts) {
                    $next = $attempts->firstWhere('attempt', $s->attempt + 1)?->schedule_date;

                    return array_merge($row, [
                        'schedule' => ['date' => $s->schedule_date, 'end' => DefenseBoard::endOf($s)],
                        'venue' => $s->venue,
                        'status' => ['key' => 'cancelled', 'label' => 'CANCELLED'.($next ? ' · RESCHED '.mb_strtoupper($next->format('M j')) : ''), 'tone' => 'fail'],
                        'note' => null,
                    ]);
                })->values();

            // Still waiting for its new date: the cancelled row already says so.
            return $earlier?->outcome === DefenseOutcome::CANCELLED && ! $lastCancelled ? $cancelledOnTheDay : $cancelledOnTheDay->push($row);
        })->sortBy(fn (array $row) => ($row['schedule'] !== null ? $row['schedule']['date']->toDateTimeString() : '9999').'|'.$row['alias'])
            ->values()->all();
    }

    /**
     * Where a team stands, for the STATUS column - and the colour a badge or
     * a spreadsheet cell should carry with it.
     *
     * Not the same question DefenseAnalytics asks (that one is "has this
     * stage been decided, across every attempt"); this is "what is this one
     * scheduled defense doing right now", which is what a schedule reads.
     *
     * @return array{key: string, label: string, tone: string}
     */
    private function statusOf(?object $schedule, string $stage, ?Carbon $deadline, ?DefenseCancellation $cancelled = null, ?object $earlier = null): array
    {
        // All caps, matching the sample sheet's own STATUS column (PASSED,
        // CANCELLED, REDEFENSE...). The app's other badges for the same
        // outcome (DefenseBoard::rows(), the Scheduled tab) stay in the
        // title case the rest of the UI uses; only this export follows the
        // department's printed convention.
        // The CaPA did not give the go: the team waits for a new date.
        if ($cancelled) {
            return ['key' => 'cancelled', 'label' => 'CANCELLED · WAITING FOR A NEW DATE', 'tone' => 'fail'];
        }

        // Sent back by the panel, and waiting for the date of its next try.
        if ($earlier && DefenseOutcome::requiresRedefense($earlier->outcome)) {
            $label = DefenseOutcome::label($earlier->outcome, $stage).($deadline ? ' (until '.$deadline->format('M j').')' : '');

            return ['key' => 'redefense', 'label' => mb_strtoupper($label), 'tone' => 'major'];
        }

        if (! $schedule || ! $schedule->schedule_date) {
            return ['key' => 'not_scheduled', 'label' => 'NOT SCHEDULED', 'tone' => 'none'];
        }

        if ($schedule->outcome) {
            $label = DefenseOutcome::label($schedule->outcome, $stage);

            if (DefenseOutcome::requiresRedefense($schedule->outcome) && $deadline) {
                $label .= ' (until '.$deadline->format('M j').')';
            }

            return ['key' => $schedule->outcome, 'label' => mb_strtoupper($label), 'tone' => DefenseOutcome::tone($schedule->outcome)];
        }

        // Defended already but nobody has logged what the panel decided, as
        // opposed to a date still ahead of it - the same distinction the
        // schedule page's own tabs draw between "scheduled" and settled.
        if ($schedule->schedule_date->isPast()) {
            return ['key' => 'awaiting', 'label' => 'AWAITING RESULT', 'tone' => 'major'];
        }

        return ['key' => 'ongoing', 'label' => 'ONGOING', 'tone' => 'ongoing'];
    }

    /**
     * The approved adviser, consultant and grammarian of each team.
     *
     * @param  Collection<int, int>  $teamIds
     * @return Collection<int, Collection<string, ?string>>
     */
    private function expertsFor(Collection $teamIds): Collection
    {
        return TeamExpertAssignment::whereIn('team_id', $teamIds)
            ->where('status', 'approved')
            ->with('expert')
            ->get()
            ->groupBy('team_id')
            ->map(fn (Collection $rows) => $rows
                ->filter(fn (TeamExpertAssignment $a) => $a->expert && in_array($a->engaged_role_name, Team::REQUIRED_EXPERT_ROLES, true))
                ->mapWithKeys(fn (TeamExpertAssignment $a) => [$a->engaged_role_name => $a->expert->name]));
    }

    /**
     * Every member with a verified receipt for this stage's fee.
     *
     * Per student, unlike DefenseBoard::paidTeamIds() - a spreadsheet a CaPSA
     * hands to the department office is exactly the place someone still
     * owing a fee has to be named, not folded into a team that "isn't paid".
     *
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, int>
     */
    private function paidStudentIds(Collection $teams, string $stage): Collection
    {
        $postIds = ExpensePost::where('defense_type', $stage)->pluck('id');
        $memberIds = StudentSchoolYear::whereIn('team_id', $teams->pluck('team_id'))->pluck('student_id')->filter();

        return FinancialRecord::whereIn('expense_post_id', $postIds)
            ->where('f_record_status', 'verified')
            ->whereIn('student_id', $memberIds)
            ->pluck('student_id')
            ->unique();
    }

    private function studentName(object $student): string
    {
        $middle = $student->m_name ? mb_substr(trim($student->m_name), 0, 1).'.' : null;

        return trim($student->l_name.', '.trim($student->f_name.' '.$middle));
    }

    /** Whether this stage charges a fee at all - Pre-proposal Defense does not. */
    public static function isPaidStage(string $stage): bool
    {
        return in_array($stage, DefenseBoard::PAID_STAGES, true);
    }
}
