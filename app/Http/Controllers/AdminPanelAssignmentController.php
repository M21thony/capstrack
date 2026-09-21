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
use App\Support\DefenseOutcome;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
        $stage = $this->board->openingStage($capsa, $request->query('defense_type'));

        $rows = $this->board->rows($capsa, $stage);

        return view('capsa.panel_assignment', [
            'state' => [
                'stage' => $stage,
                'stages' => self::DEFENSE_TYPES,
                'teams' => $rows,
                'experts' => $this->board->panelists()->values(),
                'names' => DefenseBoard::names($rows),
            ],
        ]);
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

        return response()->json([
            'success' => true,
            'data' => $result['schedule'] ? $this->formatSchedule($result['schedule']) : $this->formatEmptySchedule($validated['defense_type']),
            'propagated' => $result['propagated'] ?? [],
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
                $formatted = $result['schedule'] ? $this->formatSchedule($result['schedule']) : $this->formatEmptySchedule($validated['defense_type']);
                $formatted['propagated'] = $result['propagated'] ?? [];
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
            'defense_type' => ['required', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
            // Clearing the Chair and saving removes the team's panel for the stage.
            'chair_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id'],
            'member1_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id'],
            'member2_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id', 'different:member1_expert_id'],
        ]));
    }

    // The Pre-proposal panel is the team's panel for the rest of the capstone,
    // so assigning, changing or removing it there does the same to the other
    // three stages. A change made at a later stage stays with that stage.
    private function assignPanelToTeam(Team $team, array $validated): array
    {
        $result = empty($validated['chair_expert_id'])
            ? $this->removePanelFromTeam($team, $validated['defense_type'])
            : $this->writePanelForStage($team, $validated);

        if ($result['success'] && $validated['defense_type'] === DefenseOutcome::PRE_PROPOSAL) {
            $result['propagated'] = $this->propagatePanelToOtherStages($team, $validated);
        }

        return $result;
    }

    private function propagatePanelToOtherStages(Team $team, array $validated): array
    {
        $propagated = [];

        foreach (self::DEFENSE_TYPES as $stage) {
            if ($stage === DefenseOutcome::PRE_PROPOSAL) {
                continue;
            }

            $stageResult = empty($validated['chair_expert_id'])
                ? $this->removePanelFromTeam($team, $stage)
                : $this->writePanelForStage($team, array_merge($validated, ['defense_type' => $stage]));

            if ($stageResult['success']) {
                $propagated[] = $stageResult['schedule'] ? $this->formatSchedule($stageResult['schedule']) : $this->formatEmptySchedule($stage);
            }
        }

        return $propagated;
    }

    private function writePanelForStage(Team $team, array $validated): array
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

        $existing = DefenseSchedule::where('team_id', $team->team_id)
            ->where('defense_type', $validated['defense_type'])
            ->latestAttempt()
            ->with('panels')
            ->first();

        $unchanged = $existing && $existing->panels->pluck('expert_id')->sort()->values()->all() === collect($expertIds)->sort()->values()->all();

        // A new panel clears a date set for the old one, so Generate schedule places it again.
        $schedule = DefenseSchedule::updateOrCreate(
            ['team_id' => $team->team_id, 'defense_type' => $validated['defense_type'], 'attempt' => $existing->attempt ?? 1],
            $unchanged ? [] : ['schedule_date' => null, 'end_date' => null, 'venue' => null]
        );

        $schedule->panels()->delete();

        foreach (['Panel Chair' => $validated['chair_expert_id'], 'Panel Member 1' => $validated['member1_expert_id'] ?? null, 'Panel Member 2' => $validated['member2_expert_id'] ?? null] as $role => $expertId) {
            if ($expertId !== null) {
                DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $expertId, 'panel_role' => $role, 'created_at' => now()]);
            }
        }

        return ['success' => true, 'schedule' => $schedule->load('panels.expert')];
    }

    private function removePanelFromTeam(Team $team, string $defenseType): array
    {
        $schedule = DefenseSchedule::where('team_id', $team->team_id)
            ->where('defense_type', $defenseType)
            ->latestAttempt()
            ->with('panels')
            ->first();

        if (! $schedule) {
            return ['success' => false, 'message' => 'A Panel Chair is required to assign a new panel.'];
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

        return ['success' => true, 'schedule' => null];
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
        // The times on offer are the stage's own: how long its defenses run
        // is set on its window.
        $window = DefenseWindow::forStage($defense->defense_type);

        $validated = $request->validate([
            'schedule_date' => ['required', 'date'],
            'start_time' => ['required', 'string', Rule::in($window->slotTimes())],
            'venue' => ['required', 'string', 'in:'.implode(',', self::VENUES)],
            'confirm' => ['nullable', 'boolean'],
        ], ['start_time.in' => 'That is not one of the time slots for this stage.']);

        $defense->loadMissing('panels.expert', 'team.members');
        $capsa = $this->capsa();

        if ($defense->team && ! $this->board->covers($capsa, $defense->team)) {
            return response()->json(['success' => false, 'message' => 'That defense is not in your sections.'], 403);
        }

        $slotStart = Carbon::parse($validated['schedule_date'])->setTimeFromTimeString($validated['start_time']);
        $slotEnd = $slotStart->copy()->addMinutes($window->durationMinutes());
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
     * A room is taken while another defense is using it; a panelist is taken
     * for that time, and anywhere else that day on a different panel. Checked
     * across every stage and every section.
     *
     * "At the same time" means the two overlap, not that they start together:
     * a stage set to one hour and another set to two are cut into different
     * slots, and a panelist due at 9:00 in one is not free at 9:30 in the other.
     *
     * @return array<int, array{text: string, other: bool}>
     */
    private function conflictsForManualOverride(DefenseSchedule $defense, Carbon $slotStart, Carbon $slotEnd, string $venue, CapstoneExpert $capsa): array
    {
        $expertIds = $defense->panels->pluck('expert_id')->all();
        $sorted = collect($expertIds)->sort()->values()->all();
        $conflicts = [];

        $sameDay = DefenseSchedule::whereDate('schedule_date', $slotStart->toDateString())
            ->where('defense_id', '!=', $defense->defense_id)
            ->with(['panels.expert', 'team'])
            ->get();

        foreach ($sameDay as $other) {
            $isOther = $other->team && ! $this->board->covers($capsa, $other->team);
            $label = $isOther
                ? 'another section ('.$other->defense_type.')'
                : ($other->team?->team_name ?? 'Another team').' ('.$other->defense_type.')';
            $sameBlock = $this->overlap($other, $slotStart, $slotEnd);
            $samePanel = $other->panels->pluck('expert_id')->sort()->values()->all() === $sorted;

            if ($sameBlock && $other->venue === $venue) {
                $conflicts[] = ['text' => "Venue \"{$venue}\" is already booked at that time by {$label}.", 'other' => $isOther];
            }

            foreach ($other->panels->whereIn('expert_id', $expertIds) as $panel) {
                if ($sameBlock) {
                    $conflicts[] = ['text' => ($panel->expert?->name ?? 'This panelist')." is already on a panel at that time for {$label}.", 'other' => $isOther];
                } elseif (! $samePanel) {
                    $conflicts[] = ['text' => ($panel->expert?->name ?? 'This panelist')." is already on a different panel that day for {$label}.", 'other' => $isOther];
                }
            }
        }

        return collect($conflicts)->unique('text')->values()->all();
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
                'time_slots' => $window->slotTimes(),
                'session_minutes' => $window->durationMinutes(),
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

            $start = Carbon::parse($item['date'].' '.$item['start_time']);
            $end = $start->copy()->addMinutes($window->durationMinutes());

            if (! in_array($item['start_time'], $window->slotTimes(), true)) {
                $skip('That time is not one of the '.$type.' time slots.');

                continue;
            }

            if ($start->lt($window->start_date->copy()->startOfDay()) || $start->gt($window->end_date->copy()->endOfDay())) {
                $skip('That date is outside the '.$type.' window.');

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
        $data = collect($this->board->windows())->map(fn ($w, $type) => ['defense_type' => $type, 'start_date' => $w['start'], 'end_date' => $w['end']] + $this->settingsPayload($w))->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * PUT /panel_assignment/windows/{defenseType} - the dates a stage is
     * defended within, and how long each defense runs, shared by every
     * section. Saving only stores them; nobody is scheduled until Generate
     * schedule is reviewed and saved, and a defense that already has a time
     * keeps it - changing the length only shapes the ones made from now on.
     *
     * The length is optional so a caller that only knows the dates (the page
     * before it had this field) leaves it as it was.
     */
    public function updateWindow(Request $request, string $defenseType): JsonResponse
    {
        abort_unless(in_array($defenseType, self::DEFENSE_TYPES, true), 404);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'between:'.DefenseWindow::MIN_DURATION.','.DefenseWindow::MAX_DURATION, 'multiple_of:'.DefenseWindow::STEP],
        ], [
            'duration_minutes.between' => 'A defense can run from '.DefenseWindow::MIN_DURATION.' minutes to '.(DefenseWindow::MAX_DURATION / 60).' hours.',
            'duration_minutes.multiple_of' => 'Set the length in steps of '.DefenseWindow::STEP.' minutes.',
        ]);

        $window = DefenseWindow::forStage($defenseType)->fill($validated);
        $window->defense_type = $defenseType;
        $window->save();

        return response()->json([
            'success' => true,
            'data' => [
                'defense_type' => $window->defense_type,
                'start_date' => $window->start_date->toDateString(),
                'end_date' => $window->end_date->toDateString(),
                // Told, not guessed: these keep the length they were made with.
                'already_scheduled' => DefenseSchedule::where('defense_type', $defenseType)->whereNotNull('schedule_date')->count(),
            ] + $this->settingsPayload($window->settings()),
        ]);
    }

    /**
     * How the page names a window's length.
     *
     * @param  array<string, mixed>  $settings  From DefenseWindow::settings(), or a row of DefenseBoard::windows().
     * @return array{duration_minutes: int, time_slots: array<int, string>}
     */
    private function settingsPayload(array $settings): array
    {
        return [
            'duration_minutes' => $settings['duration'],
            'time_slots' => $settings['slots'],
        ];
    }

    /** Whether a booked defense is on during any of the given time. */
    private function overlap(DefenseSchedule $booked, Carbon $start, Carbon $end): bool
    {
        return $booked->schedule_date->lt($end) && $booked->endsAt()->gt($start);
    }

    /**
     * The first block in the window this panel can take, never double-booking
     * a room or a panelist in any section. A panel that already sits on a day
     * keeps that day and room, taking its next free block, so one panel judges
     * several teams back-to-back.
     *
     * @param  Collection<int, DefenseSchedule>|null  $held  Slots suggested in this draft but not saved.
     * @param  string|null  $skipped  Set to why the window's first block could not be used, when it could not.
     */
    private function findSlotForPanel(DefenseWindow $window, array $expertIds, ?int $excludeScheduleId, ?Collection $held, CapstoneExpert $capsa, ?string &$skipped = null): array
    {
        $sorted = collect($expertIds)->sort()->values()->all();

        $booked = DefenseSchedule::whereBetween('schedule_date', [$window->start_date->copy()->startOfDay(), $window->end_date->copy()->endOfDay()])
            ->whereNotNull('venue')
            ->when($excludeScheduleId, fn ($q) => $q->where('defense_id', '!=', $excludeScheduleId))
            ->with(['panels.expert', 'team'])
            ->get()
            ->toBase()
            ->concat($held ?? []);

        $who = fn (DefenseSchedule $s) => $s->team && ! $this->board->covers($capsa, $s->team) ? 'another section' : 'Team '.($s->team?->team_name ?? '');

        $minutes = $window->durationMinutes();
        $slots = $window->slotTimes();

        // Null when this panel can sit in $venue at $slotStart, otherwise why not.
        $reason = function (Carbon $slotStart, string $venue) use ($booked, $sorted, $who, $minutes): ?string {
            $slotEnd = $slotStart->copy()->addMinutes($minutes);

            foreach ($booked->filter(fn ($s) => $s->schedule_date->isSameDay($slotStart)) as $s) {
                $theirs = $s->panels->pluck('expert_id')->sort()->values()->all();
                $clashes = $this->overlap($s, $slotStart, $slotEnd);

                // The same panel may judge several teams that day, never two at once.
                if ($theirs === $sorted) {
                    if ($clashes) {
                        return 'This panel is already with '.$who($s).' at this time.';
                    }

                    continue;
                }

                if ($clashes && $s->venue === $venue) {
                    return $venue.' is taken at this time by '.$who($s).'.';
                }

                $shared = $s->panels->first(fn ($p) => in_array($p->expert_id, $sorted));

                if ($shared) {
                    return ($shared->expert?->name ?? 'A panelist').' sits on '.$who($s)."'s panel that day.";
                }
            }

            return null;
        };

        $ownDays = $booked->where('defense_type', $window->defense_type)
            ->filter(fn ($s) => $s->panels->pluck('expert_id')->sort()->values()->all() === $sorted)
            ->sortBy('schedule_date')
            ->groupBy(fn ($s) => $s->schedule_date->toDateString());

        foreach ($ownDays as $daySchedules) {
            $day = $daySchedules->first()->schedule_date->copy()->startOfDay();
            $venue = $daySchedules->first()->venue;

            foreach ($slots as $time) {
                $slotStart = $day->copy()->setTimeFromTimeString($time);

                if ($reason($slotStart, $venue) === null) {
                    return ['schedule_date' => $slotStart, 'end_date' => $slotStart->copy()->addMinutes($minutes), 'venue' => $venue];
                }
            }
        }

        foreach (CarbonPeriod::create($window->start_date, $window->end_date) as $date) {
            foreach ($slots as $time) {
                $slotStart = $date->copy()->setTimeFromTimeString($time);

                foreach (self::VENUES as $venue) {
                    $why = $reason($slotStart, $venue);

                    if ($why === null) {
                        return ['schedule_date' => $slotStart, 'end_date' => $slotStart->copy()->addMinutes($minutes), 'venue' => $venue];
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
        $endDate = $schedule->endsAt();

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
