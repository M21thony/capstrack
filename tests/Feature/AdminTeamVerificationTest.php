<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminTeamVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(array $sections = ['BSCS-4A']): CapstoneExpert
    {
        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.verify@test.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_admin' => true,
            'approved_status' => true,
            'sections' => $sections,
        ]));

        $this->actingAs($admin, 'expert');

        return $admin;
    }

    private function createLeader(string $email = 'leader.verify@my.cspc.edu.ph'): Student
    {
        return Student::factory()->withCompleteProfile()->create([
            'email' => $email,
            'f_name' => 'Leader',
            'l_name' => 'One',
        ]);
    }

    private function createMember(): Student
    {
        return Student::create([
            'email' => 'member.verify@my.cspc.edu.ph',
            'f_name' => 'Member',
            'l_name' => 'One',
        ]);
    }

    private const PROJECT_TITLE = 'Intelligent Flood Monitoring and Early Warning System Using IoT Sensors for Rural Communities';

    private function createCompleteTeam(Student $leader, string $teamName = 'Team Verify'): Team
    {
        $team = Team::create([
            'team_name' => $teamName,
            'team_status' => 'complete',
            'is_confirmed' => false,
            'leader_id' => $leader->student_id,
            'project_title' => self::PROJECT_TITLE,
            'tags' => ['IOT', 'Machine Learning'],
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        return $team;
    }

    // verify() now requires the title question to already be resolved -
    // creates a minimal approved-and-selected pre-proposal for the team.
    private function selectFinalTitleForTeam(Team $team, Student $leader): CapstonePreProposal
    {
        $sy = StudentSchoolYear::firstOrCreate([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'duplication-checks/test.pdf',
            'concepts' => ['title' => 'Test'],
            'top_score' => 20,
            'verdict' => 'Passed',
            'matches' => [],
            'instructors_suggested' => [],
        ]);

        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => self::PROJECT_TITLE,
            'sdg_goals' => 'SDG 4 – Quality Education',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile Development Methodology',
            'duplication_status' => 'free',
            'approved_status' => true,
            'submitted_at' => now(),
        ]);

        $team->update(['selected_pre_proposal_id' => $proposal->id]);

        return $proposal;
    }

    private function updateTeam(Team $team, string $status): void
    {
        $this->put(route('team.update', $team->team_id), [
            'team_name' => $team->team_name,
            'project_title' => $team->project_title,
            'team_status' => $status,
            'tags' => json_encode(['IOT', 'Machine Learning']),
        ]);
    }

    public function test_admin_can_see_complete_unverified_teams_on_verification_page(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $onGoing = Team::create([
            'team_name' => 'Team Ongoing',
            'team_status' => 'on-going',
            'leader_id' => $leader->student_id,
        ]);

        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertSee($team->team_name);
        $response->assertDontSee($onGoing->team_name);
    }

    public function test_admin_can_verify_a_complete_team(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $member = $this->createMember();
        $team = $this->createCompleteTeam($leader);
        $this->selectFinalTitleForTeam($team, $leader);

        StudentSchoolYear::create([
            'student_id' => $member->student_id,
            'team_id' => $team->team_id,
        ]);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'student_id' => $leader->student_id,
            'type' => 'team_verified',
        ]);

        $this->assertDatabaseHas('notifications', [
            'student_id' => $member->student_id,
            'type' => 'team_verified',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'type' => 'team_verified',
            'description' => 'Verified team <strong>'.$team->team_name.'</strong>',
        ]);
    }

    public function test_admin_cannot_verify_a_team_not_marked_complete(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = Team::create([
            'team_name' => 'Team Not Complete',
            'team_status' => 'on-going',
            'leader_id' => $leader->student_id,
        ]);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => false,
        ]);
    }

    public function test_team_no_longer_ready_to_verify_after_status_changes_away_from_complete(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'team_status' => 'complete']);

        $this->actingAs($leader, 'web');
        $this->updateTeam($team, 'on-going');

        $team->refresh();
        $this->assertSame('on-going', $team->team_status);

        $this->actingAs($admin = CapstoneExpert::find(1), 'expert');
        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertSee('No teams awaiting verification');
    }

    public function test_team_becomes_ready_to_verify_again_after_changing_back_to_complete(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $this->actingAs($leader, 'web');
        $this->updateTeam($team, 'on-going');

        $this->updateTeam($team, 'complete');

        $this->actingAs($admin = CapstoneExpert::find(1), 'expert');
        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertSee($team->team_name);
    }

    public function test_student_confirm_route_was_removed(): void
    {
        $leader = $this->createLeader();
        $this->actingAs($leader, 'web');

        $team = $this->createCompleteTeam($leader);

        $response = $this->post('/team/confirm');

        $this->assertContains($response->status(), [404, 405], 'Confirm route should no longer exist');
        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => false,
        ]);
    }

    public function test_team_leader_sees_pending_verification_when_complete_but_unverified(): void
    {
        $leader = $this->createLeader();
        $this->actingAs($leader, 'web');

        $team = $this->createCompleteTeam($leader);

        $response = $this->get(route('student.team'));

        $response->assertOk();
        $response->assertSee('Pending Verification');
        $response->assertDontSee('Confirm Team');
    }

    public function test_team_leader_sees_verified_when_confirmed(): void
    {
        $leader = $this->createLeader();
        $this->actingAs($leader, 'web');

        $team = $this->createCompleteTeam($leader);
        $team->update(['is_confirmed' => true]);

        $response = $this->get(route('student.team'));

        $response->assertOk();
        $response->assertSee('Verified');
        $response->assertDontSee('Confirm Team');
    }

    public function test_admin_only_sees_teams_in_assigned_sections(): void
    {
        $this->createAdmin(['BSIT-3A']);

        $leaderA = $this->createLeader();
        $teamA = $this->createCompleteTeam($leaderA, 'Team BSCS');

        $leaderB = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.b@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $teamB = $this->createCompleteTeam($leaderB, 'Team BSIT');

        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertDontSee($teamA->team_name);
        $response->assertSee($teamB->team_name);
    }

    public function test_admin_cannot_verify_team_outside_assigned_sections(): void
    {
        $this->createAdmin(['BSIT-3A']);

        $leaderA = $this->createLeader();
        $teamA = $this->createCompleteTeam($leaderA, 'Team BSCS');

        $leaderB = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.b@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $teamB = $this->createCompleteTeam($leaderB, 'Team BSIT');
        $this->selectFinalTitleForTeam($teamB, $leaderB);

        $response = $this->post(route('admin.verify_teams.verify', $teamA->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('team', [
            'team_id' => $teamA->team_id,
            'is_confirmed' => false,
        ]);

        $response = $this->post(route('admin.verify_teams.verify', $teamB->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('team', [
            'team_id' => $teamB->team_id,
            'is_confirmed' => true,
        ]);
    }

    public function test_admin_without_assigned_sections_sees_no_teams(): void
    {
        $this->createAdmin([]);

        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertDontSee($team->team_name);
        $response->assertSee('No teams awaiting verification');
    }

    public function test_admin_without_assigned_sections_cannot_verify_team(): void
    {
        $this->createAdmin([]);

        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => false,
        ]);
    }

    public function test_admin_can_verify_team_when_logged_in_via_expert_guard(): void
    {
        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.guard@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Guard',
            'is_admin' => true,
            'approved_status' => true,
            'sections' => ['BSCS-4A'],
        ]));

        Auth::guard('expert')->login($admin);

        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $this->selectFinalTitleForTeam($team, $leader);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => true,
        ]);
    }

    public function test_verified_tab_shows_confirmed_teams_only(): void
    {
        $this->createAdmin();

        $leader = $this->createLeader();
        $pendingTeam = $this->createCompleteTeam($leader, 'Team Pending');

        $leaderB = $this->createLeader('leader.verified@my.cspc.edu.ph');
        $verifiedTeam = $this->createCompleteTeam($leaderB, 'Team Done');
        $verifiedTeam->update(['is_confirmed' => true]);

        $response = $this->get(route('admin.verify_teams', ['tab' => 'verified']));

        $response->assertOk();
        $response->assertSee($verifiedTeam->team_name);
        $response->assertDontSee($pendingTeam->team_name);
    }

    public function test_section_filter_limits_teams_in_both_tabs(): void
    {
        $this->createAdmin(['BSCS-4A', 'BSIT-3A']);

        $leaderA = $this->createLeader();
        $bscsTeam = $this->createCompleteTeam($leaderA, 'Team BSCS');

        $leaderB = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.bsit@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $bsitTeam = $this->createCompleteTeam($leaderB, 'Team BSIT');

        $response = $this->get(route('admin.verify_teams', ['section' => 'BSIT-3A']));

        $response->assertOk();
        $response->assertSee($bsitTeam->team_name);
        $response->assertDontSee($bscsTeam->team_name);
    }

    // Same DuplicationCheck+CapstonePreProposal setup as selectFinalTitleForTeam(),
    // but left pending (not pre-approved) so the approve/reject endpoints have
    // something real to act on.
    private function createPendingProposal(Team $team, Student $leader, int $slot, string $title): CapstonePreProposal
    {
        $sy = StudentSchoolYear::firstOrCreate([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => "duplication-checks/slot{$slot}.pdf",
            'concepts' => ['title' => $title],
            'top_score' => 20,
            'verdict' => 'Passed',
            'matches' => [],
            'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => $slot,
            'title' => $title,
            'sdg_goals' => 'SDG 4 – Quality Education',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile Development Methodology',
            'duplication_status' => 'free',
            'approved_status' => false,
            'submitted_at' => now(),
        ]);
    }

    public function test_approving_the_only_approved_proposal_auto_selects_it_and_syncs_title(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $proposal = $this->createPendingProposal($team, $leader, 1, self::PROJECT_TITLE);

        $response = $this->postJson(route('admin.verify_teams.pre_proposals.approve', $proposal->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $team->refresh();
        $this->assertSame($proposal->id, $team->selected_pre_proposal_id);
        $this->assertSame(self::PROJECT_TITLE, $team->project_title);

        $this->assertDatabaseHas('notifications', [
            'student_id' => $leader->student_id,
            'type' => 'title_approved',
        ]);
    }

    public function test_approving_two_proposals_does_not_auto_select_and_notifies_the_student_to_choose(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $proposal1 = $this->createPendingProposal($team, $leader, 1, self::PROJECT_TITLE);
        $proposal2 = $this->createPendingProposal($team, $leader, 2, 'Automated Smart Campus Energy Consumption Tracking and Optimization Dashboard System');

        $this->postJson(route('admin.verify_teams.pre_proposals.approve', $proposal1->id))->assertOk();
        $this->postJson(route('admin.verify_teams.pre_proposals.approve', $proposal2->id))->assertOk();

        $team->refresh();
        $this->assertNull($team->selected_pre_proposal_id);

        $this->assertDatabaseHas('notifications', [
            'student_id' => $leader->student_id,
            'type' => 'title_pick_required',
        ]);
    }

    public function test_rejecting_a_proposal_with_a_reason_sets_status(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $proposal = $this->createPendingProposal($team, $leader, 1, self::PROJECT_TITLE);

        // Empty-reason validation is enforced client-side in the modal (same
        // convention as AdminApprovalController's reject flow) - not
        // exercised here for the same reason AdminApprovalApiTest doesn't
        // either: this app's exception handler only renders JSON for
        // /api/* routes, so a server-side validation failure on this
        // web-group route doesn't produce a clean JSON response to assert on.
        $response = $this->postJson(route('admin.verify_teams.pre_proposals.reject', $proposal->id), [
            'reason' => 'Too similar to an existing capstone.',
        ]);

        $response->assertOk();
        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame('Too similar to an existing capstone.', $proposal->fresh()->rejection_reason);
    }

    public function test_verify_is_rejected_until_a_final_title_is_selected(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $this->createPendingProposal($team, $leader, 1, self::PROJECT_TITLE);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'is_confirmed' => false]);
    }

    public function test_verify_modal_surfaces_pre_proposal_content(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $this->createPendingProposal($team, $leader, 1, self::PROJECT_TITLE);

        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertSee('A problem.');
        $response->assertSee('Agile Development Methodology');
    }

    // "Include the submission of the full content of the Pre-proposal
    // form" - the CAPSA should be able to open the exact PDF the team
    // uploaded, not just the AI-extracted fields. Served inline (not as an
    // attachment) so it renders in the preview modal instead of forcing a
    // download.
    public function test_admin_can_view_the_original_pre_proposal_document_inline(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $proposal = $this->createPendingProposal($team, $leader, 1, self::PROJECT_TITLE);
        \Illuminate\Support\Facades\Storage::put($proposal->duplicationCheck->document_path, 'fake pdf contents');

        $response = $this->get(route('admin.verify_teams.pre_proposals.document', $proposal->id));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_download_document_404s_when_the_file_is_missing(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $proposal = $this->createPendingProposal($team, $leader, 1, self::PROJECT_TITLE);

        $response = $this->get(route('admin.verify_teams.pre_proposals.document', $proposal->id));

        $response->assertNotFound();
    }

    public function test_verify_modal_includes_a_document_link_for_each_proposal(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $proposal = $this->createPendingProposal($team, $leader, 1, self::PROJECT_TITLE);

        $response = $this->get(route('admin.verify_teams'));

        // The payload is embedded as an @json()-encoded string inside an
        // HTML attribute, so its slashes come out backslash-escaped just
        // like every other field in it - check for that literal fragment
        // rather than a plain URL string.
        $expectedFragment = str_replace('/', '\/', '/verify-teams/pre-proposals/'.$proposal->id.'/document');

        $response->assertOk();
        $response->assertSee($expectedFragment, false);
    }
}
