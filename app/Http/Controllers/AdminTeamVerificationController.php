<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminTeamVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('expert')->user();
        $adminSections = $admin->sections ?? [];

        $tab = $request->query('tab', 'pending');
        $selectedSection = $request->query('section');

        $pendingCandidates = Team::with('leader')
            ->where('team_status', 'complete')
            ->where('is_confirmed', false)
            ->orderByDesc('created_at')
            ->get();

        $verifiedCandidates = Team::with('leader')
            ->where('is_confirmed', true)
            ->orderByDesc('created_at')
            ->get();

        $syRecords = StudentSchoolYear::whereIn('team_id', $pendingCandidates->pluck('team_id')->merge($verifiedCandidates->pluck('team_id'))->unique())
            ->with('student')
            ->get()
            ->groupBy('team_id');

        $pending = $this->buildTeams($pendingCandidates, $syRecords, $adminSections, $selectedSection);
        $verified = $this->buildTeams($verifiedCandidates, $syRecords, $adminSections, $selectedSection);

        $active = $tab === 'verified' ? $verified : $pending;

        return view('admin.verify_teams', [
            'teams' => $active['teams'],
            'teamSections' => $active['teamSections'],
            'teamMembers' => $active['teamMembers'],
            'tab' => $tab,
            'selectedSection' => $selectedSection,
            'pendingCount' => $pending['count'],
            'verifiedCount' => $verified['count'],
            'allSections' => $adminSections,
        ]);
    }

    /**
     * @return array{teams: Collection<int, Team>, teamSections: array<int, string>, teamMembers: array<int, Collection<int, Student>>, count: int}
     */
    private function buildTeams($candidates, $syRecords, array $adminSections, ?string $selectedSection): array
    {
        $teams = collect();
        $teamSections = [];
        $teamMembers = [];

        foreach ($candidates as $team) {
            $records = $syRecords->get($team->team_id, collect());
            $members = $records->map(fn ($sy) => $sy->student)->filter()->values();

            $section = '';
            foreach ($records as $sy) {
                if ($sy->student) {
                    $sectionLabel = Student::computeSectionLabel($sy->student->course, $sy->student->year_section);
                    if ($sectionLabel) {
                        $section = $sectionLabel;
                        break;
                    }
                }
            }

            if (! in_array($section, $adminSections, true)) {
                continue;
            }

            if ($selectedSection && $section !== $selectedSection) {
                continue;
            }

            $teamSections[$team->team_id] = $section;
            $teamMembers[$team->team_id] = $members;
            $teams->push($team);
        }

        return [
            'teams' => $teams,
            'teamSections' => $teamSections,
            'teamMembers' => $teamMembers,
            'count' => $teams->count(),
        ];
    }

    public function verify(Request $request, Team $team): RedirectResponse
    {
        $admin = auth('expert')->user();
        $team->refresh();

        if ($team->team_status !== 'complete') {
            return redirect()->route('admin.verify_teams')
                ->with('error', 'Team is not marked as complete, so it cannot be verified yet.');
        }

        if ($team->is_confirmed) {
            return redirect()->route('admin.verify_teams')
                ->with('error', 'Team <strong>'.e($team->team_name).'</strong> is already verified.');
        }

        $adminSections = $admin->sections ?? [];

        if (! in_array($this->teamSection($team), $adminSections, true)) {
            return redirect()->route('admin.verify_teams')
                ->with('error', 'You can only verify teams assigned to your sections.');
        }

        $team->update(['is_confirmed' => true]);

        $this->logActivity(
            $admin->expert_id,
            'team_verified',
            'Verified team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id, 'verified_by' => $admin->expert_id]
        );

        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)
            ->pluck('student_id');

        foreach ($memberIds as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => 'team_verified',
                'title' => 'Team Verified',
                'message' => 'Your team <strong>'.e($team->team_name).'</strong> has been verified by the administrator!',
                'link' => route('student.team'),
            ]);
        }

        return redirect()->route('admin.verify_teams')
            ->with('status', 'Team <strong>'.e($team->team_name).'</strong> has been verified.');
    }

    private function teamSection(Team $team): string
    {
        $records = StudentSchoolYear::where('team_id', $team->team_id)
            ->with('student')
            ->get();

        foreach ($records as $sy) {
            if (! $sy->student) {
                continue;
            }

            $sectionLabel = Student::computeSectionLabel($sy->student->course, $sy->student->year_section);

            if ($sectionLabel) {
                return $sectionLabel;
            }
        }

        $sy = $records->first();

        return $sy->program ?? '';
    }
}
