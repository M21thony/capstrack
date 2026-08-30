<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaderWithTeam(array $teamOverrides = []): array
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create(array_merge([
            'team_name' => 'Original Name',
            'team_status' => 'on-going',
            'leader_id' => $student->student_id,
            'is_confirmed' => false,
            'member_count' => 5,
        ], $teamOverrides));

        return [$student, $team];
    }

    // Reaching "complete" now requires all 3 required Pre-Proposal
    // submissions (see PreProposalController::store()) - fills all 3 slots
    // with minimal valid data.
    private function fillAllThreeProposalSlots(Team $team, Student $student): void
    {
        $sy = StudentSchoolYear::firstOrCreate([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $check = DuplicationCheck::create([
                'student_s_y_id' => $sy->s_y_id,
                'document_path' => "duplication-checks/slot{$slot}.pdf",
                'concepts' => ['title' => 'Test'],
                'top_score' => 20,
                'verdict' => 'Passed',
                'matches' => [],
                'instructors_suggested' => [],
            ]);

            CapstonePreProposal::create([
                'team_id' => $team->team_id,
                'duplication_check_id' => $check->id,
                'slot' => $slot,
                'title' => "Slot {$slot} Smart Automated Irrigation Monitoring System Development",
                'sdg_goals' => 'SDG 4 – Quality Education',
                'research_agenda' => ['Applications and Systems Development'],
                'problem_statement' => 'A problem.',
                'proposed_solution' => 'A solution.',
                'scope' => 'A scope.',
                'limitations' => 'A limitation.',
                'sdm' => 'Agile Development Methodology',
                'duplication_status' => 'free',
                'submitted_at' => now(),
            ]);
        }
    }

    // Regression test: team_status is a nullable field, but the controller
    // used to access $validated['team_status'] directly without a null
    // coalesce, throwing "Undefined array key" whenever a student updated
    // their team without touching status (e.g. renaming only).
    public function test_web_update_without_team_status_does_not_error(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
            'project_title' => 'A short title',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('student.team'));

        $this->assertSame('Renamed Team', $team->fresh()->team_name);
    }

    public function test_api_update_without_team_status_does_not_error(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->putJson("/api/teams/{$team->team_id}", [
            'team_name' => 'Renamed Team',
            'project_title' => 'A short title',
        ]);

        $response->assertStatus(200);
        $this->assertSame('Renamed Team', $team->fresh()->team_name);
    }

    // Regression test: the API controller used to reassign $validated to
    // the result of a second, narrower validate() call when marking a team
    // complete, wiping out team_name/team_status/member_count that had
    // already been validated and causing either a crash or a silently
    // dropped status change.
    public function test_api_update_marking_complete_keeps_other_fields(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');
        $this->fillAllThreeProposalSlots($team, $student);

        $response = $this->putJson("/api/teams/{$team->team_id}", [
            'team_name' => 'Renamed Team',
            'project_title' => 'A short title',
            'team_status' => 'complete',
        ]);

        $response->assertStatus(200);

        $fresh = $team->fresh();
        $this->assertSame('Renamed Team', $fresh->team_name);
        $this->assertSame('complete', $fresh->team_status);
    }

    public function test_api_update_marking_complete_without_three_proposals_is_rejected(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->putJson("/api/teams/{$team->team_id}", [
            'team_name' => 'Renamed Team',
            'project_title' => 'A short title',
            'team_status' => 'complete',
        ]);

        $response->assertStatus(422);
        $this->assertSame('on-going', $team->fresh()->team_status);
    }

    public function test_web_update_marking_complete_without_three_proposals_is_rejected(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
            'project_title' => 'Too short',
            'team_status' => 'complete',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('on-going', $team->fresh()->team_status);
    }

    public function test_web_update_marking_complete_succeeds_with_all_three_proposals(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');
        $this->fillAllThreeProposalSlots($team, $student);

        $response = $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
            'project_title' => 'A short title',
            'team_status' => 'complete',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');
        $this->assertSame('complete', $team->fresh()->team_status);
    }
}
