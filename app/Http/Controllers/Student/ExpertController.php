<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\TeamExpertAssignment;
use App\Support\SkillCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpertController extends Controller
{
    /** The roles a team engages, in the order the page offers them. */
    private const ROLES = ['adviser', 'consultant', 'grammarian'];

    public function index(Request $request)
    {
        $student = Auth::user();
        $team = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->first()?->team;

        // Only experts who take teams from this student's program - an
        // expert who accepts BSIS only shouldn't show up to a BSIT student.
        $studentProgram = Student::mapCourseToProgram($student->course ?? '');

        $assignments = collect();
        $isLeader = false;
        $roleAssignments = [
            'adviser' => null,
            'consultant' => null,
            'grammarian' => null,
        ];

        if ($team) {
            $isLeader = $team->leader_id === $student->student_id;
            $assignments = TeamExpertAssignment::where('team_id', $team->team_id)
                ->with(['expert.roles', 'expert.expertise', 'expertRole.role', 'preProposal'])
                ->get();

            // Keyed by the role the team actually engaged each expert as,
            // which is what expert_role_id records. This used to read
            // $a->expert->roles->first(), i.e. whichever role happened to
            // come back first out of everything the expert is qualified for.
            foreach ($assignments->where('status', 'approved') as $a) {
                $roleName = $a->engaged_role_name;
                if ($roleName && array_key_exists($roleName, $roleAssignments)) {
                    $roleAssignments[$roleName] = $a;
                }
            }
        }

        // The base list this student is allowed to see. Reused for both the
        // page of results and the skill dropdown, so the dropdown can only
        // ever offer skills that some visible expert actually has.
        $visible = fn () => CapstoneExpert::where('approved_status', true)
            ->acceptsProgram($studentProgram)
            ->whereHas('roles', function ($q) {
                $q->where('role_name', '!=', 'capstone_adviser');
            });

        // Skills are free text each expert types themselves, so the same one
        // arrives worded several ways and the roster runs to dozens of
        // distinct spellings. A flat list of all of them is unbrowsable, so
        // they are sorted into the curated areas in SkillCatalog - the same
        // grouped, searchable shape the "Make CaPSA" section picker uses -
        // with anything unrecognised kept under "Other skills" rather than
        // dropped. Within a group, the wording most experts use comes first.
        $inUse = $visible()
            ->join('expert_expertise', 'expert_expertise.expert_id', '=', 'capstone_experts.expert_id')
            ->whereRaw("TRIM(expert_expertise.expertise_field) <> ''")
            ->groupByRaw('LOWER(TRIM(expert_expertise.expertise_field))')
            ->orderByRaw('COUNT(DISTINCT capstone_experts.expert_id) DESC')
            ->orderByRaw('MIN(expert_expertise.expertise_field) ASC')
            // Aliased as skill_name, not name: these rows come back as
            // CapstoneExpert models, whose own `name` accessor would shadow
            // the column and hand back the expert's name instead.
            ->get([
                DB::raw('MIN(expert_expertise.expertise_field) as skill_name'),
                DB::raw('COUNT(DISTINCT capstone_experts.expert_id) as expert_count'),
            ]);

        // Only groups something is actually filed under are offered - an
        // empty group is a filter that could only ever return nothing.
        $skillGroups = collect();
        foreach (array_merge(SkillCatalog::groupNames(), [SkillCatalog::OTHER_GROUP]) as $groupName) {
            $items = $inUse->filter(fn ($row) => SkillCatalog::groupFor($row->skill_name) === $groupName)->values();

            if ($items->isNotEmpty()) {
                $skillGroups->put($groupName, $items);
            }
        }

        $activeSkill = trim((string) $request->query('skill', ''));
        $activeGroup = trim((string) $request->query('skill_group', ''));

        // A team needs one adviser, one consultant and one grammarian, so the
        // page shows one role at a time rather than everybody at once. The
        // role travels in the query string for the same reason the skill
        // does: the list is paginated, so choosing in the browser would only
        // ever filter the page you happen to be looking at.
        $activeRole = strtolower(trim((string) $request->query('role', '')));

        if (! in_array($activeRole, self::ROLES, true)) {
            $activeRole = '';
        }

        $search = trim((string) $request->query('q', ''));

        // A group only means something if it is one we actually offer.
        if ($activeGroup !== '' && ! $skillGroups->has($activeGroup)) {
            $activeGroup = '';
        }

        // Picking a whole area searches every skill filed under it, which is
        // the point of grouping: choose "Data, AI & Systems" without having
        // to know whether this expert wrote "Machine Learning" or "Data
        // Mining".
        $groupTerms = $activeGroup === ''
            ? []
            : $skillGroups->get($activeGroup, collect())->pluck('skill_name')->all();

        // Filtering happens in the query, not in the browser - the list is
        // paginated, so a client-side filter would only ever search the nine
        // experts on the current page and report "none" for the rest.
        //
        // Matching is a case-insensitive "contains", not an equals, which is
        // what makes the spelling drift survivable: "web" finds every expert
        // whose wording contains it, however they phrased the rest.
        $matchesSkill = function ($sub, array $terms) {
            $sub->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    // '!' rather than the usual backslash: MySQL processes
                    // backslash escapes inside string literals, so an
                    // ESCAPE clause naming a backslash does not even parse
                    // there, while SQLite has no default escape character at
                    // all and needs one named. '!' is ordinary to both.
                    $q->orWhereRaw("LOWER(expertise_field) LIKE ? ESCAPE '!'", [
                        '%'.$this->escapeLike(mb_strtolower($term)).'%',
                    ]);
                }
            });
        };

        // Typed words, each of which has to match something - a part of the
        // name, or an expertise entry. Words are ANDed, so "mark web" finds
        // the one person rather than everyone called Mark plus everyone who
        // does web.
        //
        // Columns are matched one at a time rather than concatenated:
        // SQLite, which the tests run on, has no CONCAT_WS.
        $matchesSearch = function ($q) use ($search) {
            foreach (preg_split('/\\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $like = '%'.$this->escapeLike(mb_strtolower($word)).'%';

                $q->where(function ($w) use ($like) {
                    $w->whereRaw("LOWER(first_name) LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("LOWER(last_name) LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("LOWER(COALESCE(middle_name, '')) LIKE ? ESCAPE '!'", [$like])
                        ->orWhereHas('expertise', function ($e) use ($like) {
                            $e->whereRaw("LOWER(expertise_field) LIKE ? ESCAPE '!'", [$like]);
                        });
                });
            }
        };

        // Everything except the role, so the count on each role card and the
        // list itself are filtered identically - the number on the card is
        // then exactly what choosing it shows.
        $filtered = fn () => $visible()
            ->when($activeSkill !== '', function ($q) use ($activeSkill, $matchesSkill) {
                $q->whereHas('expertise', fn ($sub) => $matchesSkill($sub, [$activeSkill]));
            })
            ->when($activeSkill === '' && $groupTerms !== [], function ($q) use ($groupTerms, $matchesSkill) {
                $q->whereHas('expertise', fn ($sub) => $matchesSkill($sub, $groupTerms));
            })
            ->when($search !== '', $matchesSearch);

        $roleCounts = [];
        foreach (self::ROLES as $roleName) {
            $roleCounts[$roleName] = $filtered()
                ->whereHas('roles', fn ($q) => $q->where('role_name', $roleName))
                ->count();
        }

        // With no role asked for, open on the first slot the team has not
        // filled - that is the one they came here to fill.
        if ($activeRole === '') {
            foreach (self::ROLES as $roleName) {
                if (($roleAssignments[$roleName] ?? null) === null) {
                    $activeRole = $roleName;
                    break;
                }
            }

            $activeRole = $activeRole ?: self::ROLES[0];
        }

        // 24 rather than 9: the page shows one role at a time now, and the
        // card strip pages through what it is handed without the screen
        // growing, so a whole role normally arrives in a single request.
        $experts = $filtered()
            ->whereHas('roles', fn ($q) => $q->where('role_name', $activeRole))
            ->with(['roles' => function ($q) {
                $q->where('role_name', '!=', 'capstone_adviser');
            }, 'expertise', 'teamAssignments' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->paginate(24)
            ->withQueryString()
            ->through(function ($expert) {
                $expert->slots_used = $expert->teamAssignments->count();
                unset($expert->teamAssignments);

                return $expert;
            });

        return view('student.experts', [
            'experts' => $experts,
            'assignments' => $assignments,
            'team' => $team,
            // The pre-proposal the CaPSA settled on. When a team has one,
            // applying does not need a PDF - that document already went
            // through the CaPSA, and asking for it again only invited a
            // different, unapproved file to be attached instead.
            'approvedProposal' => $team?->selected_pre_proposal_id
                ? CapstonePreProposal::find($team->selected_pre_proposal_id)
                : null,
            'isLeader' => $isLeader,
            'teamConfirmed' => $team?->is_confirmed ?? false,
            'skillGroups' => $skillGroups,
            'activeSkill' => $activeSkill,
            'activeGroup' => $activeGroup,
            'activeRole' => $activeRole,
            'search' => $search,
            'roleCounts' => $roleCounts,
        ]);
    }

    /**
     * Neutralise LIKE's own wildcards so a skill containing % or _ is
     * searched for literally rather than matching everything. Pairs with the
     * ESCAPE '!' clause above, so '!' has to escape itself too.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
