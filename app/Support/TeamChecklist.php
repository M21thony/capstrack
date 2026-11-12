<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Which checklist a team follows: the one its section's CaPSA/TSA keeps for the
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
     * CaPSA/TSA runs, so "who does this change reach" is the same question as
     * "which of this CaPSA/TSA's teams are in that program" - answered with the
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

            if ($this->forSection($section)?->id !== $checklist->id) {
                continue;
            }

            $students = $students->merge($filtered['teamMembers'][$team->team_id]);
        }

        return $students->unique('student_id')->values();
    }

    public function forSection(string $section): ?Checklist
    {
        $capsa = $this->capsaFor($section);

        if (! $capsa) {
            return null;
        }

        // A 3rd-year section follows this year's list, a 4th-year one the list
        // its batch started on last year; with neither, the oldest there is.
        [$from, $to] = array_map('intval', explode('-', Term::currentSchoolYear()));
        $batch = str_contains($section, '-4') ? ($from - 1).'-'.($to - 1) : $from.'-'.$to;

        return Checklist::where('capsa_id', $capsa->expert_id)->where('program', self::programOf($section))
            ->orderByRaw('school_year = ? desc', [$batch])->oldest('id')->first();
    }

    /**
     * A new school year: each CaPSA/TSA's list is copied for the new batch,
     * with no deadlines and nothing closed. Last year's list stays as it is
     * for the teams that started on it.
     */
    public static function startYear(string $from, string $to): void
    {
        foreach (Checklist::where('school_year', $from)->with('milestones.deliverables')->get() as $old) {
            if (Checklist::where(['capsa_id' => $old->capsa_id, 'program' => $old->program, 'school_year' => $to])->exists()) {
                continue;
            }

            $new = $old->replicate()->fill(['school_year' => $to]);
            $new->save();

            foreach ($old->milestones as $milestone) {
                $copy = $milestone->replicate()->fill([
                    'checklist_id' => $new->id,
                    'due_on' => null,
                    'closed_at' => null,
                    'opened_at' => in_array($milestone->name, MilestoneTemplate::OPEN_FROM_THE_START, true) ? now() : null,
                ]);
                $copy->save();

                foreach ($milestone->deliverables as $deliverable) {
                    $deliverable->replicate()->fill(['checklist_milestone_id' => $copy->id, 'due_date' => null, 'closed_at' => null])->save();
                }
            }
        }
    }

    /** The CaPSA/TSA who handles a section, or null when nobody does. */
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
