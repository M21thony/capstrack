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
            'team_status' => 'Active',
            'is_confirmed' => false,
            'leader_id' => $leader->student_id,
            'project_title' => self::PROJECT_TITLE,
            'tags' => ['IOT', 'Machine Learning'],
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        // What actually puts a team in front of the CAPSA is having turned
        // its pre-proposals in - the status string alone no longer does.
        $this->submittedProposalFor($team, $leader);

        return $team;
    }

    /**
     * A turned-in pre-proposal for this team, created or upgraded in place.
     *
     * Shared by createCompleteTeam() and selectFinalTitleForTeam() so both
     * work on the same slot-1 proposal instead of colliding on the
     * (team_id, slot) unique key.
     */
    private function submittedProposalFor(Team $team, Student $leader, array $overrides = []): CapstonePreProposal
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

        return CapstonePreProposal::updateOrCreate(
            ['team_id' => $team->team_id, 'slot' => 1],
            array_merge([
                'duplication_check_id' => $check->id,
                'title' => self::PROJECT_TITLE,
                'sdg_goals' => 'SDG 4 - Quality Education',
                'research_agenda' => ['Applications and Systems Development'],
                'problem_statement' => 'A problem.',
                'proposed_solution' => 'A solution.',
                'scope' => 'A scope.',
                'limitations' => 'A limitation.',
                'sdm' => 'Agile Development Methodology',
                'duplication_status' => 'free',
                'approved_status' => false,
                'submitted_at' => now(),
            ], $overrides),
        );
    }

    // verify() now requires the title question to already be resolved -
    // creates a minimal approved-and-selected pre-proposal for the team.
    private function selectFinalTitleForTeam(Team $team, Student $leader): CapstonePreProposal
    {
        $proposal = $this->submittedProposalFor($team, $leader, ['approved_status' => true]);

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
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

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
            'team_status' => 'Active',
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

    // A team waits for verification because it turned its pre-proposals in,
    // so unsubmitting - not a team_status change - is what withdraws it.
    public function test_unsubmitting_takes_a_team_out_of_the_verification_queue(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $this->actingAs($leader, 'web');
        $this->post(route('student.pre-proposal.unsubmit'))->assertSessionHas('status');

        $this->assertSame(0, $team->preProposals()->whereNotNull('submitted_at')->count());

        $this->actingAs(CapstoneExpert::find(1), 'expert');
        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

        $response->assertOk();
        $response->assertSee('No teams awaiting verification');
    }

    public function test_resubmitting_puts_a_team_back_in_the_verification_queue(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $this->actingAs($leader, 'web');
        $this->post(route('student.pre-proposal.unsubmit'));

        // Submitting needs the minimum number of titles kept, so top the set
        // back up before turning it in again.
        $this->submittedProposalFor($team, $leader, ['submitted_at' => null]);

        // Up to whatever submitting currently asks for, rather than a fixed
        // three - the minimum is a rule that moves.
        foreach (range(2, \App\Http\Controllers\PreProposalController::MINIMUM_PROPOSALS) as $slot) {
            $this->createPendingProposal($team, $leader, $slot, "Candidate Title Number {$slot} For This Team To Consider Fully");
        }

        $team->preProposals()->update(['submitted_at' => null]);

        $this->post(route('student.pre-proposal.submit'))->assertSessionHas('status');

        $this->actingAs(CapstoneExpert::find(1), 'expert');
        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

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

        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

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

        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

        $response->assertOk();
        $response->assertDontSee($team->team_name);
        $response->assertSee('You have no sections assigned yet');
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

    // The Verification tab carries both states at once, under their own
    // headings, rather than the two separate tabs Team Verification used -
    // splitting them meant clicking away to answer "did I already do that
    // one?". What matters is that each team lands under the right heading.
    public function test_verification_separates_teams_awaiting_from_teams_already_verified(): void
    {
        $this->createAdmin();

        $leader = $this->createLeader();
        $pendingTeam = $this->createCompleteTeam($leader, 'Team Pending');

        $leaderB = $this->createLeader('leader.verified@my.cspc.edu.ph');
        $verifiedTeam = $this->createCompleteTeam($leaderB, 'Team Done');
        $verifiedTeam->update(['is_confirmed' => true]);

        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

        $response->assertOk();

        $html = $response->getContent();
        $awaitingAt = strpos($html, 'Awaiting verification');
        $verifiedAt = strpos($html, '>Verified<');

        $this->assertNotFalse($awaitingAt);
        $this->assertNotFalse($verifiedAt);

        // Each name falls between the heading it belongs under and the next.
        $this->assertGreaterThan($awaitingAt, strpos($html, $pendingTeam->team_name));
        $this->assertLessThan($verifiedAt, strpos($html, $pendingTeam->team_name));
        $this->assertGreaterThan($verifiedAt, strpos($html, $verifiedTeam->team_name));
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

        $response = $this->get(route('admin.team_management', ['tab' => 'verification', 'section' => 'BSIT-3A']));

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

        // updateOrCreate, not create: createCompleteTeam() already puts a
        // turned-in proposal in slot 1, and a test asking for slot 1 here
        // means "make that one look like this", not "add a second".
        return CapstonePreProposal::updateOrCreate([
            'team_id' => $team->team_id,
            'slot' => $slot,
        ], [
            'duplication_check_id' => $check->id,
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

        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

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

        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

        // The payload is embedded as an @json()-encoded string inside an
        // HTML attribute, so its slashes come out backslash-escaped just
        // like every other field in it - check for that literal fragment
        // rather than a plain URL string.
        $expectedFragment = str_replace('/', '\/', '/verify-teams/pre-proposals/'.$proposal->id.'/document');

        $response->assertOk();
        $response->assertSee($expectedFragment, false);
    }

    // -- The title is not settled until the review is over -----------------

    /** Three submitted titles for one team, none of them decided yet. */
    private function threeSubmittedTitles(Team $team, Student $leader): array
    {
        return collect(['First Title', 'Second Title', 'Third Title'])
            ->map(fn ($title, $i) => $this->createPendingProposal($team, $leader, $i + 1, $title))
            ->all();
    }

    /**
     * Approving one while the others are still unread used to hand the team
     * a final title. The CaPSA is mid-review; nothing is decided yet.
     */
    public function test_approving_one_title_settles_nothing_while_others_are_unreviewed(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $team->update(['project_title' => null]);
        [$first] = $this->threeSubmittedTitles($team, $leader);

        $this->postJson(route('admin.verify_teams.pre_proposals.approve', $first))->assertOk();

        $team->refresh();
        $this->assertNull($team->project_title, 'The team was given a title while two were still unreviewed.');
        $this->assertNull($team->selected_pre_proposal_id);
    }

    /** Once every title has a decision and only one got through, it wins. */
    public function test_the_title_settles_when_the_last_one_is_decided(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $team->update(['project_title' => null]);
        [$first, $second, $third] = $this->threeSubmittedTitles($team, $leader);

        $this->postJson(route('admin.verify_teams.pre_proposals.approve', $first))->assertOk();
        $this->postJson(route('admin.verify_teams.pre_proposals.reject', $second), ['reason' => 'Too broad'])->assertOk();

        $this->assertNull($team->fresh()->project_title, 'Settled with one still undecided.');

        $this->postJson(route('admin.verify_teams.pre_proposals.reject', $third), ['reason' => 'Duplicate'])->assertOk();

        $team->refresh();
        $this->assertSame('First Title', $team->project_title);
        $this->assertSame($first->id, $team->selected_pre_proposal_id);
    }

    /**
     * Two approved means the team picks - and the title has to come off the
     * team, not just the selection. Leaving it is how an approved-but-not-
     * chosen title kept showing on student/team.
     */
    public function test_two_approved_titles_leave_the_team_with_no_title_until_they_pick(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $team->update(['project_title' => null]);
        [$first, $second, $third] = $this->threeSubmittedTitles($team, $leader);

        $this->postJson(route('admin.verify_teams.pre_proposals.approve', $first))->assertOk();
        $this->postJson(route('admin.verify_teams.pre_proposals.approve', $second))->assertOk();
        $this->postJson(route('admin.verify_teams.pre_proposals.reject', $third), ['reason' => 'No'])->assertOk();

        $team->refresh();
        $this->assertNull($team->project_title, 'The first approval is still showing as the team title.');
        $this->assertNull($team->selected_pre_proposal_id);
    }

    /** Everything rejected leaves no title behind either. */
    public function test_rejecting_every_title_leaves_the_team_without_one(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        [$first, $second, $third] = $this->threeSubmittedTitles($team, $leader);

        foreach ([$first, $second, $third] as $p) {
            $this->postJson(route('admin.verify_teams.pre_proposals.reject', $p), ['reason' => 'No'])->assertOk();
        }

        $team->refresh();
        $this->assertNull($team->project_title);
        $this->assertNull($team->selected_pre_proposal_id);
    }

    /**
     * The modal used to be refreshed by reloading the page, which closed it -
     * so a CaPSA with three titles reopened it after every decision. The
     * endpoints hand back what the modal needs to redraw itself instead.
     */
    public function test_a_decision_returns_what_the_modal_needs_to_redraw(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);
        $team->update(['project_title' => null]);
        [$first, $second, $third] = $this->threeSubmittedTitles($team, $leader);

        $response = $this->postJson(route('admin.verify_teams.pre_proposals.approve', $first))->assertOk();

        $response->assertJsonPath('success', true);
        $response->assertJsonCount(3, 'proposals');
        $response->assertJsonPath('proposals.0.status', 'approved');
        // Still mid-review, so no title and nothing to verify yet.
        $response->assertJsonPath('project_title', null);
        $response->assertJsonPath('selected_pre_proposal_id', null);

        $this->postJson(route('admin.verify_teams.pre_proposals.reject', $second), ['reason' => 'Too broad'])
            ->assertOk()
            ->assertJsonPath('proposals.1.status', 'rejected')
            ->assertJsonPath('proposals.1.rejection_reason', 'Too broad');

        $this->postJson(route('admin.verify_teams.pre_proposals.reject', $third), ['reason' => 'Duplicate'])
            ->assertOk()
            ->assertJsonPath('project_title', 'First Title')
            ->assertJsonPath('selected_pre_proposal_id', $first->id);
    }
}
