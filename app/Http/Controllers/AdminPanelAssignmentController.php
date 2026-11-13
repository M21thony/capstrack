<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\ExpertAwayDate;
use App\Models\PanelReplacement;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\DefenseScheduleNotifier;
use App\Services\GoogleCalendarService;
use App\Services\Notifier;
use App\Support\DefenseBoard;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The panel and scheduling endpoints the Defenses page calls.
 *
 * Assigning a panel never gives a team a date. Dates come only from Generate
 * schedule on the Defense Scheduling page, reviewed before they are saved,
 * and saving a defense window only stores its dates.
 */
class AdminPanelAssignmentController extends Controller
{
    private const DEFENSE_TYPES = DefenseBoard::STAGES;

    private const VENUES = DefenseBoard::VENUES;

    /**
     * Held while a date is checked and saved, by every CaPSA/TSA: two of them
     * saving at the same moment would otherwise both find the same slot free.
     */
    private const SAVE_LOCK = 'defense-schedule-save';

    public function __construct(private GoogleCalendarService $calendar, private DefenseBoard $board) {}

    /**
     * GET /panel_assignment/board - one stage's board as JSON.
     *
     * The stage used to be a page of its own, so scheduling a team for a
     * different defense type meant navigating away and back. This lets the
     * page (and the per-team schedule dialog) redraw any stage in place.
     */
    public function board(Request $request): JsonResponse
    {
        /** @var CapstoneExpert $capsa */
        $capsa = $this->capsa();
        $stage = $this->board->openingStage($capsa, $request->query('defense_type'));
        $rows = $this->board->rows($capsa, $stage);
        $other = $this->board->otherBookings(array_column($rows, 'id'));

        return response()->json(['success' => true, 'data' => [
            'stage' => $stage,
            'stages' => DefenseBoard::STAGES,
            'teams' => $rows,
            'experts' => $this->board->panelists()->values(),
            'names' => DefenseBoard::names($rows, $other),
            'sections' => $capsa->sections ?? [],
            'other' => $other,
            'windows' => $this->board->windows(),
            'has_rubric' => DefenseBoard::hasRubric($stage, $capsa->handledPrograms()),
            'rubric_missing_programs' => DefenseBoard::programsWithoutRubric($stage, $capsa->handledPrograms()),
            'slots' => DefenseBoard::slots(DefenseBoard::minutesFor($stage)),
            'venues' => DefenseBoard::VENUES,
            'away' => $this->board->awayDates(),
            'progress' => $this->board->stageProgress($capsa),
        ]]);
    }

    public function store(Request $request, Team $team): JsonResponse
    {
        $validated = $this->validatePanel($request);

        if (! $this->board->covers($this->capsa(), $team)) {
            return response()->json(['success' => false, 'message' => $team->team_name.' is not in your sections.'], 403);
        }

        $result = $this->assignPanelToTeam($team, $validated);

        if (! $result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        // Return the first stage's schedule (Pre-proposal) as the primary, with all stages in propagated
        $allStages = [];
        $propagatedFormatted = [];

        if ($result['schedule']) {
            $allStages[] = $this->formatSchedule($result['schedule']);
        }

        if (! empty($result['propagated'])) {
            foreach ($result['propagated'] as $item) {
                if ($item instanceof DefenseSchedule) {
                    $formatted = $this->formatSchedule($item);
                    $allStages[] = $formatted;
                    $propagatedFormatted[] = $formatted;
                } else {
                    // It's a stage name (string) from removePanelFromTeam
                    $formatted = $this->formatEmptySchedule($item);
                    $allStages[] = $formatted;
                    $propagatedFormatted[] = $formatted;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result['schedule'] ? $this->formatSchedule($result['schedule']) : $this->formatEmptySchedule('Pre-proposal Defense'),
            'propagated' => $propagatedFormatted,
            'all_stages' => $allStages,
        ]);
    }

    public function storeMultiple(Request $request): JsonResponse
    {
        $validated = $this->validatePanel($request, [
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'exists:team,team_id'],
        ]);

        $capsa = $this->capsa();
        $assigned = [];
        $skipped = [];

        foreach (Team::whereIn('team_id', $validated['team_ids'])->get() as $team) {
            $result = $this->board->covers($capsa, $team)
                ? $this->assignPanelToTeam($team, $validated)
                : ['success' => false, 'message' => 'Not in your sections.'];

            if ($result['success']) {
                $allStages = [];
                $propagatedFormatted = [];

                if ($result['schedule']) {
                    $allStages[] = $this->formatSchedule($result['schedule']);
                }

                if (! empty($result['propagated'])) {
                    foreach ($result['propagated'] as $item) {
                        if ($item instanceof DefenseSchedule) {
                            $formatted = $this->formatSchedule($item);
                            $allStages[] = $formatted;
                            $propagatedFormatted[] = $formatted;
                        } else {
                            $formatted = $this->formatEmptySchedule($item);
                            $allStages[] = $formatted;
                            $propagatedFormatted[] = $formatted;
                        }
                    }
                }

                $formatted = $result['schedule'] ? $this->formatSchedule($result['schedule']) : $this->formatEmptySchedule('Pre-proposal Defense');
                $formatted['propagated'] = $propagatedFormatted;
                $formatted['all_stages'] = $allStages;
                $assigned[] = array_merge(['team_id' => $team->team_id], $formatted);
            } else {
                $skipped[] = ['team_id' => $team->team_id, 'team_name' => $team->team_name, 'reason' => $result['message']];
            }
        }

        return response()->json(['success' => true, 'data' => ['assigned' => $assigned, 'skipped' => $skipped]]);
    }

    private function validatePanel(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge($extra, [
            // defense_type is accepted but ignored - panel is assigned to all stages
            'defense_type' => ['nullable', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
            // Clearing the Chair and saving removes the team's panel for all stages.
            'chair_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', $this->sitsOnPanels()],
            'member1_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id', $this->sitsOnPanels()],
            'member2_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id', 'different:member1_expert_id', $this->sitsOnPanels()],
        ]));
    }

    /**
     * Only a CaPA or a consultant sits on a panel - never someone who is only
     * a grammarian. The page lists no one else; this is for a request that
     * did not come from the page.
     */
    private function sitsOnPanels(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value && ! CapstoneExpert::whereKey($value)->whereHas('roles', fn ($q) => $q->whereIn('role_name', ['adviser', 'consultant']))->exists()) {
                $fail('Only a CaPA or a consultant can sit on a panel.');
            }
        };
    }

    // A team has one panel, and it sits every defense they face. Assigning,
    // changing or removing it is the same act for all four stages.
    private function assignPanelToTeam(Team $team, array $validated): array
    {
        // A final panel sits every defense through Final Oral. Someone who
        // can no longer sit is replaced on their own (replacePanelist()).
        if ($team->panelIsFinal()) {
            return ['success' => false, 'message' => $team->team_name.'\'s panel is final. Replace one panelist instead.'];
        }

        $result = empty($validated['chair_expert_id'])
            ? $this->removePanelFromTeam($team)
            : $this->writePanelToAllStages($team, $validated);

        if ($result['success']) {
            // A panel saved here is a draft until the CaPSA/TSA finalizes it.
            $team->update(['panel_status' => empty($validated['chair_expert_id']) ? null : Team::PANEL_DRAFT]);
        }

        return $result;
    }

    /**
     * POST /panel_assignment/finalize - these teams' draft panels become
     * final: from now on they sit every defense through Final Oral, and the
     * teams can be scheduled.
     */
    public function finalize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'exists:team,team_id'],
        ]);

        $capsa = $this->capsa();
        $done = [];
        $skipped = [];

        foreach (Team::whereIn('team_id', $validated['team_ids'])->get() as $team) {
            $hasChair = DefensePanel::whereIn('defense_id', DefenseSchedule::where('team_id', $team->team_id)->pluck('defense_id'))
                ->where('panel_role', 'Panel Chair')->exists();

            $why = match (true) {
                ! $this->board->covers($capsa, $team) => 'Not in your sections.',
                ! $hasChair => 'Has no panel yet.',
                $team->panelIsFinal() => 'Already final.',
                default => null,
            };

            if ($why) {
                $skipped[] = ['team_id' => $team->team_id, 'team_name' => $team->team_name, 'reason' => $why];

                continue;
            }

            $team->update(['panel_status' => Team::PANEL_FINAL, 'panel_finalized_at' => now(), 'panel_finalized_by' => $capsa->expert_id]);
            $done[] = $team->team_id;
        }

        $n = count($done);

        return response()->json([
            'success' => true,
            'message' => $n.' '.($n === 1 ? 'panel' : 'panels').' finalized.',
            'data' => ['finalized' => $done, 'skipped' => $skipped],
        ]);
    }

    /**
     * POST /panel_assignment/{team}/replacements - one panelist on a final
     * panel who can no longer sit, and who takes the seat. Defenses already
     * held keep the panelist who sat them; every one still to come gets the
     * new one.
     */
    public function replacePanelist(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'seat' => ['required', 'in:chair,m1,m2'],
            'expert_id' => ['required', 'integer', 'exists:capstone_experts,expert_id', $this->sitsOnPanels()],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $capsa = $this->capsa();

        if (! $this->board->covers($capsa, $team)) {
            return response()->json(['success' => false, 'message' => $team->team_name.' is not in your sections.'], 403);
        }

        if (! $team->panelIsFinal()) {
            return response()->json(['success' => false, 'message' => 'This panel is still a draft. Change it instead.'], 422);
        }

        $role = ['chair' => 'Panel Chair', 'm1' => 'Panel Member 1', 'm2' => 'Panel Member 2'][$validated['seat']];
        $new = CapstoneExpert::findOrFail($validated['expert_id']);

        // Every defense still to come: not decided, and not already past.
        $upcoming = DefenseSchedule::where('team_id', $team->team_id)
            ->whereNull('outcome')
            ->where(fn ($q) => $q->whereNull('schedule_date')->orWhere('schedule_date', '>=', now()))
            ->with('panels.expert')
            ->get();

        $current = $upcoming->flatMap->panels->firstWhere('panel_role', $role);

        if (! $current) {
            return response()->json(['success' => false, 'message' => 'That seat has no defense left to sit.'], 422);
        }

        $sitting = $upcoming->flatMap->panels->where('panel_role', '!=', $role)->pluck('expert_id')->map(fn ($id) => (int) $id);
        $why = match (true) {
            (int) $current->expert_id === $new->expert_id => $new->name.' already has this seat.',
            $sitting->contains($new->expert_id) => $new->name.' is already on this panel.',
            $this->teamAdviserConsultantIds($team)->map(fn ($id) => (int) $id)->contains($new->expert_id) => "This team's adviser or consultant can't also sit on their defense panel.",
            $role === 'Panel Chair' && ! $new->canChairPanel() => 'A Panel Chair needs a Master\'s degree or higher.',
            default => null,
        };

        if ($why) {
            return response()->json(['success' => false, 'message' => $why], 422);
        }

        // A defense that already has a date keeps it, so the new panelist has to be free then.
        foreach ($upcoming->filter(fn ($s) => $s->schedule_date) as $schedule) {
            $ids = $schedule->panels->where('panel_role', '!=', $role)->pluck('expert_id')->map(fn ($id) => (int) $id)->push($new->expert_id)->all();
            $conflicts = $this->conflictsFor($schedule, $ids, $schedule->schedule_date, DefenseBoard::endOf($schedule), $schedule->venue, $capsa);

            if ($conflicts) {
                return response()->json(['success' => false, 'message' => $schedule->defense_type.' on '.$schedule->schedule_date->format('M j').': '.implode(' ', array_column($conflicts, 'text')).' Move that defense first.'], 409);
            }
        }

        $oldId = (int) $current->expert_id;

        foreach ($upcoming as $schedule) {
            $seat = $schedule->panels->firstWhere('panel_role', $role);

            if (! $seat) {
                continue;
            }

            if ($seat->google_event_id) {
                try {
                    $this->calendar->cancelBookingEvent($seat->expert_id, $seat->google_event_id);
                } catch (\Exception $e) {
                    Log::warning('Defense panel Google Calendar cancel failed', ['defense_id' => $schedule->defense_id, 'expert_id' => $seat->expert_id, 'error' => $e->getMessage()]);
                }
            }

            $seat->update(['expert_id' => $new->expert_id, 'google_event_id' => null]);

            if ($schedule->schedule_date) {
                $this->syncDefensePanelToGoogleCalendar($schedule->fresh(['panels.expert', 'team']));
            }
        }

        PanelReplacement::create([
            'team_id' => $team->team_id,
            'panel_role' => $role,
            'old_expert_id' => $oldId,
            'new_expert_id' => $new->expert_id,
            'reason' => $validated['reason'],
            'replaced_by' => $capsa->expert_id,
        ]);

        Notifier::toExpert($new, 'defense_panel', 'You are on '.$team->team_name.'\'s defense panel', 'You take the '.$role.' seat for the defenses still to come.', route('instructor.grading'));
        Notifier::toExpert($oldId, 'defense_panel', 'You were replaced on '.$team->team_name.'\'s panel', 'Reason: '.$validated['reason'].'. Defenses you already sat stay on your record.', route('instructor.grading'));

        return response()->json(['success' => true, 'message' => $new->name.' replaces '.(CapstoneExpert::find($oldId)?->name ?? 'the panelist').' on '.$team->team_name.'\'s panel.']);
    }

    private function writePanelToAllStages(Team $team, array $validated): array
    {
        if ($missing = $team->missingExpertRoles()) {
            return ['success' => false, 'message' => $team->team_name.' still needs '.$this->listRoles($missing).' before a panel can be assigned.'];
        }

        // A Panel Chair has a Master's degree or higher.
        $chair = CapstoneExpert::find($validated['chair_expert_id']);

        if ($chair && ! $chair->canChairPanel()) {
            return [
                'success' => false,
                'message' => $chair->name.' has '.($chair->degreeLabel() ? 'a '.$chair->degreeLabel().' degree' : 'no degree on record')
                    .'. A Panel Chair needs a Master\'s degree or higher.',
            ];
        }

        $expertIds = array_values(array_filter([
            $validated['chair_expert_id'],
            $validated['member1_expert_id'] ?? null,
            $validated['member2_expert_id'] ?? null,
        ]));

        if (array_intersect($expertIds, $this->teamAdviserConsultantIds($team)->all())) {
            return ['success' => false, 'message' => "This team's adviser or consultant can't also sit on their defense panel."];
        }

        $schedules = [];

        foreach ($team->defenseStages() as $stage) {
            $existing = DefenseSchedule::where('team_id', $team->team_id)
                ->where('defense_type', $stage)
                ->latestAttempt()
                ->with('panels')
                ->first();

            $unchanged = $existing && $existing->panels->pluck('expert_id')->sort()->values()->all() === collect($expertIds)->sort()->values()->all();

            // A new panel clears a date set for the old one, so Generate schedule places it again.
            $schedule = DefenseSchedule::updateOrCreate(
                ['team_id' => $team->team_id, 'defense_type' => $stage, 'attempt' => $existing->attempt ?? 1],
                $unchanged ? [] : ['schedule_date' => null, 'end_date' => null, 'venue' => null]
            );

            $schedule->panels()->delete();

            foreach (['Panel Chair' => $validated['chair_expert_id'], 'Panel Member 1' => $validated['member1_expert_id'] ?? null, 'Panel Member 2' => $validated['member2_expert_id'] ?? null] as $role => $expertId) {
                if ($expertId !== null) {
                    DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $expertId, 'panel_role' => $role, 'created_at' => now()]);
                }
            }

            $schedules[] = $schedule->load('panels.expert');
        }

        return ['success' => true, 'schedule' => $schedules[0], 'propagated' => array_slice($schedules, 1)];
    }

    private function removePanelFromTeam(Team $team): array
    {
        $propagated = [];

        foreach (self::DEFENSE_TYPES as $stage) {
            $schedule = DefenseSchedule::where('team_id', $team->team_id)
                ->where('defense_type', $stage)
                ->latestAttempt()
                ->with('panels')
                ->first();

            if (! $schedule) {
                $propagated[] = $stage;

                continue;
            }

            foreach ($schedule->panels->filter(fn ($p) => $p->google_event_id) as $panel) {
                try {
                    $this->calendar->cancelBookingEvent($panel->expert_id, $panel->google_event_id);
                } catch (\Exception $e) {
                    Log::warning('Defense panel Google Calendar cancel failed', ['defense_id' => $schedule->defense_id, 'expert_id' => $panel->expert_id, 'error' => $e->getMessage()]);
                }
            }

            $schedule->panels()->delete();
            $schedule->delete();

            $propagated[] = $stage;
        }

        return ['success' => true, 'schedule' => null, 'propagated' => $propagated];
    }

    private function formatEmptySchedule(string $defenseType): array
    {
        return ['defense_id' => null, 'defense_type' => $defenseType, 'schedule_date' => null, 'end_date' => null, 'venue' => null, 'panels' => []];
    }

    /**
     * PUT /panel_assignment/schedule/{defense} - move one team's defense.
     *
     * A clash inside the CaPSA/TSA's own sections is shown and can be kept on
     * purpose. A clash with another section's defense cannot: that booking is
     * another CaPSA/TSA's, and overruling it would double-book their room or panelist.
     */
    public function updateSchedule(Request $request, DefenseSchedule $defense): JsonResponse
    {
        $minutes = DefenseBoard::minutesFor($defense->defense_type);

        $validated = $request->validate([
            'schedule_date' => ['required', 'date'],
            // A redefense is held when the team and its panel are free, not on the slot grid.
            'start_time' => ['required', 'string', $defense->isRedefense() ? 'date_format:H:i' : 'in:'.implode(',', DefenseBoard::slots($minutes))],
            'venue' => ['required', 'string', 'in:'.implode(',', self::VENUES)],
            'confirm' => ['nullable', 'boolean'],
        ]);

        $defense->loadMissing('panels.expert', 'team.members');
        $capsa = $this->capsa();

        if ($defense->team && ! $this->board->covers($capsa, $defense->team)) {
            return response()->json(['success' => false, 'message' => 'That defense is not in your sections.'], 403);
        }

        $slotStart = Carbon::parse($validated['schedule_date'])->setTimeFromTimeString($validated['start_time']);
        $slotEnd = $slotStart->copy()->addMinutes($minutes);

        if ($why = $this->unusableTime($slotStart)) {
            return response()->json(['success' => false, 'message' => $why], 422);
        }

        return $this->whileSaving(fn () => $this->saveOneSchedule($request, $defense, $validated, $slotStart, $slotEnd, $capsa));
    }

    /**
     * Run a check-and-save holding the save lock, waiting a few seconds for
     * another CaPSA/TSA's save to finish first.
     */
    private function whileSaving(callable $save): JsonResponse
    {
        try {
            return Cache::lock(self::SAVE_LOCK, 30)->block(5, $save);
        } catch (LockTimeoutException) {
            return response()->json(['success' => false, 'message' => 'Another CaPSA/TSA is saving a schedule. Try again in a moment.'], 423);
        }
    }

    /** The check and the save, run while the save lock is held. */
    private function saveOneSchedule(Request $request, DefenseSchedule $defense, array $validated, Carbon $slotStart, Carbon $slotEnd, CapstoneExpert $capsa): JsonResponse
    {
        $conflicts = $this->conflictsForManualOverride($defense, $slotStart, $slotEnd, $validated['venue'], $capsa);

        if ($conflicts) {
            $other = collect($conflicts)->contains('other', true);

            if ($other || ! $request->boolean('confirm')) {
                return response()->json([
                    'success' => false,
                    'requires_confirmation' => ! $other,
                    'other_section' => $other,
                    'conflicts' => collect($conflicts)->pluck('text')->all(),
                    'message' => $other
                        ? 'This clashes with another section\'s defense. Pick another time or room.'
                        : 'This schedule conflicts with an existing booking. Review and confirm to override anyway.',
                ], 409);
            }
        }

        $defense->update([
            'schedule_date' => $slotStart,
            'end_date' => $slotEnd,
            'venue' => $validated['venue'],
        ]);

        $this->syncDefensePanelToGoogleCalendar($defense);
        app(DefenseScheduleNotifier::class)->scheduled($defense);

        return response()->json(['success' => true, 'data' => $this->formatSchedule($defense->fresh(['panels.expert']))]);
    }

    /**
     * What makes a booking clash with this one, checked across every stage
     * and every section.
     *
     * @return array<int, array{text: string, other: bool}>
     */
    private function conflictsForManualOverride(DefenseSchedule $defense, Carbon $slotStart, Carbon $slotEnd, string $venue, CapstoneExpert $capsa): array
    {
        return $this->conflictsFor($defense, $defense->panels->pluck('expert_id')->map(fn ($id) => (int) $id)->all(), $slotStart, $slotEnd, $venue, $capsa);
    }

    /**
     * The same, for a panel that is not (yet) the defense's own - a panelist
     * about to be replaced. A panelist's day away can be kept on purpose,
     * like a clash in the CaPSA/TSA's own sections; the defense goes ahead
     * without them.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{text: string, other: bool}>
     */
    private function conflictsFor(DefenseSchedule $defense, array $ids, Carbon $slotStart, Carbon $slotEnd, string $venue, CapstoneExpert $capsa): array
    {
        $conflicts = [];

        if ($why = $this->awayReason($ids, $slotStart)) {
            $conflicts[] = ['text' => $why, 'other' => false];
        }

        $sameDay = DefenseSchedule::whereDate('schedule_date', $slotStart->toDateString())
            ->where('defense_id', '!=', $defense->defense_id)
            ->whereNotNull('venue')
            ->with(['panels.expert', 'team'])
            ->get();

        foreach ($sameDay as $other) {
            $isOther = $other->team && ! $this->board->covers($capsa, $other->team);
            $label = $isOther
                ? 'another section ('.$other->defense_type.')'
                : ($other->team?->team_name ?? 'Another team').' ('.$other->defense_type.')';

            if ($why = $this->clash($other, $ids, $slotStart, $slotEnd, $venue, $label)) {
                $conflicts[] = ['text' => $why, 'other' => $isOther];
            }
        }

        return collect($conflicts)->unique('text')->values()->all();
    }

    /** Why one of these panelists can't sit on this day - they are away - or null. */
    private function awayReason(array $ids, Carbon $day, ?Collection $away = null): ?string
    {
        $away ??= ExpertAwayDate::forExperts($ids, $day, $day)->load('expert');
        $hit = $away->first(fn (ExpertAwayDate $a) => in_array((int) $a->expert_id, $ids, true) && $a->covers($day));

        return $hit ? ($hit->expert?->name ?? 'A panelist').' is away that day ('.mb_strtolower($hit->reason).').' : null;
    }

    /**
     * Why booking $s stops panelists $ids from sitting in $venue from $start
     * to $end, or null when it doesn't. The generator, saving and moving a
     * defense all ask this, so they can't disagree.
     *
     * - A room holds one defense at a time.
     * - A panelist sits one defense at a time.
     * - A panelist keeps one room for the whole day: the first defense they
     *   sit that day decides the room for every other one.
     */
    private function clash(DefenseSchedule $s, array $ids, Carbon $start, Carbon $end, string $venue, string $who): ?string
    {
        if (! $s->schedule_date->isSameDay($start)) {
            return null;
        }

        $overlaps = $s->schedule_date->lt($end) && $start->lt(DefenseBoard::endOf($s));
        $shared = $s->panels->first(fn ($p) => in_array((int) $p->expert_id, $ids, true));
        $name = $shared?->expert?->name ?? 'A panelist';

        if ($shared && $overlaps) {
            return "{$name} is already on {$who}'s panel at this time.";
        }

        if ($shared && $s->venue !== $venue) {
            return "{$name} is in {$s->venue} that day for {$who}, so this panel has to use {$s->venue} too.";
        }

        if ($overlaps && $s->venue === $venue) {
            return "{$venue} is taken at this time by {$who}.";
        }

        return null;
    }

    /** Why nobody can defend at $start, or null when the time is usable. */
    private function unusableTime(Carbon $start): ?string
    {
        if ($start->isWeekend()) {
            return 'Defenses are held on weekdays only.';
        }

        if ($start->lt(now())) {
            return 'That time has already passed.';
        }

        return null;
    }

    /**
     * POST /panel_assignment/schedule-drafts - a suggested date, time and
     * venue for every team in the CaPSA/TSA's sections that can be scheduled for
     * this stage. Nothing is saved.
     *
     * Suggestions are held in memory, and each next team's search treats them
     * as booked, which is how two teams never get the same slot.
     */
    public function draftSchedule(Request $request): JsonResponse
    {
        $type = $request->validate(['defense_type' => ['required', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)]])['defense_type'];
        $window = DefenseWindow::where('defense_type', $type)->first();

        if (! $window) {
            return response()->json(['success' => false, 'message' => 'Set the '.$type.' window first - the dates this stage is defended within.'], 422);
        }

        $capsa = $this->capsa();
        [$ready, $notReady, $alreadyScheduled] = $this->schedulingCandidates($type, $capsa);
        $held = collect();
        $draft = [];
        $unplaced = [];

        foreach ($ready as $schedule) {
            $skipped = null;

            try {
                $slot = $this->findSlotForPanel($window, $schedule->panels->pluck('expert_id')->all(), $schedule->defense_id, $held, $capsa, $skipped);
            } catch (\RuntimeException $e) {
                $unplaced[] = ['team_id' => $schedule->team_id, 'team_name' => $schedule->team->team_name, 'reason' => 'No free slot for this panel inside the window. Widen the window or change the panel.'];

                continue;
            }

            $suggested = new DefenseSchedule(['defense_type' => $type, 'schedule_date' => $slot['schedule_date'], 'end_date' => $slot['end_date'], 'venue' => $slot['venue']]);
            $suggested->defense_id = $schedule->defense_id;
            $suggested->setRelation('panels', $schedule->panels);
            $suggested->setRelation('team', $schedule->team);
            $held->push($suggested);

            $draft[] = [
                'defense_id' => $schedule->defense_id,
                'team_id' => $schedule->team_id,
                'team_name' => $schedule->team->team_name,
                'attempt' => (int) ($schedule->attempt ?? 1),
                'date' => $slot['schedule_date']->toDateString(),
                'start_time' => $slot['schedule_date']->format('H:i'),
                'venue' => $slot['venue'],
                'skipped' => $skipped,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'defense_type' => $type,
                'window' => ['start_date' => $window->start_date->toDateString(), 'end_date' => $window->end_date->toDateString()],
                'time_slots' => DefenseBoard::slots($window->duration_minutes),
                'minutes' => $window->duration_minutes,
                'venues' => self::VENUES,
                'draft' => $draft,
                'unplaced' => $unplaced,
                'not_ready' => $notReady,
                'already_scheduled' => $alreadyScheduled,
            ],
        ]);
    }

    /**
     * PUT /panel_assignment/schedules - save the reviewed draft, all at once.
     * Every row is checked again as it is saved; a row that fails is skipped
     * with its reason and the rest are saved, put on the panel's Google
     * Calendar, and announced to the team.
     */
    public function applySchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'defense_type' => ['required', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.defense_id' => ['required', 'integer'],
            'items.*.date' => ['required', 'date_format:Y-m-d'],
            'items.*.start_time' => ['required', 'date_format:H:i'],
            'items.*.venue' => ['required', 'in:'.implode(',', self::VENUES)],
        ]);

        $type = $validated['defense_type'];
        $window = DefenseWindow::where('defense_type', $type)->first();

        if (! $window) {
            return response()->json(['success' => false, 'message' => 'Set the '.$type.' window first.'], 422);
        }

        $capsa = $this->capsa();

        return $this->whileSaving(fn () => $this->saveDraft($validated, $window, $capsa));
    }

    /** Saves the reviewed draft row by row, while the save lock is held. */
    private function saveDraft(array $validated, DefenseWindow $window, CapstoneExpert $capsa): JsonResponse
    {
        $type = $validated['defense_type'];
        $minutes = $window->duration_minutes;
        $readyById = $this->schedulingCandidates($type, $capsa)[0]->keyBy('defense_id');
        $saved = [];
        $skipped = [];

        foreach ($validated['items'] as $item) {
            $schedule = $readyById->get($item['defense_id']);
            $skip = function (string $reason) use (&$skipped, $item, $schedule): void {
                $skipped[] = [
                    'defense_id' => $item['defense_id'],
                    'team_name' => $schedule?->team?->team_name ?? DefenseSchedule::find($item['defense_id'])?->team?->team_name ?? 'A team',
                    'reason' => $reason,
                ];
            };

            if (! $schedule) {
                $skip('No longer ready to schedule, or already has a date.');

                continue;
            }

            if (! in_array($item['start_time'], DefenseBoard::slots($minutes), true)) {
                $skip($item['start_time'].' is not a start time for '.$minutes.'-minute defenses.');

                continue;
            }

            $start = Carbon::parse($item['date'].' '.$item['start_time']);
            $end = $start->copy()->addMinutes($minutes);

            if ($start->lt($window->start_date->copy()->startOfDay()) || $start->gt($window->end_date->copy()->endOfDay())) {
                $skip('That date is outside the '.$type.' window.');

                continue;
            }

            if ($why = $this->unusableTime($start)) {
                $skip($why);

                continue;
            }

            if ($conflicts = $this->conflictsForManualOverride($schedule, $start, $end, $item['venue'], $capsa)) {
                $skip(implode(' ', array_column($conflicts, 'text')));

                continue;
            }

            $schedule->update(['schedule_date' => $start, 'end_date' => $end, 'venue' => $item['venue']]);
            $this->syncDefensePanelToGoogleCalendar($schedule);
            app(DefenseScheduleNotifier::class)->scheduled($schedule);

            $saved[] = array_merge(['team_id' => $schedule->team_id], $this->formatSchedule($schedule->fresh(['panels.expert'])));
        }

        return response()->json([
            'success' => true,
            'message' => count($saved).' '.(count($saved) === 1 ? 'team' : 'teams').' scheduled'.($skipped ? ', '.count($skipped).' skipped.' : '.'),
            'data' => ['scheduled' => $saved, 'skipped' => $skipped],
        ]);
    }

    /**
     * The CaPSA/TSA's teams sorted into ready to schedule for this stage and not,
     * with the reason - read from DefenseBoard, the one place that decides.
     *
     * @return array{0: Collection<int, DefenseSchedule>, 1: array<int, array{team_id: int, team_name: string, reason: string}>, 2: int}
     */
    private function schedulingCandidates(string $type, CapstoneExpert $capsa): array
    {
        $rows = collect($this->board->rows($capsa, $type))->reject(fn ($row) => $row['passed']);
        $alreadyScheduled = $rows->filter(fn ($row) => $row['schedule'])->count();
        $open = $rows->reject(fn ($row) => $row['schedule']);

        // Requirements still owed don't hold a team back; the panel follows them up.
        // A redefense is never placed here: the team finds a time with its
        // panel and the CaPSA/TSA enters it by hand.
        $leftOut = fn ($row) => $row['attempt'] > 1 || DefenseBoard::hardBlockers($row['blockers']) !== [];
        $readyIds = $open->reject($leftOut)->pluck('defense_id');
        $ready = DefenseSchedule::whereIn('defense_id', $readyIds)->with(['panels.expert', 'team.members'])->orderBy('defense_id')->get()
            // The regular batch gets its slots first; returnees take what is left.
            ->sortBy(fn (DefenseSchedule $s) => (bool) $s->team->returnee_stage)->values();

        $notReady = $open->filter($leftOut)
            ->map(fn ($row) => ['team_id' => $row['id'], 'team_name' => $row['name'], 'reason' => (DefenseBoard::hardBlockers($row['blockers'])[0]['text'] ?? 'Redefense, scheduled by hand').'.'])
            ->values()->all();

        return [$ready, $notReady, $alreadyScheduled];
    }

    public function indexWindows(): JsonResponse
    {
        $data = collect($this->board->windows())->map(fn ($w, $type) => ['defense_type' => $type, 'start_date' => $w['start'], 'end_date' => $w['end'], 'duration_minutes' => $w['minutes']])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * PUT /panel_assignment/windows/{defenseType} - the dates a stage is
     * defended within, shared by every section. Saving only stores them;
     * nobody is scheduled until Generate schedule is reviewed and saved.
     */
    public function updateWindow(Request $request, string $defenseType): JsonResponse
    {
        abort_unless(in_array($defenseType, self::DEFENSE_TYPES, true), 404);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_minutes' => ['nullable', 'integer', 'min:'.DefenseBoard::MIN_MINUTES, 'max:'.DefenseBoard::MAX_MINUTES],
        ]);

        $capsa = $this->capsa();
        $before = DefenseWindow::where('defense_type', $defenseType)->first();
        $window = DefenseWindow::updateOrCreate(['defense_type' => $defenseType], array_filter($validated, fn ($v) => $v !== null) + ['updated_by' => $capsa->expert_id, 'updated_at' => now()]);

        // Every section shares the window, so the other CaPSA/TSAs hear when it moves.
        if (! $before || ! $before->start_date->equalTo($window->start_date) || ! $before->end_date->equalTo($window->end_date)) {
            CapstoneExpert::whereHas('roles', fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE))->where('expert_id', '!=', $capsa->expert_id)->get()
                ->each(fn (CapstoneExpert $other) => Notifier::toExpert($other, 'defense_window', $defenseType.' window: '.$window->start_date->format('M j').' – '.$window->end_date->format('M j'),
                    $capsa->name.' set the '.$defenseType.' window for every section.', route('capsa.defenses', ['defense_type' => $defenseType])));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'defense_type' => $window->defense_type,
                'start_date' => $window->start_date->toDateString(),
                'end_date' => $window->end_date->toDateString(),
                'duration_minutes' => $window->duration_minutes,
                'slots' => DefenseBoard::slots($window->duration_minutes),
                'set_by' => $capsa->name,
                'set_on' => now()->toDateString(),
            ],
        ]);
    }

    /**
     * The first weekday slot in the window, not yet passed, that this panel
     * can take without breaking a rule in clash(), in any section. Days a
     * panelist already sits are tried first, in that day's room, so a panel
     * judges several teams back-to-back without anyone moving rooms.
     *
     * Slots held for other panels' waiting teams (see heldForWaitingTeams())
     * count as taken; this panel's own held slots are free to it. A panel
     * starting a new day is put where the room has space for all of its
     * teams in the stage, the waiting ones too, whenever such a room exists.
     *
     * @param  Collection<int, DefenseSchedule>|null  $held  Slots suggested in this draft but not saved.
     * @param  string|null  $skipped  Set to why the window's first slot could not be used, when it could not.
     */
    private function findSlotForPanel(DefenseWindow $window, array $expertIds, ?int $excludeScheduleId, ?Collection $held, CapstoneExpert $capsa, ?string &$skipped = null): array
    {
        $ids = array_map('intval', $expertIds);
        $minutes = $window->duration_minutes;
        $slots = DefenseBoard::slots($minutes);

        $booked = DefenseSchedule::whereBetween('schedule_date', [$window->start_date->copy()->startOfDay(), $window->end_date->copy()->endOfDay()])
            ->whereNotNull('venue')
            ->when($excludeScheduleId, fn ($q) => $q->where('defense_id', '!=', $excludeScheduleId))
            ->with(['panels.expert', 'team'])
            ->get()
            ->toBase()
            ->concat($held ?? []);

        $waiting = $this->waitingTeams($window->defense_type, array_filter([$excludeScheduleId, ...($held ?? collect())->pluck('defense_id')->all()]));
        $isOwn = fn (DefenseSchedule $s) => $s->panels->whereIn('expert_id', $ids)->isNotEmpty();
        $heldForOthers = $this->heldForWaitingTeams($window, $booked, $waiting)->reject($isOwn);

        $who = fn (DefenseSchedule $s) => $s->team && ! $this->board->covers($capsa, $s->team) ? 'another section' : 'Team '.($s->team?->team_name ?? '');

        $away = ExpertAwayDate::forExperts($ids, $window->start_date, $window->end_date)->load('expert');

        // Null when this panel can sit in $venue at $slotStart, otherwise why not.
        $reason = function (Carbon $slotStart, string $venue) use ($booked, $heldForOthers, $ids, $minutes, $who, $away): ?string {
            $slotEnd = $slotStart->copy()->addMinutes($minutes);

            if ($away->isNotEmpty() && ($why = $this->awayReason($ids, $slotStart, $away))) {
                return $why;
            }

            foreach ($booked as $s) {
                if ($why = $this->clash($s, $ids, $slotStart, $slotEnd, $venue, $who($s))) {
                    return $why;
                }
            }

            foreach ($heldForOthers as $s) {
                if ($why = $this->clash($s, $ids, $slotStart, $slotEnd, $venue, $who($s).', which has the same panel and is not ready yet')) {
                    return $why;
                }
            }

            return null;
        };

        $slot = fn (Carbon $start, string $venue) => ['schedule_date' => $start, 'end_date' => $start->copy()->addMinutes($minutes), 'venue' => $venue];

        $ownDays = $booked->where('defense_type', $window->defense_type)
            ->filter($isOwn)
            ->sortBy('schedule_date')
            ->groupBy(fn ($s) => $s->schedule_date->toDateString());

        foreach ($ownDays as $daySchedules) {
            $day = $daySchedules->first()->schedule_date->copy()->startOfDay();
            $venue = $daySchedules->first()->venue;

            foreach ($slots as $time) {
                $slotStart = $day->copy()->setTimeFromTimeString($time);

                if ($this->unusableTime($slotStart) === null && $reason($slotStart, $venue) === null) {
                    return $slot($slotStart, $venue);
                }
            }
        }

        // This team, and every team of its panel still waiting for a slot in
        // this stage - never more than one room holds in a day.
        $needed = min(1 + $waiting->filter($isOwn)->count(), count($slots));
        $fitsWholePanel = function (Carbon $date, string $venue) use ($slots, $reason, $needed): bool {
            $free = 0;

            foreach ($slots as $time) {
                $start = $date->copy()->setTimeFromTimeString($time);

                if ($this->unusableTime($start) === null && $reason($start, $venue) === null && ++$free >= $needed) {
                    return true;
                }
            }

            return false;
        };

        // On a new day the panel's own rooms go first, so it stays put where it can.
        $venues = $ownDays->flatten()->pluck('venue')->concat(self::VENUES)->unique()->values();
        $fallback = null;
        $skippedBeforeFallback = null;

        foreach (CarbonPeriod::create($window->start_date, $window->end_date) as $date) {
            foreach ($slots as $time) {
                $slotStart = $date->copy()->setTimeFromTimeString($time);

                if ($this->unusableTime($slotStart) !== null) {
                    continue;
                }

                foreach ($venues as $venue) {
                    $why = $reason($slotStart, $venue);

                    if ($why === null && ($needed === 1 || $fitsWholePanel($date, $venue))) {
                        return $slot($slotStart, $venue);
                    }

                    if ($why === null) {
                        // Free, but the rest of this panel's teams would not fit
                        // in this room that day. Kept in case no room fits them all.
                        if (! $fallback) {
                            $fallback = $slot($slotStart, $venue);
                            $skippedBeforeFallback = $skipped;
                        }
                        $why = "{$venue} has no room that day for all {$needed} of this panel's teams.";
                    }

                    $skipped ??= 'Not earlier: '.$slotStart->format('M j').', '.$slotStart->format('g:i A').', '.$why;
                }
            }
        }

        if ($fallback) {
            $skipped = $skippedBeforeFallback;

            return $fallback;
        }

        throw new \RuntimeException('No available slot for this panel within the window.');
    }

    /**
     * Teams with a panel for this stage but no slot yet - usually not ready
     * (fee unpaid, a requirement missing), or ready and waiting their turn in
     * the draft being built. Finished defenses are not waiting for anything.
     *
     * @param  array<int, int>  $except  Defenses being placed or already placed in this draft.
     * @return Collection<int, DefenseSchedule>
     */
    private function waitingTeams(string $type, array $except): Collection
    {
        return DefenseSchedule::where('defense_type', $type)
            ->where('attempt', 1) // no slot is held for a redefense, its date is arranged by the team
            ->whereNull('venue')
            ->whereNull('outcome')
            ->whereHas('panels')
            ->when($except, fn ($q) => $q->whereNotIn('defense_id', $except))
            ->with(['panels.expert', 'team'])
            ->orderBy('defense_id')
            ->get()
            ->toBase();
    }

    /**
     * One slot held for each waiting team whose panel already defends in the
     * window: the next free slot in that panel's room on the day it defends,
     * the slot the team would get once ready. No other panel is placed there,
     * so a panel's teams stay together even when they become ready one by
     * one. A held slot stays empty until its team is scheduled. Nothing is
     * saved - these are worked out again each time.
     *
     * @param  Collection<int, DefenseSchedule>  $booked
     * @param  Collection<int, DefenseSchedule>  $waiting
     * @return Collection<int, DefenseSchedule> Unsaved placeholders carrying the waiting team's panel.
     */
    private function heldForWaitingTeams(DefenseWindow $window, Collection $booked, Collection $waiting): Collection
    {
        $minutes = $window->duration_minutes;
        $held = collect();

        foreach ($waiting as $team) {
            $ids = $team->panels->pluck('expert_id')->map(fn ($id) => (int) $id)->all();
            $days = $booked->where('defense_type', $window->defense_type)
                ->filter(fn ($s) => $s->panels->whereIn('expert_id', $ids)->isNotEmpty())
                ->sortBy('schedule_date')
                ->groupBy(fn ($s) => $s->schedule_date->toDateString());

            foreach ($days as $daySchedules) {
                $day = $daySchedules->first()->schedule_date->copy()->startOfDay();
                $venue = $daySchedules->first()->venue;

                foreach (DefenseBoard::slots($minutes) as $time) {
                    $start = $day->copy()->setTimeFromTimeString($time);
                    $end = $start->copy()->addMinutes($minutes);

                    if ($this->unusableTime($start) !== null) {
                        continue;
                    }

                    $taken = $booked->concat($held)->contains(fn ($s) => $this->clash($s, $ids, $start, $end, $venue, '') !== null);

                    if (! $taken) {
                        $slot = new DefenseSchedule(['defense_type' => $window->defense_type, 'schedule_date' => $start, 'end_date' => $end, 'venue' => $venue]);
                        $slot->defense_id = $team->defense_id;
                        $slot->setRelation('panels', $team->panels);
                        $slot->setRelation('team', $team->team);
                        $held->push($slot);

                        continue 3;
                    }
                }
            }
        }

        return $held;
    }

    // A team's own adviser and consultant can't evaluate them on the panel.
    private function teamAdviserConsultantIds(Team $team): Collection
    {
        return TeamExpertAssignment::where('team_id', $team->team_id)->where('status', 'approved')->pluck('expert_id');
    }

    /** ['consultant', 'grammarian'] reads as "a consultant and a grammarian". */
    private function listRoles(array $roles): string
    {
        $named = array_map(fn ($r) => (in_array($r[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ').$r, $roles);

        return count($named) > 1 ? implode(', ', array_slice($named, 0, -1)).' and '.end($named) : ($named[0] ?? '');
    }

    // Best-effort: each connected panelist gets their own calendar copy of the
    // event; a failure is logged and never undoes the schedule.
    private function syncDefensePanelToGoogleCalendar(DefenseSchedule $schedule): void
    {
        if (! $schedule->schedule_date || ! $schedule->venue) {
            return;
        }

        $schedule->loadMissing('panels.expert', 'team.members');
        $connected = $schedule->panels->filter(fn ($p) => $p->expert && $p->expert->google_token);

        if ($connected->isEmpty()) {
            return;
        }

        $memberEmails = $schedule->team ? $schedule->team->members->pluck('email')->filter()->values()->all() : [];
        $byExpert = $connected->keyBy('expert_id');
        $endDate = DefenseBoard::endOf($schedule);

        foreach ($connected as $panel) {
            $attendees = collect($memberEmails)->merge($byExpert->except([$panel->expert_id])->pluck('expert.email')->filter())->unique()->values()->all();

            try {
                if ($panel->google_event_id) {
                    $this->calendar->cancelBookingEvent($panel->expert_id, $panel->google_event_id);
                }

                $panel->update(['google_event_id' => $this->calendar->createDefensePanelEvent(
                    organizerExpertId: $panel->expert_id,
                    date: $schedule->schedule_date->toDateString(),
                    startTime: $schedule->schedule_date->format('H:i'),
                    endTime: $endDate->format('H:i'),
                    defenseType: $schedule->defense_type,
                    teamName: $schedule->team?->team_name ?? 'Team',
                    venue: $schedule->venue,
                    attendeeEmails: $attendees,
                )]);
            } catch (\Exception $e) {
                Log::warning('Defense panel Google Calendar sync failed', ['defense_id' => $schedule->defense_id, 'expert_id' => $panel->expert_id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function formatSchedule(DefenseSchedule $schedule): array
    {
        return [
            'defense_id' => $schedule->defense_id,
            'defense_type' => $schedule->defense_type,
            'schedule_date' => $schedule->schedule_date?->toDateTimeString(),
            'end_date' => $schedule->end_date?->toDateTimeString(),
            'venue' => $schedule->venue,
            'panels' => $schedule->panels->map(fn ($p) => ['panel_role' => $p->panel_role, 'expert_id' => $p->expert_id, 'expert_name' => $p->expert?->name]),
        ];
    }

    private function capsa(): CapstoneExpert
    {
        return auth('expert')->user();
    }
}
