<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\DefenseScheduleNotifier;
use App\Services\GoogleCalendarService;
use App\Support\DefenseBoard;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Panel Assignment, and the scheduling endpoints Defense Scheduling calls.
 *
 * Assigning a panel never gives a team a date. Dates come only from Generate
 * schedule on the Defense Scheduling page, reviewed before they are saved,
 * and saving a defense window only stores its dates.
 */
class AdminPanelAssignmentController extends Controller
{
    private const DEFENSE_TYPES = DefenseBoard::STAGES;

    private const VENUES = DefenseBoard::VENUES;

    public function __construct(private GoogleCalendarService $calendar, private DefenseBoard $board) {}

    public function index(Request $request): View
    {
        $capsa = $this->capsa();

        // The page opens where the work is: the earliest stage any of this
        // CaPSA's teams is still working through, unless one is asked for.
        // A panel belongs to the team rather than to one defense (assigning
        // writes all four stages), but the stage still decides which teams,
        // schedules and panels this board lists.
        $stage = $this->board->openingStage($capsa, $request->query('defense_type'));

        $rows = $this->board->rows($capsa, $stage);

        return view('capsa.panel_assignment', [
            'state' => [
                'stage' => $stage,
                'stages' => DefenseBoard::STAGES,
                'teams' => $rows,
                'experts' => $this->board->panelists()->values(),
                'names' => DefenseBoard::names($rows),
            ],
        ]);
    }

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
            'has_rubric' => DefenseBoard::hasRubric($stage),
            'slots' => DefenseBoard::slots(DefenseBoard::minutesFor($stage)),
            'venues' => DefenseBoard::VENUES,
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
            'chair_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id'],
            'member1_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id'],
            'member2_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id', 'different:member1_expert_id'],
        ]));
    }

    // A team has one panel, and it sits every defense they face. Assigning,
    // changing or removing it is the same act for all four stages.
    private function assignPanelToTeam(Team $team, array $validated): array
    {
        $result = empty($validated['chair_expert_id'])
            ? $this->removePanelFromTeam($team)
            : $this->writePanelToAllStages($team, $validated);

        return $result;
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

        foreach (self::DEFENSE_TYPES as $stage) {
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
     * A clash inside the CaPSA's own sections is shown and can be kept on
     * purpose. A clash with another section's defense cannot: that booking is
     * another CaPSA's, and overruling it would double-book their room or panelist.
     */
    public function updateSchedule(Request $request, DefenseSchedule $defense): JsonResponse
    {
        $minutes = DefenseBoard::minutesFor($defense->defense_type);

        $validated = $request->validate([
            'schedule_date' => ['required', 'date'],
            'start_time' => ['required', 'string', 'in:'.implode(',', DefenseBoard::slots($minutes))],
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
        $ids = $defense->panels->pluck('expert_id')->map(fn ($id) => (int) $id)->all();
        $conflicts = [];

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
     * venue for every team in the CaPSA's sections that can be scheduled for
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
     * The CaPSA's teams sorted into ready to schedule for this stage and not,
     * with the reason - read from DefenseBoard, the one place that decides.
     *
     * @return array{0: Collection<int, DefenseSchedule>, 1: array<int, array{team_id: int, team_name: string, reason: string}>, 2: int}
     */
    private function schedulingCandidates(string $type, CapstoneExpert $capsa): array
    {
        $rows = collect($this->board->rows($capsa, $type))->reject(fn ($row) => $row['passed']);
        $alreadyScheduled = $rows->filter(fn ($row) => $row['schedule'])->count();
        $open = $rows->reject(fn ($row) => $row['schedule']);

        $readyIds = $open->filter(fn ($row) => $row['blockers'] === [])->pluck('defense_id');
        $ready = DefenseSchedule::whereIn('defense_id', $readyIds)->with(['panels.expert', 'team.members'])->orderBy('defense_id')->get();

        $notReady = $open->filter(fn ($row) => $row['blockers'] !== [])
            ->map(fn ($row) => ['team_id' => $row['id'], 'team_name' => $row['name'], 'reason' => $row['blockers'][0]['text'].'.'])
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

        $window = DefenseWindow::updateOrCreate(['defense_type' => $defenseType], array_filter($validated, fn ($v) => $v !== null));

        return response()->json([
            'success' => true,
            'data' => [
                'defense_type' => $window->defense_type,
                'start_date' => $window->start_date->toDateString(),
                'end_date' => $window->end_date->toDateString(),
                'duration_minutes' => $window->duration_minutes,
                'slots' => DefenseBoard::slots($window->duration_minutes),
            ],
        ]);
    }

    /**
     * The first weekday slot in the window, not yet passed, that this panel
     * can take without breaking a rule in clash(), in any section. Days a
     * panelist already sits are tried first, in that day's room, so a panel
     * judges several teams back-to-back without anyone moving rooms.
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

        $who = fn (DefenseSchedule $s) => $s->team && ! $this->board->covers($capsa, $s->team) ? 'another section' : 'Team '.($s->team?->team_name ?? '');

        // Null when this panel can sit in $venue at $slotStart, otherwise why not.
        $reason = function (Carbon $slotStart, string $venue) use ($booked, $ids, $minutes, $who): ?string {
            $slotEnd = $slotStart->copy()->addMinutes($minutes);

            foreach ($booked as $s) {
                if ($why = $this->clash($s, $ids, $slotStart, $slotEnd, $venue, $who($s))) {
                    return $why;
                }
            }

            return null;
        };

        $slot = fn (Carbon $start, string $venue) => ['schedule_date' => $start, 'end_date' => $start->copy()->addMinutes($minutes), 'venue' => $venue];

        $ownDays = $booked->where('defense_type', $window->defense_type)
            ->filter(fn ($s) => $s->panels->whereIn('expert_id', $ids)->isNotEmpty())
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

        // On a new day the panel's own rooms go first, so it stays put where it can.
        $venues = $ownDays->flatten()->pluck('venue')->concat(self::VENUES)->unique()->values();

        foreach (CarbonPeriod::create($window->start_date, $window->end_date) as $date) {
            foreach ($slots as $time) {
                $slotStart = $date->copy()->setTimeFromTimeString($time);

                if ($this->unusableTime($slotStart) !== null) {
                    continue;
                }

                foreach ($venues as $venue) {
                    $why = $reason($slotStart, $venue);

                    if ($why === null) {
                        return $slot($slotStart, $venue);
                    }

                    $skipped ??= 'Not earlier: '.$slotStart->format('M j').', '.$slotStart->format('g:i A').', '.$why;
                }
            }
        }

        throw new \RuntimeException('No available slot for this panel within the window.');
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
