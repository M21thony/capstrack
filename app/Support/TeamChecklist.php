<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Which checklist a team follows: the one its section's CaPSA keeps for the
 * program that section belongs to. A team has no checklist of its own.
 */
class TeamChecklist
{
    public function __construct(private SectionScope $sections) {}

    public function for(Team $team): ?Checklist
    {
        return $this->forSection($this->sections->sectionFor($team));
    }

    /**
     * The other direction: every student on every team that follows this
     * checklist. A checklist covers a whole program across every section its
     * CaPSA runs, so "who does this change reach" is the same question as
     * "which of this CaPSA's teams are in that program" - answered with the
     * same filter the team-management list uses, so the two cannot drift.
     *
     * @return Collection<int, Student>
     */
    public function studentsFollowing(Checklist $checklist): Collection
    {
        $capsa = $checklist->capsa;

        if (! $capsa) {
            return collect();
        }

        $syRecords = StudentSchoolYear::whereNotNull('team_id')->with('student')->get()->groupBy('team_id');
        $candidates = Team::whereIn('team_id', $syRecords->keys())->get();

        $filtered = $this->sections->filterTeams($candidates, $syRecords, $capsa->sections ?? []);

        $students = collect();

        foreach ($filtered['teams'] as $team) {
            $section = $filtered['teamSections'][$team->team_id];

            if (self::programOf($section) !== $checklist->program) {
                continue;
            }

            $students = $students->merge($filtered['teamMembers'][$team->team_id]);
        }

        return $students->unique('student_id')->values();
    }

    public function forSection(string $section): ?Checklist
    {
        $capsa = $this->capsaFor($section);

        return $capsa
            ? Checklist::where('capsa_id', $capsa->expert_id)->where('program', self::programOf($section))->oldest('id')->first()
            : null;
    }

    /** The CaPSA who handles a section, or null when nobody does. */
    public function capsaFor(string $section): ?CapstoneExpert
    {
        if ($section === '') {
            return null;
        }

        return CapstoneExpert::whereNotNull('sections')->orderBy('expert_id')->get()
            ->first(fn (CapstoneExpert $capsa) => in_array($section, $capsa->sections ?? [], true));
    }

    /** "BSIT-3A" is in BSIT. */
    public static function programOf(string $section): string
    {
        return strtoupper(explode('-', $section)[0]);
    }
}
