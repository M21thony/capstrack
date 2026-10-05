<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CapstoneDocument;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a team page shows besides its row: the panel, the activity feed and
 * the three expert seats. Shared by the CaPSA's Team Management and an
 * expert's My Teams, which show the same page.
 */
trait ShowsTeamPage
{
    /**
     * The panel of the team's latest defense that has one.
     *
     * @return object{panelists: Collection<int, DefensePanel>}|null
     */
    private function panelFor(Team $team): ?object
    {
        $schedule = DefenseSchedule::where('team_id', $team->team_id)
            ->whereHas('panels')
            ->with('panels.expert')
            ->orderByDesc('schedule_date')
            ->first();

        if (! $schedule) {
            return null;
        }

        return (object) [
            'panelists' => $schedule->panels->filter(fn (DefensePanel $panel) => $panel->expert)->values(),
        ];
    }

    /**
     * What happened on this team, newest first: files sent, titles turned in,
     * defenses held, and the day it was formed. Read off the records
     * themselves, so there is no separate log to keep in step.
     *
     * @return Collection<int, object{who: string, student: ?object, did: string, at: Carbon}>
     */
    private function activityFor(object $row): Collection
    {
        $team = $row->team;
        $members = $row->members->keyBy('student_id');
        $nameOf = fn ($student) => $student ? trim(($student->f_name ?? '').' '.($student->l_name ?? '')) : 'A former member';
        $event = fn (?object $student, string $who, string $did, $at) => (object) [
            'who' => $who, 'student' => $student, 'did' => $did, 'at' => Carbon::parse($at),
        ];

        $files = CapstoneDocument::ofTeam($team->team_id)
            ->get(['student_id', 'file_name', 'chapter', 'file_version', 'created_at'])
            ->filter(fn ($file) => $file->created_at)
            ->map(fn ($file) => $event(
                $members->get($file->student_id),
                $nameOf($members->get($file->student_id)),
                'sent '.($file->chapter ?: $file->file_name).' · v'.$file->file_version,
                $file->created_at,
            ));

        $titles = $row->proposals->map(fn ($proposal) => $event(
            null, 'The team', 'turned in title #'.$proposal->slot, $proposal->submitted_at,
        ));

        $defenses = DefenseSchedule::where('team_id', $team->team_id)
            ->where('schedule_date', '<=', now())
            ->get()
            ->map(fn ($schedule) => $event(null, 'The team', 'had its '.$schedule->defense_type, $schedule->schedule_date));

        $formed = $team->created_at
            ? [$event($members->get($team->leader_id), $nameOf($members->get($team->leader_id)), 'formed the team', $team->created_at)]
            : [];

        return $files->concat($titles)->concat($defenses)->concat($formed)
            ->sortByDesc(fn ($e) => $e->at->getTimestamp())
            ->values();
    }

    /**
     * The three roles a team engages, always all three and always in order,
     * so an empty one is visibly empty rather than missing.
     *
     * @return array<int, object>
     */
    private function expertCards(object $row): array
    {
        return collect(['adviser', 'consultant', 'grammarian'])
            ->map(function (string $role) use ($row) {
                $expert = $row->engagedRoles->get($role);

                return (object) [
                    'role' => $role,
                    'expert' => $expert,
                    'degree' => $expert?->degreeLabel(),
                    // Their research areas for the role this team engaged them as.
                    'expertise' => $expert ? $expert->expertiseFor($role) : collect(),
                ];
            })
            ->all();
    }
}
