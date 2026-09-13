<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

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

    /** What the Team Rules tab shows. */
    public static function pageData(): array
    {
        return [
            'maxMembers' => Team::maxMembers(),
            'allowCreation' => Team::creationAllowed(),
            'allowJoining' => Team::joiningAllowed(),
            // How many teams have each headcount, so the page can say who a
            // lower limit reaches before the CaPSA commits to it.
            'teamSizes' => StudentSchoolYear::query()
                ->whereNotNull('team_id')
                ->selectRaw('team_id, count(*) as members')
                ->groupBy('team_id')
                ->pluck('members')
                ->countBy()
                ->all(),
        ];
    }

    /**
     * PUT /capsa/team-rules - all three rules together.
     *
     * One resource, replaced whole: the page always sends every rule, so a
     * save never leaves one half-applied beside the others.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'max_members' => ['required', 'integer', 'min:'.Team::MIN_MEMBERS, 'max:'.Team::MAX_MEMBERS],
            'allow_creation' => ['required', 'boolean'],
            'allow_joining' => ['required', 'boolean'],
        ], [
            'max_members.min' => 'A capstone team is at least '.Team::MIN_MEMBERS.'.',
            'max_members.max' => 'A capstone team is at most '.Team::MAX_MEMBERS.'.',
        ]);

        Setting::set('max_team_members', (string) $validated['max_members']);
        Setting::set('allow_team_creation', $validated['allow_creation'] ? '1' : '0');
        Setting::set('allow_team_joining', $validated['allow_joining'] ? '1' : '0');

        return response()->json([
            'success' => true,
            'max_members' => Team::maxMembers(),
            'allow_creation' => Team::creationAllowed(),
            'allow_joining' => Team::joiningAllowed(),
            'message' => 'Team rules saved.',
        ]);
    }
}
