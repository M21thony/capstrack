<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPanelAssignmentController extends Controller
{
    private const DEFENSE_TYPES = [
        'Pre-proposal Defense',
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

    public function index(): View
    {
        $teams = Team::where('is_confirmed', true)
            ->where('team_status', '!=', 'closed')
            ->with(['defenseSchedules' => function ($q) {
                $q->with(['panels.expert']);
            }])
            ->orderBy('team_name')
            ->get();

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
            'excluded_expert_ids' => $this->teamAdviserConsultantIds($t)->values(),
            'schedules' => $t->defenseSchedules->map(fn ($s) => [
                'defense_id' => $s->defense_id,
                'defense_type' => $s->defense_type,
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
        ])->values();

        return view('admin.panel_assignment', compact('teams', 'experts', 'teamSections', 'allSections', 'teamsForJs', 'expertsForJs'));
    }

    public function store(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'defense_type' => ['required', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
            'chair_expert_id' => ['required', 'integer', 'exists:capstone_experts,expert_id'],
            'member1_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id'],
            'member2_expert_id' => ['nullable', 'integer', 'exists:capstone_experts,expert_id', 'different:chair_expert_id', 'different:member1_expert_id'],
        ]);

        $expertIds = array_values(array_filter([
            $validated['chair_expert_id'],
            $validated['member1_expert_id'] ?? null,
            $validated['member2_expert_id'] ?? null,
        ]));

        $excludedIds = $this->teamAdviserConsultantIds($team);
        $conflicted = array_intersect($expertIds, $excludedIds->all());

        if (! empty($conflicted)) {
            return response()->json([
                'success' => false,
                'message' => "This team's adviser or consultant can't also sit on their defense panel.",
            ], 422);
        }

        // Assigning a panel never touches the schedule itself - day/venue
        // are only ever set by generateScheduleForWindow(), triggered when
        // the admin saves a Defense Window. That's a deliberate two-step
        // workflow: assign panels for everyone first, then generate the
        // schedule once, rather than each assignment racing to grab a slot
        // before a window even exists.
        $existing = DefenseSchedule::where('team_id', $team->team_id)
            ->where('defense_type', $validated['defense_type'])
            ->with('panels')
            ->first();

        $sortedNewExpertIds = collect($expertIds)->sort()->values()->all();
        $previousExpertIds = $existing
            ? $existing->panels->pluck('expert_id')->sort()->values()->all()
            : [];
        $panelUnchanged = $existing && $previousExpertIds === $sortedNewExpertIds;

        $schedule = DefenseSchedule::updateOrCreate(
            ['team_id' => $team->team_id, 'defense_type' => $validated['defense_type']],
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

        return response()->json([
            'success' => true,
            'data' => $this->formatSchedule($schedule),
        ]);
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
        $defense->loadMissing('panels.expert', 'team');

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

        return response()->json([
            'success' => true,
            'data' => $this->formatSchedule($defense->fresh(['panels.expert'])),
        ]);
    }

    // Same two conflict types findSlotForPanel() avoids automatically:
    // a venue double-booked by a different panel in the same 2-hour
    // slot, and a panelist sitting on two different panels in that same
    // slot. Scoped to the exact slot (not the whole day) - since the day
    // is now split into 4 blocks, two different panels sharing a day is
    // fine as long as they're not in the same block. Scoped across every
    // defense_type (not just this team's own stage) since a room/person
    // conflict is real regardless of which stage it's for.
    private function conflictsForManualOverride(DefenseSchedule $defense, Carbon $slotStart, string $venue): array
    {
        $expertIds = $defense->panels->pluck('expert_id')->all();

        $sameSlotOthers = DefenseSchedule::where('schedule_date', $slotStart)
            ->where('defense_id', '!=', $defense->defense_id)
            ->with(['panels.expert', 'team'])
            ->get();

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

        return array_values(array_unique($conflicts));
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

        $scheduledCount = $this->generateScheduleForWindow($window);

        return response()->json([
            'success' => true,
            'data' => [
                'defense_type' => $window->defense_type,
                'start_date' => $window->start_date->toDateString(),
                'end_date' => $window->end_date->toDateString(),
            ],
            'scheduled_count' => $scheduledCount,
        ]);
    }

    // Assigning a panel to a team only ever clears its schedule to
    // unassigned (see store()) - this is the only place day/venue actually
    // get set, run once the admin saves a window covering everyone who's
    // already been given a panel for that defense stage.
    private function generateScheduleForWindow(DefenseWindow $window): int
    {
        $pending = DefenseSchedule::where('defense_type', $window->defense_type)
            ->whereNull('schedule_date')
            ->whereHas('panels', fn ($q) => $q->where('panel_role', 'Panel Chair'))
            ->with('panels')
            ->orderBy('defense_id')
            ->get();

        $scheduledCount = 0;

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
            $scheduledCount++;
        }

        return $scheduledCount;
    }

    // Deterministic, no AI - this is a room/person conflict problem (is
    // this venue or expert already booked in this 2-hour block), not a
    // language one, so plain constraint-checking guarantees the rules
    // hold every time instead of only "usually" holding.
    //
    // 1. If this exact panel (same expert set) already has a slot booked
    //    within this defense_type's window, reuse it - this is what lets
    //    one panel judge several teams back-to-back in the same block.
    // 2. Otherwise scan each day in the window, each of the day's four
    //    fixed 2-hour blocks (self::TIME_SLOTS), each venue in order, and
    //    return the first (date, time, venue) where no OTHER panel
    //    already holds that venue in that exact block and none of these
    //    experts already sit on a different panel in that exact block
    //    (any venue). Two different panels CAN share the same day, just
    //    not the same block.
    private function findSlotForPanel(DefenseWindow $window, array $expertIds, ?int $excludeScheduleId = null): array
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
            ->get();

        foreach ($schedulesInWindow->where('defense_type', $window->defense_type) as $existing) {
            $existingExpertIds = $existing->panels->pluck('expert_id')->sort()->values()->all();

            if ($existingExpertIds === $sortedExpertIds) {
                return [
                    'schedule_date' => $existing->schedule_date,
                    'end_date' => $existing->end_date,
                    'venue' => $existing->venue,
                ];
            }
        }

        foreach (CarbonPeriod::create($window->start_date, $window->end_date) as $date) {
            foreach (self::TIME_SLOTS as $time) {
                $slotStart = $date->copy()->setTimeFromTimeString($time);

                $sameSlot = $schedulesInWindow->filter(fn ($s) => $s->schedule_date->equalTo($slotStart));

                $conflictingPanelsThisSlot = $sameSlot->filter(
                    fn ($s) => $s->panels->pluck('expert_id')->sort()->values()->all() !== $sortedExpertIds
                );

                foreach (self::VENUES as $venue) {
                    $venueTaken = $conflictingPanelsThisSlot->contains(fn ($s) => $s->venue === $venue);

                    $expertConflict = $conflictingPanelsThisSlot->contains(
                        fn ($s) => count(array_intersect($sortedExpertIds, $s->panels->pluck('expert_id')->all())) > 0
                    );

                    if ($venueTaken || $expertConflict) {
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
