<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Works out which section a team belongs to, and whether a given CaPSA/TSA is
 * responsible for it.
 *
 * A CaPSA/TSA covers named sections ("BSIT-3A"), but a team has no section of
 * its own - it is its leader's, or failing that the first member's with a
 * complete enough profile to compute one from (see sectionFor()). That inference has to give the same answer
 * everywhere: a listing page and the action behind it disagreeing is what
 * once made teams appear verifiable and then be rejected on submit. This
 * class is the single copy so they cannot drift.
 */
class SectionScope
{
    /** Shown in place of a section when no member has a complete profile. */
    public const UNASSIGNED = 'Unassigned (incomplete profile)';

    /**
     * The section label for a team, or '' when none can be computed.
     *
     * The leader's section. A team forms inside one section in 3rd year, but
     * by 4th year its members can be split across sections - 4A and 4B - and
     * still be one team finishing one capstone. Taking whichever member came
     * back first gave such a team a different section from one page to the
     * next, and with it a different CaPSA/TSA, checklist and grading list. The
     * leader answers it the same way everywhere; the other members, in the
     * order they joined, only stand in when the leader's profile has no
     * section.
     *
     * @param  Collection<int, StudentSchoolYear>|null  $records  Pre-loaded rows, to avoid a query per team in a list.
     */
    public function sectionFor(Team $team, ?Collection $records = null): string
    {
        $records ??= StudentSchoolYear::where('team_id', $team->team_id)->with('student')->get();

        $records = $records
            ->sortBy(fn ($sy) => [(int) $sy->student_id === (int) $team->leader_id ? 0 : 1, (int) $sy->getKey()])
            ->values();

        foreach ($records as $sy) {
            if (! $sy->student) {
                continue;
            }

            $label = Student::computeSectionLabel($sy->student->course, $sy->student->year_section);

            if ($label) {
                return $label;
            }
        }

        return '';
    }

    /**
     * Whether this CaPSA/TSA may act on a team.
     *
     * A team with no computable section is allowed through deliberately.
     * Since the forced personal-info onboarding step was removed for new
     * sign-ins, that is a reachable state, and hiding such a team from
     * everyone would leave it permanently unverifiable with nobody able to
     * fix it. It is shown to every CaPSA/TSA, distinctly labelled, instead.
     */
    public function covers(array $capsaSections, string $section): bool
    {
        return $section === '' || in_array($section, $capsaSections, true);
    }

    /**
     * Filter teams down to the ones a CaPSA/TSA is responsible for.
     *
     * @param  Collection<int, Team>  $candidates
     * @param  Collection<int, Collection<int, StudentSchoolYear>>  $syRecords  Keyed by team_id.
     * @return array{teams: Collection<int, Team>, teamSections: array<int, string>, teamMembers: array<int, Collection<int, Student>>, count: int}
     */
    public function filterTeams(
        Collection $candidates,
        Collection $syRecords,
        array $capsaSections,
        ?string $selectedSection = null,
    ): array {
        $teams = collect();
        $teamSections = [];
        $teamMembers = [];

        foreach ($candidates as $team) {
            $records = $syRecords->get($team->team_id, collect());
            $section = $this->sectionFor($team, $records);

            if (! $this->covers($capsaSections, $section)) {
                continue;
            }

            if ($section !== '' && $selectedSection && $section !== $selectedSection) {
                continue;
            }

            $teamSections[$team->team_id] = $section !== '' ? $section : self::UNASSIGNED;
            $teamMembers[$team->team_id] = $records->map(fn ($sy) => $sy->student)->filter()->values();
            $teams->push($team);
        }

        return [
            'teams' => $teams,
            'teamSections' => $teamSections,
            'teamMembers' => $teamMembers,
            'count' => $teams->count(),
        ];
    }
}
