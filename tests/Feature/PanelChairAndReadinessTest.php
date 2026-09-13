<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefensePanel;
use App\Models\DuplicationCheck;
use App\Models\ExpertExpertise;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two rules before a CaPSA can assign a panel: the team has its adviser,
 * consultant and grammarian, and the Panel Chair holds a Master's or higher.
 * And suggested teams are the ones whose project meets the panel's expertise.
 */
class PanelChairAndReadinessTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
    }

    private function team(string $name = 'Ready Team', string $title = 'A Capstone Project', bool $staffed = true, array $staff = Team::REQUIRED_EXPERT_ROLES): Team
    {
        $team = Team::create([
            'team_name' => $name, 'project_title' => $title, 'tags' => [],
            'team_status' => 'Active', 'is_confirmed' => true, 'member_count' => 1,
        ]);

        foreach ($staffed ? $staff : [] as $roleName) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            $role = Role::firstOrCreate(['role_name' => $roleName]);
            $expert->roles()->attach($role->role_id);

            TeamExpertAssignment::create([
                'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
            ]);
        }

        return $team;
    }

    private function assign(Team $team, CapstoneExpert $chair)
    {
        return $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $chair->expert_id,
            ]);
    }

    // ── the team must be ready ───────────────────────────────────────────

    public function test_a_team_without_its_grammarian_cannot_be_given_a_panel(): void
    {
        $team = $this->team(staff: ['adviser', 'consultant']);

        $this->assign($team, CapstoneExpert::factory()->create(['approved_status' => true]))
            ->assertStatus(422)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'still needs a grammarian'))->etc());

        $this->assertSame(0, DefensePanel::count());
    }

    public function test_a_team_with_nobody_yet_is_told_every_role_it_needs(): void
    {
        $team = $this->team(staffed: false);

        $this->assertSame(['adviser', 'consultant', 'grammarian'], $team->missingExpertRoles());

        $this->assign($team, CapstoneExpert::factory()->create(['approved_status' => true]))
            ->assertStatus(422)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'an adviser, a consultant and a grammarian'))->etc());
    }

    /** A request not yet accepted is not a role filled. */
    public function test_a_pending_assignment_does_not_count(): void
    {
        $team = $this->team();
        TeamExpertAssignment::where('team_id', $team->team_id)->first()->update(['status' => 'pending']);

        $this->assertCount(1, $team->missingExpertRoles());
    }

    public function test_bulk_assignment_skips_an_unready_team_and_says_why(): void
    {
        $ready = $this->team('Ready');
        $unready = $this->team('Unready', staffed: false);
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);

        $response = $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.store.bulk'), [
                'team_ids' => [$ready->team_id, $unready->team_id],
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $chair->expert_id,
            ])
            ->assertOk();

        $this->assertSame([$ready->team_id], collect($response->json('data.assigned'))->pluck('team_id')->all());
        $this->assertStringContainsString('still needs', $response->json('data.skipped.0.reason'));
    }

    public function test_the_page_knows_which_teams_are_not_ready(): void
    {
        $unready = $this->team('Unready', staff: ['adviser']);

        $this->actingAs($this->capsa, 'expert')
            ->get(route('capsa.panel_assignment', ['defense_type' => 'Pre-proposal Defense']))
            ->assertOk()
            ->assertViewHas('teamsForJs', fn ($teams) => collect($teams)->firstWhere('team_id', $unready->team_id)['missing_roles'] === ['consultant', 'grammarian']);
    }

    // ── the chair's degree ───────────────────────────────────────────────

    public function test_a_chair_without_a_masters_is_refused(): void
    {
        foreach (['bachelor', null] as $degree) {
            $chair = CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => $degree]);

            $this->assign($this->team('Team '.($degree ?? 'none')), $chair)
                ->assertStatus(422)
                ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, "needs a Master's degree or higher"))->etc());
        }

        $this->assertSame(0, DefensePanel::count());
    }

    public function test_a_masters_or_doctorate_may_chair(): void
    {
        foreach (['master', 'doctorate'] as $degree) {
            $chair = CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => $degree]);
            $this->assign($this->team('Team '.$degree), $chair)->assertOk();
        }

        $this->assertSame(2, DefensePanel::where('panel_role', 'Panel Chair')->count());
    }

    /** Only the chair needs the degree - members do not. */
    public function test_a_panel_member_needs_no_masters(): void
    {
        $chair = CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => 'master']);
        $member = CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => 'bachelor']);

        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $this->team()->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $chair->expert_id,
                'member1_expert_id' => $member->expert_id,
            ])
            ->assertOk();
    }

    public function test_the_capsa_records_a_degree(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => null]);

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('capsa.experts.degree.update', $expert), ['highest_degree' => 'doctorate'])
            ->assertOk()
            ->assertJson(['can_chair' => true]);

        $this->assertTrue($expert->fresh()->canChairPanel());

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('capsa.experts.degree.update', $expert), ['highest_degree' => 'phd-ish'])
            ->assertStatus(422);
    }

    public function test_an_expert_records_their_own_degree(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => null]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);
        $program = CapstoneExpert::PROGRAMS[0];

        $this->actingAs($expert, 'expert')
            ->putJson(route('instructor.profile.update'), [
                'program_slots' => [$program => 2], 'expertise' => [], 'highest_degree' => 'master',
            ])
            ->assertOk();

        $this->assertSame('master', $expert->fresh()->highest_degree);

        // A save that does not mention the degree leaves it alone.
        $this->actingAs($expert, 'expert')
            ->putJson(route('instructor.profile.update'), ['program_slots' => [$program => 2], 'expertise' => []])
            ->assertOk();

        $this->assertSame('master', $expert->fresh()->highest_degree);
    }

    // ── suggested teams ──────────────────────────────────────────────────

    private function expertIn(string $field): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        ExpertExpertise::create(['expert_id' => $expert->expert_id, 'expertise_field' => $field]);

        return $expert;
    }

    private function suggestFor(CapstoneExpert $expert): array
    {
        return collect($this->actingAs($this->capsa, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest-teams', ['expert_ids' => [$expert->expert_id]]))
            ->assertOk()
            ->json('data'))->pluck('team_name')->all();
    }

    public function test_a_team_is_suggested_when_its_title_meets_the_panels_expertise(): void
    {
        $this->team('Chain Team', 'Blockchain Ledger For Barangay Certificate Requests');
        $this->team('Farm Team', 'Crop Yield Monitoring For Rice Farmers');

        $this->assertSame(['Chain Team'], $this->suggestFor($this->expertIn('Blockchain Technology')));
    }

    public function test_a_team_is_suggested_by_its_approved_pre_proposal_tags(): void
    {
        $team = $this->team('Sensor Team', 'Water Level Warning For Flood Prone Areas');
        $leader = Student::factory()->withCompleteProfile()->create();
        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => $team->project_title, 'sdg_goals' => 'SDG 11', 'research_agenda' => ['x'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile', 'tags' => ['IoT'], 'approved_status' => true,
        ]);

        $this->assertSame(['Sensor Team'], $this->suggestFor($this->expertIn('IoT')));
    }

    public function test_a_team_that_is_not_ready_is_not_suggested(): void
    {
        $this->team('Unready Chain', 'Blockchain Ledger For Barangay Certificate Requests', staffed: false);

        $this->assertSame([], $this->suggestFor($this->expertIn('Blockchain Technology')));
    }

    /** Generic title words ("System", "Development") match nothing on their own. */
    public function test_common_title_words_do_not_count_as_a_match(): void
    {
        $this->team('Generic Team', 'Online Management System Development');

        $this->assertSame([], $this->suggestFor($this->expertIn('Systems Development')));
    }
}
