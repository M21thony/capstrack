<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\GoogleCalendarService;
use App\Support\DefenseOutcome;
use App\Support\DefenseProgress;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminPanelAssignmentController extends Controller
{
    public function __construct(private GoogleCalendarService $calendar) {}

    private const DEFENSE_TYPES = [
        'Pre-proposal Defense',
        'Title Defense',
        'Pre Oral Defense',
        'Final Oral Defense',
    ];

    // Pre-proposal Defense has no associated fee - only these three stages
    // are ever gated on payment (see paidTeamIds() and
    // generateScheduleForWindow()).
    private const PAID_DEFENSE_TYPES = [
        'Title Defense',
        'Pre Oral Defense',
        'Final Oral Defense',
    ];

    private const VENUES = ['Mac Lab', 'Open Lab'];

    // Defense day runs 8am-5pm; each session is a fixed 2-hour block.
    // Four blocks fit (8-10, 10-12, 12-2, 2-4) - a 4-6pm block would run
    // past the 5pm cutoff, so the day's last hour (4-5pm) is never used.
    // Both the automatic scheduler and the manual override snap to this
    // same grid, so a day+venue can now host up to 4 different panels
    // instead of just 1.
    private const TIME_SLOTS = ['08:00', '10:00', '12:00', '14:00'];

    private const SESSION_HOURS = 2;

    // Team tags are picked from a fixed list (see team.blade.php's
    // COMMON_TAGS); expert_expertise.expertise_field is free text an
    // expert typed themselves, so it won't always spell a tag exactly the
    // same way. This maps each tag to alternate phrasings worth treating
    // as the same match.
    private const TAG_SYNONYMS = [
        'artificial intelligence' => ['ai'],
        'ai' => ['artificial intelligence'],
        'machine learning' => ['ml', 'ai'],
        'web development' => ['web dev', 'web'],
        'mobile app' => ['mobile development', 'mobile', 'android', 'ios'],
        'data analytics' => ['data analysis', 'data science'],
        'cybersecurity' => ['security', 'infosec'],
        'database systems' => ['databases', 'database'],
        'ui/ux design' => ['ui/ux', 'ui', 'ux', 'user experience', 'user interface'],
        'network systems' => ['networking', 'networks'],
    ];

    public function index(Request $request): View
    {
        $defenseType = $request->query('defense_type', self::PAID_DEFENSE_TYPES[0]);
        if (! in_array($defenseType, self::DEFENSE_TYPES, true)) {
            $defenseType = self::PAID_DEFENSE_TYPES[0];
        }

        $requiresPayment = in_array($defenseType, self::PAID_DEFENSE_TYPES, true);

        $teams = Team::where('is_confirmed', true)
            ->where('team_status', '!=', 'Full')
            ->with(['members', 'defenseSchedules' => function ($q) {
                $q->with(['panels.expert']);
            }])
            ->orderBy('team_name')
            ->get();

        // Pre-proposal Defense has no fee - every confirmed team is simply
        // "schedulable" for it, no paid/unpaid split to make.
        if ($requiresPayment) {
            $paidTeamIds = $this->paidTeamIds($teams, $defenseType);
            $paidTeams = $teams->filter(fn ($t) => $paidTeamIds->contains($t->team_id))->values();
            $unpaidTeams = $teams->reject(fn ($t) => $paidTeamIds->contains($t->team_id))->values();
        } else {
            $paidTeamIds = $teams->pluck('team_id');
            $paidTeams = $teams;
            $unpaidTeams = collect();
        }

        $teamIds = $teams->pluck('team_id');

        $sections = StudentSchoolYear::whereIn('team_id', $teamIds)
            ->whereNotNull('program')
            ->with('student')
            ->get()
            ->keyBy('team_id');

        $teamSections = [];
        foreach ($teams as $team) {
            $sy = $sections->get($team->team_id);
            if ($sy && $sy->student) {
                $sectionLabel = Student::computeSectionLabel($sy->student->course, $sy->student->year_section);
                $teamSections[$team->team_id] = $sectionLabel ?? $sy->program ?? '';
            } else {
                $teamSections[$team->team_id] = '';
            }
        }

        $adviserRole = Role::where('role_name', 'adviser')->first();
        $consultantRole = Role::where('role_name', 'consultant')->first();

        $expertRoleIds = array_filter([
            $adviserRole?->role_id,
            $consultantRole?->role_id,
        ]);

        $experts = CapstoneExpert::where('approved_status', true)
            ->whereHas('roles', function ($q) use ($expertRoleIds) {
                $q->whereIn('roles.role_id', $expertRoleIds);
            })
            ->with(['roles' => function ($q) use ($expertRoleIds) {
                $q->whereIn('roles.role_id', $expertRoleIds);
            }, 'expertise'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $allSections = array_values(array_unique(array_filter(array_map('trim', $teamSections))));
        sort($allSections);

        // Built as plain arrays (not inline closures inside @json() in the
        // view) - Blade's @json() directive argument parser doesn't
        // reliably balance nested multi-line array literals inside
        // fn() => [...] closures and silently truncates them.
        $teamsForJs = $teams->map(fn ($t) => [
            'team_id' => $t->team_id,
            'team_name' => $t->team_name,
            'project_title' => $t->project_title,
            'is_paid' => $paidTeamIds->contains($t->team_id),
            'excluded_expert_ids' => $this->teamAdviserConsultantIds($t)->values(),
            // Empty once the team has its adviser, consultant and grammarian.
            'missing_roles' => $t->missingExpertRoles(),
            // One row per stage: the attempt in play. Earlier attempts are
            // kept in the table as a record, but this page assigns panels for
            // the current try, and the JS looks a stage up by defense_type.
            'schedules' => $t->defenseSchedules
                ->sortByDesc('attempt')
                ->unique('defense_type')
                ->map(fn ($s) => [
                'defense_id' => $s->defense_id,
                'defense_type' => $s->defense_type,
                'attempt' => $s->attempt,
                'schedule_date' => $s->schedule_date?->format('Y-m-d\TH:i'),
                'end_date' => $s->end_date?->format('Y-m-d\TH:i'),
                'venue' => $s->venue,
                'chair_expert_id' => $s->panels->firstWhere('panel_role', 'Panel Chair')?->expert_id,
                'member1_expert_id' => $s->panels->firstWhere('panel_role', 'Panel Member 1')?->expert_id,
                'member2_expert_id' => $s->panels->firstWhere('panel_role', 'Panel Member 2')?->expert_id,
            ])->values(),
        ])->values();

        $expertsForJs = $experts->map(fn ($e) => [
            'id' => $e->expert_id,
            'name' => $e->name,
            'tags' => $e->expertise->pluck('expertise_field')->values(),
            'degree' => $e->degreeLabel(),
            'can_chair' => $e->canChairPanel(),
        ])->values();

        return view('capsa.panel_assignment', compact(
            'teams',
            'experts',
            'teamSections',
            'allSections',
            'teamsForJs',
            'expertsForJs',
            'paidTeams',
            'unpaidTeams',
            'defenseType',
            'requiresPayment'
        ))->with('defenseTypes', self::DEFENSE_TYPES);
    }

    public function store(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'defense_type' => ['required', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
            // Nullable, not required - clearing the Chair slot (via its ×)
            // and saving is how an admin removes a team's panel entirely
            // for this stage, not an error. See assignPanelToTeam().
            'chair_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id'],
            'member1_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id'],
            'member2_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id', 'different:member1_expert_id'],
        ]);

        $result = $this->assignPanelToTeam($team, $validated);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['schedule'] ? $this->formatSchedule($result['schedule']) : $this->formatEmptySchedule($validated['defense_type']),
            'propagated' => $result['propagated'] ?? [],
        ]);
    }

    public function storeMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'exists:team,team_id'],
            'defense_type' => ['required', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
            'chair_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id'],
            'member1_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id'],
            'member2_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id', 'different:member1_expert_id'],
        ]);

        $teams = Team::whereIn('team_id', $validated['team_ids'])->get();

        $assigned = [];
        $skipped = [];

        foreach ($teams as $team) {
            $result = $this->assignPanelToTeam($team, $validated);

            if ($result['success']) {
                $formatted = $result['schedule'] ? $this->formatSchedule($result['schedule']) : $this->formatEmptySchedule($validated['defense_type']);
                $formatted['propagated'] = $result['propagated'] ?? [];
                $assigned[] = array_merge(['team_id' => $team->team_id], $formatted);
            } else {
                $skipped[] = [
                    'team_id' => $team->team_id,
                    'team_name' => $team->team_name,
                    'reason' => $result['message'],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'assigned' => $assigned,
                'skipped' => $skipped,
            ],
        ]);
    }

    // Shared by store() and storeMultiple() - runs the adviser/consultant
    // conflict check, then (if it passes) writes the panel. Payment status
    // never blocks assignment - only generateScheduleForWindow() (actually
    // giving a team a date/venue) is gated on payment; an admin can assign
    // or update a team's panel at any time regardless of payment. Never
    // throws for an expected rejection - returns
    // ['success' => false, 'message' => ...] so storeMultiple() can skip
    // and report a per-team failure instead of aborting the whole batch.
    //
    // Pre-proposal Defense establishes a team's panel for its ENTIRE
    // defense journey - assigning, changing, or removing it here
    // propagates the identical action to the other three stages,
    // overwriting whatever they currently have. Each stage still keeps its
    // own independent Assign/Update action for a targeted one-off swap
    // (e.g. a panelist unavailable for one specific date) - that override
    // never propagates anywhere; only a change made via Pre-proposal
    // Defense itself does. The result's 'propagated' key (only present for
    // a Pre-proposal Defense action) carries the other three stages'
    // resulting schedules so callers can keep their own state in sync.
    private function assignPanelToTeam(Team $team, array $validated): array
    {
        $result = empty($validated['chair_expert_id'])
            ? $this->removePanelFromTeam($team, $validated['defense_type'])
            : $this->writePanelForStage($team, $validated);

        if ($result['success'] && $validated['defense_type'] === 'Pre-proposal Defense') {
            $result['propagated'] = $this->propagatePanelToOtherStages($team, $validated);
        }

        return $result;
    }

    private function propagatePanelToOtherStages(Team $team, array $validated): array
    {
        $propagated = [];

        foreach (self::DEFENSE_TYPES as $stage) {
            if ($stage === 'Pre-proposal Defense') {
                continue;
            }

            $stageResult = empty($validated['chair_expert_id'])
                ? $this->removePanelFromTeam($team, $stage)
                : $this->writePanelForStage($team, array_merge($validated, ['defense_type' => $stage]));

            if ($stageResult['success']) {
                $propagated[] = $stageResult['schedule']
                    ? $this->formatSchedule($stageResult['schedule'])
                    : $this->formatEmptySchedule($stage);
            }
        }

        return $propagated;
    }

    private function writePanelForStage(Team $team, array $validated): array
    {
        // No panel until the team has its adviser, consultant and grammarian -
        // the Getting started part of the checklist.
        if ($missing = $team->missingExpertRoles()) {
            return [
                'success' => false,
                'message' => $team->team_name.' still needs '.$this->listRoles($missing)
                    .' before a panel can be assigned.',
            ];
        }

        // The department's rule: a Panel Chair has a Master's degree or higher.
        // Checked here because every way of saving a panel - one team, many,
        // or copied down from the pre-proposal stage - comes through here.
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

        $excludedIds = $this->teamAdviserConsultantIds($team);
        $conflicted = array_intersect($expertIds, $excludedIds->all());

        if (! empty($conflicted)) {
            return [
                'success' => false,
                'message' => "This team's adviser or consultant can't also sit on their defense panel.",
            ];
        }

        // Assigning a panel never touches the schedule itself - day/venue
        // are only ever set by generateScheduleForWindow(), triggered when
        // the admin saves a Defense Window. That's a deliberate two-step
        // workflow: assign panels for everyone first, then generate the
        // schedule once, rather than each assignment racing to grab a slot
        // before a window even exists.
        // A stage can have more than one attempt now, so "this team's Title
        // Defense" means the current one - the latest attempt.
        $existing = DefenseSchedule::where('team_id', $team->team_id)
            ->where('defense_type', $validated['defense_type'])
            ->latestAttempt()
            ->with('panels')
            ->first();

        $sortedNewExpertIds = collect($expertIds)->sort()->values()->all();
        $previousExpertIds = $existing
            ? $existing->panels->pluck('expert_id')->sort()->values()->all()
            : [];
        $panelUnchanged = $existing && $previousExpertIds === $sortedNewExpertIds;

        // Keyed on the attempt too, so assigning a panel edits the attempt in
        // play rather than silently reaching back over an earlier one.
        $schedule = DefenseSchedule::updateOrCreate(
            [
                'team_id' => $team->team_id,
                'defense_type' => $validated['defense_type'],
                'attempt' => $existing->attempt ?? 1,
            ],
            $panelUnchanged ? [] : ['schedule_date' => null, 'end_date' => null, 'venue' => null]
        );

        $schedule->panels()->delete();

        $roles = [
            'Panel Chair' => $validated['chair_expert_id'],
            'Panel Member 1' => $validated['member1_expert_id'] ?? null,
            'Panel Member 2' => $validated['member2_expert_id'] ?? null,
        ];

        foreach ($roles as $panelRole => $expertId) {
            if ($expertId === null) {
                continue;
            }

            DefensePanel::create([
                'defense_id' => $schedule->defense_id,
                'expert_id' => $expertId,
                'panel_role' => $panelRole,
                'created_at' => now(),
            ]);
        }

        $schedule->load('panels.expert');

        return ['success' => true, 'schedule' => $schedule];
    }

    // Clearing the Panel Chair slot (its × button) and saving means "remove
    // this team's panel for this stage entirely" - a schedule with no chair
    // can't run, so there's nothing meaningful to keep. Best-effort cancels
    // any Google Calendar events already created for it, same as every
    // other calendar call site in this controller. A team with no existing
    // schedule for this stage has nothing to remove, so that's still
    // treated as "a chair is required" rather than a silent no-op success -
    // otherwise a brand-new, never-assigned team could be "saved" empty.
    private function removePanelFromTeam(Team $team, string $defenseType): array
    {
        $schedule = DefenseSchedule::where('team_id', $team->team_id)
            ->where('defense_type', $defenseType)
            ->latestAttempt()
            ->with('panels')
            ->first();

        if (! $schedule) {
            return [
                'success' => false,
                'message' => 'A Panel Chair is required to assign a new panel.',
            ];
        }

        foreach ($schedule->panels as $panel) {
            if (! $panel->google_event_id) {
                continue;
            }

            try {
                $this->calendar->cancelBookingEvent($panel->expert_id, $panel->google_event_id);
            } catch (\Exception $e) {
                Log::warning('Defense panel Google Calendar cancel failed', [
                    'defense_id' => $schedule->defense_id,
                    'expert_id' => $panel->expert_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $schedule->panels()->delete();
        $schedule->delete();

        return ['success' => true, 'schedule' => null];
    }

    private function formatEmptySchedule(string $defenseType): array
    {
        return [
            'defense_id' => null,
            'defense_type' => $defenseType,
            'schedule_date' => null,
            'end_date' => null,
            'venue' => null,
            'panels' => [],
        ];
    }

    // The auto-scheduling algorithm's assignment isn't final — the admin
    // can manually override a specific team's day/venue (e.g. a room
    // freed up, or a panelist agreed to double up on short notice). A
    // conflict (double-booked venue or a shared panelist already on a
    // different panel that day) doesn't block the save; it's surfaced so
    // the admin can decide, and only actually saved once confirmed.
    public function updateSchedule(Request $request, DefenseSchedule $defense): JsonResponse
    {
        $validated = $request->validate([
            'schedule_date' => ['required', 'date'],
            'start_time' => ['required', 'string', 'in:'.implode(',', self::TIME_SLOTS)],
            'venue' => ['required', 'string', 'in:'.implode(',', self::VENUES)],
            'confirm' => ['nullable', 'boolean'],
        ]);

        $slotStart = Carbon::parse($validated['schedule_date'])->setTimeFromTimeString($validated['start_time']);
        $defense->loadMissing('panels.expert', 'team.members');

        $conflicts = $this->conflictsForManualOverride($defense, $slotStart, $validated['venue']);

        if (! empty($conflicts) && ! $request->boolean('confirm')) {
            return response()->json([
                'success' => false,
                'requires_confirmation' => true,
                'conflicts' => $conflicts,
                'message' => 'This schedule conflicts with an existing booking. Review and confirm to override anyway.',
            ], 409);
        }

        $defense->update([
            'schedule_date' => $slotStart,
            'end_date' => $slotStart->copy()->addHours(self::SESSION_HOURS),
            'venue' => $validated['venue'],
        ]);

        $this->syncDefensePanelToGoogleCalendar($defense);
        app(\App\Services\DefenseScheduleNotifier::class)->scheduled($defense);

        return response()->json([
            'success' => true,
            'data' => $this->formatSchedule($defense->fresh(['panels.expert'])),
        ]);
    }

    // Same two conflict types findSlotForPanel() avoids automatically, but
    // scoped differently: a venue double-booked by a different panel is
    // only a problem in that exact 2-hour block (a room can host different
    // panels across different blocks of the same day). A panelist is a
    // conflict in two ways - literally the same instant (regardless of
    // whether it's declared the "same panel"; nobody can be in two rooms
    // at once) OR anywhere ELSE that same day if it's a genuinely
    // different panel (two different panels can never share a day once
    // they share even one expert). This panel's own other teams at a
    // DIFFERENT time that day are never a conflict - that's the normal
    // "one panel judges several teams back-to-back" case. Scoped across
    // every defense_type (not just this team's own stage) since a
    // room/person conflict is real regardless of which stage it's for.
    private function conflictsForManualOverride(DefenseSchedule $defense, Carbon $slotStart, string $venue): array
    {
        $expertIds = $defense->panels->pluck('expert_id')->all();
        $sortedExpertIds = collect($expertIds)->sort()->values()->all();

        $sameSlotOthers = DefenseSchedule::where('schedule_date', $slotStart)
            ->where('defense_id', '!=', $defense->defense_id)
            ->with(['panels.expert', 'team'])
            ->get();

        $sameDayDifferentBlockOthers = DefenseSchedule::whereDate('schedule_date', $slotStart->toDateString())
            ->where('defense_id', '!=', $defense->defense_id)
            ->where('schedule_date', '!=', $slotStart)
            ->with(['panels.expert', 'team'])
            ->get()
            ->filter(fn ($s) => $s->panels->pluck('expert_id')->sort()->values()->all() !== $sortedExpertIds);

        $conflicts = [];

        foreach ($sameSlotOthers as $other) {
            $teamLabel = ($other->team?->team_name ?? 'Another team').' ('.$other->defense_type.')';

            if ($other->venue === $venue) {
                $conflicts[] = "Venue \"{$venue}\" is already booked at that time by {$teamLabel}.";
            }

            foreach ($other->panels->whereIn('expert_id', $expertIds) as $panel) {
                $expertName = $panel->expert?->name ?? 'This panelist';
                $conflicts[] = "{$expertName} is already on a panel at that time for {$teamLabel}.";
            }
        }

        foreach ($sameDayDifferentBlockOthers as $other) {
            $teamLabel = ($other->team?->team_name ?? 'Another team').' ('.$other->defense_type.')';

            foreach ($other->panels->whereIn('expert_id', $expertIds) as $panel) {
                $expertName = $panel->expert?->name ?? 'This panelist';
                $conflicts[] = "{$expertName} is already on a different panel that day for {$teamLabel}.";
            }
        }

        return array_values(array_unique($conflicts));
    }

    /**
     * POST /panel_assignment/schedule-drafts - a suggested date, time and
     * venue for every team ready to defend this stage. Nothing is saved.
     *
     * "Ready" is everything a team must have before it defends: its adviser,
     * consultant and grammarian, the previous stage passed, a panel with a
     * Chair, and - for a paid stage - every member's fee verified. A team that
     * falls short is listed with the reason instead of being left out quietly.
     *
     * Suggestions are worked out in memory. The tables cannot roll back a
     * trial save, so each suggestion is held in a list the next team's search
     * treats as booked, which is how two teams never get the same slot.
     */
    public function draftSchedule(Request $request): JsonResponse
    {
        $type = $request->validate([
            'defense_type' => ['required', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
        ])['defense_type'];

        $window = DefenseWindow::where('defense_type', $type)->first();

        if (! $window) {
            return response()->json([
                'success' => false,
                'message' => 'Set the '.$type.' window first - the dates this stage is defended within.',
            ], 422);
        }

        [$ready, $notReady, $alreadyScheduled] = $this->schedulingCandidates($type);

        $held = collect();
        $draft = [];
        $unplaced = [];

        foreach ($ready as $schedule) {
            try {
                $slot = $this->findSlotForPanel($window, $schedule->panels->pluck('expert_id')->all(), $schedule->defense_id, $held);
            } catch (\RuntimeException $e) {
                $unplaced[] = [
                    'team_id' => $schedule->team_id,
                    'team_name' => $schedule->team->team_name,
                    'reason' => 'No free slot for this panel inside the window. Widen the window or change the panel.',
                ];

                continue;
            }

            $suggested = new DefenseSchedule([
                'defense_type' => $type,
                'schedule_date' => $slot['schedule_date'],
                'end_date' => $slot['end_date'],
                'venue' => $slot['venue'],
            ]);
            $suggested->defense_id = $schedule->defense_id;
            $suggested->setRelation('panels', $schedule->panels);
            $held->push($suggested);

            $draft[] = [
                'defense_id' => $schedule->defense_id,
                'team_id' => $schedule->team_id,
                'team_name' => $schedule->team->team_name,
                'project_title' => $schedule->team->project_title,
                'panel' => $schedule->panels->sortBy('panel_role')
                    ->map(fn ($p) => ['role' => $p->panel_role, 'name' => $p->expert?->name])
                    ->values(),
                'date' => $slot['schedule_date']->toDateString(),
                'start_time' => $slot['schedule_date']->format('H:i'),
                'venue' => $slot['venue'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'defense_type' => $type,
                'window' => [
                    'start_date' => $window->start_date->toDateString(),
                    'end_date' => $window->end_date->toDateString(),
                ],
                'time_slots' => self::TIME_SLOTS,
                'venues' => self::VENUES,
                'session_hours' => self::SESSION_HOURS,
                'draft' => $draft,
                'unplaced' => $unplaced,
                'not_ready' => $notReady,
                'already_scheduled' => $alreadyScheduled,
            ],
        ]);
    }

    /**
     * PUT /panel_assignment/schedules - save the reviewed draft, all at once.
     *
     * Every row is checked again as it is saved: the team still ready, the
     * date inside the window, and nothing booked in that slot since the draft
     * was made. A row that fails is skipped with its reason; the rest are
     * saved, put on the panel's Google Calendar, and announced to the team.
     */
    public function applySchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'defense_type' => ['required', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.defense_id' => ['required', 'integer'],
            'items.*.date' => ['required', 'date_format:Y-m-d'],
            'items.*.start_time' => ['required', 'in:'.implode(',', self::TIME_SLOTS)],
            'items.*.venue' => ['required', 'in:'.implode(',', self::VENUES)],
        ]);

        $type = $validated['defense_type'];
        $window = DefenseWindow::where('defense_type', $type)->first();

        if (! $window) {
            return response()->json(['success' => false, 'message' => 'Set the '.$type.' window first.'], 422);
        }

        $readyById = $this->schedulingCandidates($type)[0]->keyBy('defense_id');
        $saved = [];
        $skipped = [];

        foreach ($validated['items'] as $item) {
            $schedule = $readyById->get($item['defense_id']);
            // A full closure, not fn(): an arrow function copies $skipped and
            // the appended row would be lost.
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

            if ($start->lt($window->start_date->copy()->startOfDay()) || $start->gt($window->end_date->copy()->endOfDay())) {
                $skip('That date is outside the '.$type.' window.');

                continue;
            }

            if ($conflicts = $this->conflictsForManualOverride($schedule, $start, $item['venue'])) {
                $skip(implode(' ', $conflicts));

                continue;
            }

            $schedule->update([
                'schedule_date' => $start,
                'end_date' => $start->copy()->addHours(self::SESSION_HOURS),
                'venue' => $item['venue'],
            ]);

            $this->syncDefensePanelToGoogleCalendar($schedule);
            app(\App\Services\DefenseScheduleNotifier::class)->scheduled($schedule);

            $saved[] = array_merge(['team_id' => $schedule->team_id], $this->formatSchedule($schedule->fresh(['panels.expert'])));
        }

        return response()->json([
            'success' => true,
            'message' => count($saved).' '.(count($saved) === 1 ? 'team' : 'teams').' scheduled'
                .($skipped ? ', '.count($skipped).' skipped.' : '.'),
            'data' => ['scheduled' => $saved, 'skipped' => $skipped],
        ]);
    }

    /**
     * Every confirmed team, sorted into ready to schedule for this stage and
     * not - with the reason.
     *
     * @return array{0: Collection<int, DefenseSchedule>, 1: array<int, array{team_id: int, team_name: string, reason: string}>, 2: int}
     */
    private function schedulingCandidates(string $type): array
    {
        $teams = Team::where('is_confirmed', true)
            ->with(['members', 'defenseSchedules' => fn ($q) => $q->where('defense_type', $type)->with('panels.expert')])
            ->orderBy('team_name')
            ->get();

        $paidTeamIds = in_array($type, self::PAID_DEFENSE_TYPES, true)
            ? $this->paidTeamIds($teams, $type)
            : $teams->pluck('team_id');

        $stage = array_search($type, DefenseProgress::STAGES, true);
        $previous = $stage > 0 ? DefenseProgress::STAGES[$stage - 1] : null;
        $progress = app(DefenseProgress::class);

        $ready = collect();
        $notReady = [];
        $alreadyScheduled = 0;

        foreach ($teams as $team) {
            $schedule = $team->defenseSchedules->sortByDesc('attempt')->first();

            // Through this stage already, or on the calendar for it.
            if ($schedule && DefenseOutcome::isPass($schedule->outcome)) {
                continue;
            }

            if ($schedule?->schedule_date) {
                $alreadyScheduled++;

                continue;
            }

            $missing = $team->missingExpertRoles();

            $reason = match (true) {
                $missing !== [] => 'Needs '.$this->listRoles($missing).'.',
                $previous && ! $progress->hasPassed($team, $previous) => 'Has not passed the '.$previous.' yet.',
                ($blockers = $team->checklistBlockersFor($type)) !== [] => 'Checklist: '.$blockers[0].' not done yet'
                    .(count($blockers) > 1 ? ' (and '.(count($blockers) - 1).' more)' : '').'.',
                ! $schedule || ! $schedule->panels->contains('panel_role', 'Panel Chair') => 'No panel assigned yet.',
                ! $paidTeamIds->contains($team->team_id) => 'Fees for this stage are not verified for every member.',
                default => null,
            };

            if ($reason) {
                $notReady[] = ['team_id' => $team->team_id, 'team_name' => $team->team_name, 'reason' => $reason];

                continue;
            }

            $schedule->setRelation('team', $team);
            $ready->push($schedule);
        }

        return [$ready, $notReady, $alreadyScheduled];
    }

    public function indexWindows(): JsonResponse
    {
        $windows = DefenseWindow::whereIn('defense_type', self::DEFENSE_TYPES)->get()->keyBy('defense_type');

        $data = collect(self::DEFENSE_TYPES)->map(function ($type) use ($windows) {
            $window = $windows->get($type);

            return [
                'defense_type' => $type,
                'start_date' => $window?->start_date?->toDateString(),
                'end_date' => $window?->end_date?->toDateString(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function updateWindow(Request $request, string $defenseType): JsonResponse
    {
        if (! in_array($defenseType, self::DEFENSE_TYPES, true)) {
            abort(404);
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $window = DefenseWindow::updateOrCreate(['defense_type' => $defenseType], $validated);

        $scheduledIds = $this->generateScheduleForWindow($window);
        $resolvedIds = $this->resolveScheduleConflicts($window);
        $compactedIds = $this->compactPanelSchedules($window);

        // Every schedule any of the three steps touched, refetched fresh so
        // the frontend can patch just those rows (updateTeamRow()) instead
        // of reloading the whole page.
        $touchedIds = $scheduledIds->merge($resolvedIds)->merge($compactedIds)->unique();
        $updatedSchedules = DefenseSchedule::whereIn('defense_id', $touchedIds)
            ->with('panels.expert')
            ->get()
            ->map(fn ($s) => array_merge(['team_id' => $s->team_id], $this->formatSchedule($s)))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'defense_type' => $window->defense_type,
                'start_date' => $window->start_date->toDateString(),
                'end_date' => $window->end_date->toDateString(),
            ],
            'scheduled_count' => $scheduledIds->count(),
            'resolved_count' => $resolvedIds->count(),
            'compacted_count' => $compactedIds->count(),
            'updated_schedules' => $updatedSchedules,
        ]);
    }

    // A team whose panel changes gets its own schedule cleared and
    // re-placed (see generateScheduleForWindow()), but any OTHER team still
    // sharing that same panel keeps whatever block it already had - so if
    // the reassigned team vacated an earlier block, the panel's remaining
    // teams are left with a gap instead of sitting back-to-back from the
    // start of the day. This closes that gap: for every panel/day/venue
    // group, re-lays its teams onto the earliest blocks that panel can
    // actually use that day (skipping only blocks a DIFFERENT panel holds),
    // in defense_id order, moving anyone who's sitting later than they need
    // to be. A no-op wherever nothing has moved.
    private function compactPanelSchedules(DefenseWindow $window): Collection
    {
        $scheduled = DefenseSchedule::where('defense_type', $window->defense_type)
            ->whereNotNull('schedule_date')
            ->whereNotNull('venue')
            ->with(['panels.expert', 'team.members'])
            ->orderBy('defense_id')
            ->get();

        $groups = $scheduled->groupBy(function ($s) {
            $expertIds = $s->panels->pluck('expert_id')->sort()->values()->implode(',');

            return $expertIds.'|'.$s->schedule_date->toDateString().'|'.$s->venue;
        });

        $compactedIds = collect();

        foreach ($groups as $group) {
            $sorted = $group->sortBy('defense_id')->values();
            $day = $sorted->first()->schedule_date->copy()->startOfDay();
            $venue = $sorted->first()->venue;
            $expertIds = $sorted->first()->panels->pluck('expert_id')->sort()->values()->all();

            $otherPanelsThisDay = $scheduled
                ->filter(fn ($s) => $s->schedule_date->isSameDay($day))
                ->filter(fn ($s) => $s->panels->pluck('expert_id')->sort()->values()->all() !== $expertIds);

            // An expert conflict rules out the WHOLE day for this panel,
            // not just the block a shared panelist happens to sit in - two
            // different panels can never share a day at all once they share
            // even one expert, so there's nothing safe to compact into here.
            $hasExpertConflictThisDay = $otherPanelsThisDay->contains(
                fn ($s) => count(array_intersect($expertIds, $s->panels->pluck('expert_id')->all())) > 0
            );

            if ($hasExpertConflictThisDay) {
                continue;
            }

            $blockedByOtherPanel = $otherPanelsThisDay
                ->filter(fn ($s) => $s->venue === $venue)
                ->map(fn ($s) => $s->schedule_date->format('H:i'))
                ->unique();

            $slotsForThisPanel = collect(self::TIME_SLOTS)->reject(fn ($t) => $blockedByOtherPanel->contains($t))->values();

            foreach ($sorted as $i => $schedule) {
                if (! isset($slotsForThisPanel[$i])) {
                    continue;
                }

                $newStart = $day->copy()->setTimeFromTimeString($slotsForThisPanel[$i]);

                if ($newStart->equalTo($schedule->schedule_date)) {
                    continue;
                }

                $schedule->update([
                    'schedule_date' => $newStart,
                    'end_date' => $newStart->copy()->addHours(self::SESSION_HOURS),
                ]);
                $this->syncDefensePanelToGoogleCalendar($schedule);
            app(\App\Services\DefenseScheduleNotifier::class)->scheduled($schedule);
                $compactedIds->push($schedule->defense_id);
            }
        }

        return $compactedIds;
    }

    // Re-saving the window is also the admin's "fix any conflicts" button:
    // finds schedules that ended up double-booked into the exact same
    // (date, time, venue) - which the current findSlotForPanel() can no
    // longer produce, but older rows saved before a bug fix (or a manual
    // edit) can still be sitting on - and re-places every one of them
    // except the earliest via the same conflict-aware algorithm used for
    // brand-new teams.
    private function resolveScheduleConflicts(DefenseWindow $window): Collection
    {
        $scheduled = DefenseSchedule::where('defense_type', $window->defense_type)
            ->whereNotNull('schedule_date')
            ->whereNotNull('venue')
            ->with(['panels.expert', 'team.members'])
            ->orderBy('defense_id')
            ->get();

        $toReplace = $scheduled
            ->groupBy(fn ($s) => $s->schedule_date->toDateTimeString().'|'.$s->venue)
            ->filter(fn ($group) => $group->count() > 1)
            ->flatMap(fn ($group) => $group->slice(1));

        $resolvedIds = collect();

        foreach ($toReplace as $schedule) {
            $expertIds = $schedule->panels->pluck('expert_id')->all();

            try {
                $slot = $this->findSlotForPanel($window, $expertIds, $schedule->defense_id);
            } catch (\RuntimeException $e) {
                // Window's full - leave this one where it is rather than
                // losing its schedule entirely.
                continue;
            }

            $schedule->update([
                'schedule_date' => $slot['schedule_date'],
                'end_date' => $slot['end_date'],
                'venue' => $slot['venue'],
            ]);
            $this->syncDefensePanelToGoogleCalendar($schedule);
            app(\App\Services\DefenseScheduleNotifier::class)->scheduled($schedule);
            $resolvedIds->push($schedule->defense_id);
        }

        return $resolvedIds;
    }

    // Assigning a panel to a team only ever clears its schedule to
    // unassigned (see store()) - this is the only place day/venue actually
    // get set, run once the admin saves a window covering everyone who's
    // already been given a panel for that defense stage.
    //
    // A schedule already sitting OUTSIDE the window's new date range is
    // treated the same as never having been scheduled at all - cleared and
    // re-placed inside the new window. Without this, moving the window
    // (e.g. the defense got pushed back a week) would silently do nothing
    // for every team that already had a date, which is exactly backwards
    // from what saving a new window is for.
    private function generateScheduleForWindow(DefenseWindow $window): Collection
    {
        $outOfWindowIds = DefenseSchedule::where('defense_type', $window->defense_type)
            ->whereNotNull('schedule_date')
            ->where(function ($q) use ($window) {
                $q->where('schedule_date', '<', $window->start_date->copy()->startOfDay())
                    ->orWhere('schedule_date', '>', $window->end_date->copy()->endOfDay());
            })
            ->pluck('defense_id');

        if ($outOfWindowIds->isNotEmpty()) {
            DefenseSchedule::whereIn('defense_id', $outOfWindowIds)->update([
                'schedule_date' => null,
                'end_date' => null,
                'venue' => null,
            ]);
        }

        $pending = DefenseSchedule::where('defense_type', $window->defense_type)
            ->whereNull('schedule_date')
            ->whereHas('panels', fn ($q) => $q->where('panel_role', 'Panel Chair'))
            ->with(['panels.expert', 'team.members'])
            ->orderBy('defense_id')
            ->get();

        // Payment only gates the actual scheduling step (giving a team a
        // real date/venue) - panel assignment itself is never blocked (see
        // assignPanelToTeam()). Pre-proposal Defense has no fee, so it's
        // never filtered here.
        if (in_array($window->defense_type, self::PAID_DEFENSE_TYPES, true)) {
            $pendingTeams = $pending->pluck('team')->filter()->unique('team_id')->values();
            $paidTeamIds = $this->paidTeamIds($pendingTeams, $window->defense_type);
            $pending = $pending->filter(fn ($s) => $paidTeamIds->contains($s->team_id))->values();
        }

        $scheduledIds = collect();

        foreach ($pending as $schedule) {
            $expertIds = $schedule->panels->pluck('expert_id')->all();

            try {
                $slot = $this->findSlotForPanel($window, $expertIds, $schedule->defense_id);
            } catch (\RuntimeException $e) {
                // Window's full for this panel - leave it unscheduled
                // rather than failing the whole batch; the admin can widen
                // the window or change the panel and save again.
                continue;
            }

            $schedule->update([
                'schedule_date' => $slot['schedule_date'],
                'end_date' => $slot['end_date'],
                'venue' => $slot['venue'],
            ]);
            $this->syncDefensePanelToGoogleCalendar($schedule);
            app(\App\Services\DefenseScheduleNotifier::class)->scheduled($schedule);
            $scheduledIds->push($schedule->defense_id);
        }

        return $scheduledIds;
    }

    // Deterministic, no AI - this is a room/person conflict problem (is
    // this venue or expert already booked in this 2-hour block), not a
    // language one, so plain constraint-checking guarantees the rules
    // hold every time instead of only "usually" holding.
    //
    // 1. If this exact panel (same expert set) already has a slot booked
    //    within this defense_type's window, reuse that SAME day and venue
    //    but find the next block on it this panel isn't already sitting in
    //    - this is what lets one panel judge several teams back-to-back in
    //    the same room without literally double-booking them into the same
    //    block. Falls through to a later existing day of theirs, if they
    //    have more than one, once the current day's four blocks are full.
    // 2. Otherwise (or once every day this panel already has is full) scan
    //    each day in the window, each of the day's four fixed 2-hour blocks
    //    (self::TIME_SLOTS), each venue in order, and return the first
    //    (date, time, venue) where no OTHER panel already holds that venue
    //    in that exact block, AND none of these experts sit on a different
    //    panel ANYWHERE that day (not just this block) - a panelist can't
    //    be judging two different teams' different panels on the same day
    //    even if the blocks don't literally overlap. Two different panels
    //    CAN still share the same day, just never the same block, and
    //    never the same panelist.
    /**
     * @param  Collection<int, DefenseSchedule>|null  $held  Slots already suggested in a draft but
     *                                                       not saved - booked as far as this search is concerned.
     */
    private function findSlotForPanel(DefenseWindow $window, array $expertIds, ?int $excludeScheduleId = null, ?Collection $held = null): array
    {
        $sortedExpertIds = collect($expertIds)->sort()->values()->all();

        $schedulesInWindow = DefenseSchedule::whereBetween('schedule_date', [
            $window->start_date->copy()->startOfDay(),
            $window->end_date->copy()->endOfDay(),
        ])
            ->whereNotNull('schedule_date')
            ->whereNotNull('venue')
            ->when($excludeScheduleId, fn ($q) => $q->where('defense_id', '!=', $excludeScheduleId))
            ->with('panels')
            ->get()
            ->toBase()
            ->concat($held ?? []);

        // True when this exact panel could sit in $venue at $slotStart:
        // the venue must be free in that exact block, but an expert
        // conflict is checked across the WHOLE DAY - a panelist can't sit
        // on two different panels on the same day at all, even in
        // different blocks or rooms, only ever on the SAME panel across
        // several blocks (which is what lets one panel judge several teams
        // back-to-back - and is never a conflict with itself).
        $isFree = function (Carbon $slotStart, string $venue) use ($schedulesInWindow, $sortedExpertIds): bool {
            $otherPanelsThisSlot = $schedulesInWindow
                ->filter(fn ($s) => $s->schedule_date->equalTo($slotStart))
                ->filter(fn ($s) => $s->panels->pluck('expert_id')->sort()->values()->all() !== $sortedExpertIds);

            $venueTaken = $otherPanelsThisSlot->contains(fn ($s) => $s->venue === $venue);

            $otherPanelsThisDay = $schedulesInWindow
                ->filter(fn ($s) => $s->schedule_date->isSameDay($slotStart))
                ->filter(fn ($s) => $s->panels->pluck('expert_id')->sort()->values()->all() !== $sortedExpertIds);

            $expertConflict = $otherPanelsThisDay->contains(
                fn ($s) => count(array_intersect($sortedExpertIds, $s->panels->pluck('expert_id')->all())) > 0
            );

            return ! $venueTaken && ! $expertConflict;
        };

        $panelSchedules = $schedulesInWindow->where('defense_type', $window->defense_type)
            ->filter(fn ($s) => $s->panels->pluck('expert_id')->sort()->values()->all() === $sortedExpertIds)
            ->sortBy('schedule_date');

        foreach ($panelSchedules->groupBy(fn ($s) => $s->schedule_date->toDateString()) as $daySchedules) {
            $day = $daySchedules->first()->schedule_date->copy()->startOfDay();
            $venue = $daySchedules->first()->venue;

            foreach (self::TIME_SLOTS as $time) {
                $slotStart = $day->copy()->setTimeFromTimeString($time);

                // $isFree only rules out OTHER panels sitting here (that's
                // the point - this panel's own bookings must never count as
                // a conflict with itself) - so a block this panel already
                // occupies still needs its own explicit check, or every
                // team sharing the panel would just land back in block one.
                $alreadyUsedByThisPanel = $daySchedules->contains(fn ($s) => $s->schedule_date->equalTo($slotStart));

                if (! $alreadyUsedByThisPanel && $isFree($slotStart, $venue)) {
                    return [
                        'schedule_date' => $slotStart,
                        'end_date' => $slotStart->copy()->addHours(self::SESSION_HOURS),
                        'venue' => $venue,
                    ];
                }
            }
        }

        foreach (CarbonPeriod::create($window->start_date, $window->end_date) as $date) {
            foreach (self::TIME_SLOTS as $time) {
                $slotStart = $date->copy()->setTimeFromTimeString($time);

                foreach (self::VENUES as $venue) {
                    if (! $isFree($slotStart, $venue)) {
                        continue;
                    }

                    return [
                        'schedule_date' => $slotStart,
                        'end_date' => $slotStart->copy()->addHours(self::SESSION_HOURS),
                        'venue' => $venue,
                    ];
                }
            }
        }

        throw new \RuntimeException(sprintf(
            'No available slot for this panel within %s - %s. Try a different panel or widen the window.',
            $window->start_date->format('M j'),
            $window->end_date->format('M j, Y')
        ));
    }

    // A team's own adviser/consultant can't also evaluate them on the
    // defense panel - conflict of interest.
    private function teamAdviserConsultantIds(Team $team): \Illuminate\Support\Collection
    {
        return TeamExpertAssignment::where('team_id', $team->team_id)
            ->where('status', 'approved')
            ->pluck('expert_id');
    }

    // A team is "paid" for a defense stage once every member has a verified
    // financial_records row against an expense_posts row tagged with that
    // stage. Untagged posts (defense_type NULL) never count toward any
    // stage - that's deliberate, not a bug (see the migration's comment).
    // Expects $teams to already have 'members' eager-loaded.
    private function paidTeamIds(Collection $teams, string $defenseType): Collection
    {
        $postIds = ExpensePost::where('defense_type', $defenseType)->pluck('id');

        $memberIdsByTeam = $teams->mapWithKeys(
            fn ($t) => [$t->team_id => $t->members->pluck('student_id')->filter()->values()]
        );

        $verifiedStudentIds = FinancialRecord::whereIn('expense_post_id', $postIds)
            ->where('f_record_status', 'verified')
            ->whereIn('student_id', $memberIdsByTeam->flatten()->unique())
            ->distinct()
            ->pluck('student_id');

        return $memberIdsByTeam
            ->filter(fn ($ids) => $ids->isNotEmpty() && $ids->diff($verifiedStudentIds)->isEmpty())
            ->keys();
    }

    public function suggestExperts(Team $team): JsonResponse
    {
        $tags = array_map(fn ($t) => trim((string) $t), $team->tags ?: []);
        $excludedIds = $this->teamAdviserConsultantIds($team);

        $experts = CapstoneExpert::where('approved_status', true)
            ->whereNotIn('expert_id', $excludedIds)
            ->with('expertise')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $suggestions = $experts->map(function ($expert) use ($tags) {
            $matched = $this->matchedTags($tags, $expert->expertise->pluck('expertise_field')->all());

            return [
                'expert_id' => $expert->expert_id,
                'name' => $expert->name,
                'matched_tags' => $matched,
                'score' => count($matched),
            ];
        })
            ->filter(fn ($s) => $s['score'] > 0)
            ->sortByDesc('score')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    // Same idea as suggestExperts() but for bulk mode - matches against the
    // UNION of every selected team's tags, so an expert only has to be
    // relevant to SOME of the teams to be starred (a chair covering "AI"
    // and another covering "Web Dev" both surface an expert who does one or
    // the other). Still excludes any expert who's an adviser/consultant for
    // ANY of the selected teams - no point starring someone the per-team
    // conflict check will just skip and report anyway.
    public function suggestExpertsMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'exists:team,team_id'],
        ]);

        $teams = Team::whereIn('team_id', $validated['team_ids'])->get();

        $tags = $teams
            ->flatMap(fn ($t) => array_map(fn ($tag) => trim((string) $tag), $t->tags ?: []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $excludedIds = $teams->flatMap(fn ($t) => $this->teamAdviserConsultantIds($t))->unique();

        $experts = CapstoneExpert::where('approved_status', true)
            ->whereNotIn('expert_id', $excludedIds)
            ->with('expertise')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $suggestions = $experts->map(function ($expert) use ($tags) {
            $matched = $this->matchedTags($tags, $expert->expertise->pluck('expertise_field')->all());

            return [
                'expert_id' => $expert->expert_id,
                'name' => $expert->name,
                'matched_tags' => $matched,
                'score' => count($matched),
            ];
        })
            ->filter(fn ($s) => $s['score'] > 0)
            ->sortByDesc('score')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    // The reverse of suggestExperts()/suggestExpertsMultiple(): the admin
    // picks the panel FIRST, and this finds which CONFIRMED teams' tags
    // best match that panel's combined expertise - for building a panel
    // around a set of experts rather than staffing one team at a time.
    // Excludes a team outright (not just de-prioritizes it) if any picked
    // expert is that team's own adviser/consultant, and reuses
    // paidTeamIds() so the picker can show each suggestion's payment
    // status for the chosen stage.
    // Payment is deliberately not considered here (and not returned) - an
    // assigned panel is that team's panel for every defense stage, not
    // scoped to one stage's payment status, so it has no bearing on which
    // teams are worth suggesting for a given panel.
    public function suggestTeamsForPanel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expert_ids' => ['required', 'array', 'min:1'],
            'expert_ids.*' => ['integer', 'exists:capstone_experts,expert_id'],
        ]);

        $expertiseFields = CapstoneExpert::whereIn('expert_id', $validated['expert_ids'])
            ->with('expertise')
            ->get()
            ->flatMap(fn ($e) => $e->expertise->pluck('expertise_field'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $teams = Team::where('is_confirmed', true)
            ->where('team_status', '!=', 'Full')
            ->with('preProposals')
            ->orderBy('team_name')
            ->get();

        $suggestions = $teams->map(function ($team) use ($expertiseFields, $validated) {
            // What the project is about: the team's own tags, the tags on
            // its approved pre-proposals, and the words of its title. A
            // team is suggested only when one of those meets the panel's
            // expertise.
            $tags = collect($team->tags ?: [])
                ->merge($team->preProposals->where('approved_status', true)->flatMap(fn ($p) => $p->tags ?? []))
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $matched = array_values(array_unique(array_merge(
                $this->matchedTags($tags, $expertiseFields),
                $this->matchedTitleWords((string) $team->project_title, $expertiseFields),
            )));
            $excludedIds = $this->teamAdviserConsultantIds($team);
            $conflicted = count(array_intersect($validated['expert_ids'], $excludedIds->all())) > 0;

            return [
                'team_id' => $team->team_id,
                'team_name' => $team->team_name,
                'project_title' => $team->project_title,
                'matched_tags' => $matched,
                'score' => count($matched),
                'conflicted' => $conflicted,
                'ready' => $team->missingExpertRoles() === [],
            ];
        })
            // A team without its adviser, consultant and grammarian cannot
            // take a panel, so suggesting it would only lead to a refusal.
            ->filter(fn ($s) => $s['score'] > 0 && ! $s['conflicted'] && $s['ready'])
            ->sortByDesc('score')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    // Case-insensitive match between a team's tags and an expert's
    // freeform expertise entries, widened with TAG_SYNONYMS so "AI" and
    // "Artificial Intelligence" (or an expert typing "ML" for "Machine
    // Learning") are still recognized as the same thing.
    private function matchedTags(array $tags, array $expertiseFields): array
    {
        $normalizedExpertise = array_map(fn ($f) => strtolower(trim((string) $f)), $expertiseFields);
        $matched = [];

        foreach ($tags as $tag) {
            $normalizedTag = strtolower($tag);
            $candidates = array_merge([$normalizedTag], self::TAG_SYNONYMS[$normalizedTag] ?? []);

            foreach ($normalizedExpertise as $field) {
                if ($field === '') {
                    continue;
                }

                foreach ($candidates as $candidate) {
                    if (str_contains($field, $candidate) || str_contains($candidate, $field)) {
                        $matched[] = $tag;
                        continue 3;
                    }
                }
            }
        }

        return array_values(array_unique($matched));
    }

    /** ['consultant', 'grammarian'] reads as "a consultant and a grammarian". */
    private function listRoles(array $roles): string
    {
        $named = array_map(fn ($r) => (in_array($r[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ').$r, $roles);

        return count($named) > 1
            ? implode(', ', array_slice($named, 0, -1)).' and '.end($named)
            : ($named[0] ?? '');
    }

    /** Words too common in capstone titles to say what a project is about. */
    private const TITLE_NOISE = [
        'system', 'systems', 'based', 'using', 'with', 'from', 'into', 'through', 'development',
        'application', 'app', 'platform', 'tool', 'online', 'digital', 'smart', 'automated',
        'design', 'implementation', 'students', 'student', 'school', 'college', 'campus',
    ];

    /**
     * Words of a team's title that appear, as whole words, in the panel's
     * expertise - "Crop Yield Prediction Using Machine Learning" meets an
     * expert in "Machine Learning" even when the team never tagged it.
     */
    private function matchedTitleWords(string $title, array $expertiseFields): array
    {
        $words = collect(preg_split('/[^a-z0-9]+/i', strtolower($title)))
            ->filter(fn ($w) => strlen($w) >= 4 && ! in_array($w, self::TITLE_NOISE, true))
            ->unique();

        return $words
            ->filter(fn ($w) => collect($expertiseFields)->contains(
                fn ($field) => preg_match('/\b'.preg_quote($w, '/').'\b/i', (string) $field) === 1
            ))
            ->map(fn ($w) => ucfirst($w))
            ->values()
            ->all();
    }

    // Best-effort, synchronous, never blocks the schedule save (every call
    // site here follows its own DB update with this, matching how every
    // other GoogleCalendarService call site in this app works - see
    // Instructor/ScheduleController). Each CONNECTED panelist (google_token
    // set) gets their own calendar copy of the event, created via their own
    // client/token; an unconnected panelist is skipped both as an event
    // owner and as an attendee on everyone else's copy, so one stray/
    // unconnected test account never breaks the real invitees' events.
    // Reschedule cancels the old event and creates a fresh one (same
    // pattern ScheduleController::updateSlot() already uses) rather than
    // patching in place.
    private function syncDefensePanelToGoogleCalendar(DefenseSchedule $schedule): void
    {
        if (! $schedule->schedule_date || ! $schedule->venue) {
            return;
        }

        $schedule->loadMissing('panels.expert', 'team.members');

        $connectedPanels = $schedule->panels->filter(fn ($p) => $p->expert && $p->expert->google_token);

        if ($connectedPanels->isEmpty()) {
            return;
        }

        $teamName = $schedule->team?->team_name ?? 'Team';
        $memberEmails = $schedule->team
            ? $schedule->team->members->pluck('email')->filter()->values()->all()
            : [];

        $connectedByExpertId = $connectedPanels->keyBy('expert_id');
        $endDate = $schedule->end_date ?? $schedule->schedule_date->copy()->addHours(self::SESSION_HOURS);
        $date = $schedule->schedule_date->toDateString();
        $startTime = $schedule->schedule_date->format('H:i');
        $endTime = $endDate->format('H:i');

        foreach ($connectedPanels as $panel) {
            $otherConnectedEmails = $connectedByExpertId
                ->except([$panel->expert_id])
                ->pluck('expert.email')
                ->filter()
                ->values();

            $attendeeEmails = collect($memberEmails)->merge($otherConnectedEmails)->unique()->values()->all();

            try {
                if ($panel->google_event_id) {
                    $this->calendar->cancelBookingEvent($panel->expert_id, $panel->google_event_id);
                }

                $newEventId = $this->calendar->createDefensePanelEvent(
                    organizerExpertId: $panel->expert_id,
                    date: $date,
                    startTime: $startTime,
                    endTime: $endTime,
                    defenseType: $schedule->defense_type,
                    teamName: $teamName,
                    venue: $schedule->venue,
                    attendeeEmails: $attendeeEmails,
                );

                $panel->update(['google_event_id' => $newEventId]);
            } catch (\Exception $e) {
                Log::warning('Defense panel Google Calendar sync failed', [
                    'defense_id' => $schedule->defense_id,
                    'expert_id' => $panel->expert_id,
                    'error' => $e->getMessage(),
                ]);
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
            'panels' => $schedule->panels->map(fn ($p) => [
                'panel_role' => $p->panel_role,
                'expert_id' => $p->expert_id,
                'expert_name' => $p->expert?->name,
            ]),
        ];
    }
}
