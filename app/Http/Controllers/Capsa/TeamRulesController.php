<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Setting;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\SectionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The rules every team is formed under: how large it may grow, and whether
 * students may start or join one at all.
 *
 * These were a row of number buttons on Team Management that saved the moment
 * one was clicked. A setting that decides who can join forty teams deserves a
 * page of its own, an explicit save, and a statement of what the change will
 * do before it does it.
 */
class TeamRulesController extends Controller
{
    // GET /capsa/team-rules - a tab on Team Management now; old links land on it.
    public function show(): RedirectResponse
    {
        return redirect()->route('capsa.team_management', ['tab' => 'rules']);
    }

    /**
     * What the Team Rules menu shows: one max per program this CaPSA handles,
     * since a CaPSA with BSIT and BSIS sections may allow each a different size.
     *
     * @return array{programs: array<int, array{program: string, sections: array<int, string>, max: int, sizes: array<int, int>}>, allowCreation: bool, allowJoining: bool}
     */
    public static function pageData(CapstoneExpert $capsa): array
    {
        $sections = $capsa->sections ?? [];
        $programs = [];

        foreach ($capsa->handledPrograms() as $program) {
            $own = array_values(array_filter($sections, fn ($s) => self::programOf($s) === $program));
            $programs[$program] = ['program' => $program, 'sections' => $own, 'max' => Team::maxMembers($own[0] ?? null), 'sizes' => []];
        }

        // How many of this CaPSA's teams have each headcount, per program, so
        // the menu can say who a lower limit reaches before saving.
        $scope = app(SectionScope::class);
        $records = StudentSchoolYear::whereNotNull('team_id')->with('student')->get()->groupBy('team_id');

        foreach (Team::whereIn('team_id', $records->keys())->get() as $team) {
            $members = $records->get($team->team_id);
            $section = $scope->sectionFor($team, $members);
            $program = self::programOf($section);

            if (in_array($section, $sections, true) && isset($programs[$program])) {
                $programs[$program]['sizes'][$members->count()] = ($programs[$program]['sizes'][$members->count()] ?? 0) + 1;
            }
        }

        return [
            'programs' => array_values($programs),
            'allowCreation' => Team::creationAllowed($sections[0] ?? null),
            'allowJoining' => Team::joiningAllowed($sections[0] ?? null),
        ];
    }

    /** "BSIT-3A" -> "BSIT". */
    private static function programOf(string $section): string
    {
        return strtoupper(trim(explode('-', $section)[0]));
    }

    /**
     * PUT /capsa/team-rules - all three rules together.
     *
     * One resource, replaced whole: the page always sends every rule, so a
     * save never leaves one half-applied beside the others.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        // Only the CaPSA's own sections: other CaPSAs set theirs.
        $sections = $capsa->sections ?? [];
        $programs = $capsa->handledPrograms();

        if ($programs === []) {
            return response()->json(['success' => false, 'message' => 'You have no sections to set rules for.'], 422);
        }

        $validated = $request->validate([
            'max_members' => ['required', 'array', 'required_array_keys:'.implode(',', $programs)],
            'max_members.*' => ['required', 'integer', 'min:'.Team::MIN_MEMBERS, 'max:'.Team::MAX_MEMBERS],
            'allow_creation' => ['required', 'boolean'],
            'allow_joining' => ['required', 'boolean'],
        ], [
            'max_members.*.min' => 'A capstone team is at least '.Team::MIN_MEMBERS.'.',
            'max_members.*.max' => 'A capstone team is at most '.Team::MAX_MEMBERS.'.',
        ]);

        foreach ($sections as $section) {
            $max = $validated['max_members'][self::programOf($section)] ?? null;

            if ($max === null) {
                continue;
            }

            Setting::set(Team::RULES_KEY.$section, json_encode([
                'max' => (int) $max,
                'create' => (bool) $validated['allow_creation'],
                'join' => (bool) $validated['allow_joining'],
            ]));
        }

        return response()->json([
            'success' => true,
            'max_members' => collect($programs)->mapWithKeys(fn ($p) => [$p => (int) $validated['max_members'][$p]])->all(),
            'allow_creation' => (bool) $validated['allow_creation'],
            'allow_joining' => (bool) $validated['allow_joining'],
            'message' => 'Team rules saved.',
        ]);
    }
}
