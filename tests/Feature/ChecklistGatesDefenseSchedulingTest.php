<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\ExpertRole;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseOutcome;
use App\Support\MilestoneTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A defense is not scheduled until everything above it on the checklist is
 * done: Getting started for the Pre-Proposal, everything through the signed
 * Hearing Notice for the Title Defense.
 */
class ChecklistGatesDefenseSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
        DefenseWindow::create(['defense_type' => DefenseOutcome::PRE_PROPOSAL, 'start_date' => '2027-03-15', 'end_date' => '2027-03-16']);
        DefenseWindow::create(['defense_type' => DefenseOutcome::TITLE_DEFENSE, 'start_date' => '2027-04-01', 'end_date' => '2027-04-02']);
    }

    private function readyTeam(string $name, string $stage): Team
    {
        $team = Team::create(['team_name' => $name, 'project_title' => $name.' Title', 'team_status' => 'Active', 'is_confirmed' => true]);
        $member = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        foreach (Team::REQUIRED_EXPERT_ROLES as $roleName) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            $role = Role::firstOrCreate(['role_name' => $roleName]);
            $expert->roles()->attach($role->role_id);
            TeamExpertAssignment::create([
                'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
            ]);
        }

        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => $stage, 'attempt' => 1]);
        DefensePanel::create([
            'defense_id' => $schedule->defense_id, 'panel_role' => 'Panel Chair', 'created_at' => now(),
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
        ]);

        return $team;
    }

    private function notReady(string $stage): \Illuminate\Support\Collection
    {
        return collect($this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => $stage])
            ->assertOk()
            ->json('data.not_ready'))->pluck('reason', 'team_name');
    }

    public function test_the_pre_proposal_waits_for_getting_started(): void
    {
        $team = $this->readyTeam('Alpha', DefenseOutcome::PRE_PROPOSAL);
        $milestone = Milestone::create(['team_id' => $team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
        MilestoneTemplate::applyTo($milestone, MilestoneTemplate::CAPSTONE_1);

        $reason = $this->notReady(DefenseOutcome::PRE_PROPOSAL)['Alpha'] ?? '';
        $this->assertStringContainsString('Signed Project Team Assignment Form', $reason);
        $this->assertStringContainsString('4 more', $reason);

        $milestone->items()->where('phase', 'Getting started')->update(['completed_at' => now()]);

        $this->assertArrayNotHasKey('Alpha', $this->notReady(DefenseOutcome::PRE_PROPOSAL)->all());
    }

    public function test_the_title_defense_waits_for_everything_above_it_and_nothing_below(): void
    {
        $team = $this->readyTeam('Bravo', DefenseOutcome::TITLE_DEFENSE);
        DefenseSchedule::create([
            'team_id' => $team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1,
            'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now(),
        ]);

        $milestone = Milestone::create(['team_id' => $team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
        $notice = $milestone->items()->create(['title' => 'Signed Title Defense Hearing Notice', 'kind' => MilestoneItem::FORM, 'position' => 0]);
        $milestone->items()->create(['title' => 'Title Defense', 'kind' => MilestoneItem::DEFENSE, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'position' => 1]);
        $milestone->items()->create(['title' => 'Signed (Noted) TD RSC', 'kind' => MilestoneItem::FORM, 'position' => 2]);

        $this->assertStringContainsString('Signed Title Defense Hearing Notice', $this->notReady(DefenseOutcome::TITLE_DEFENSE)['Bravo'] ?? '');

        $notice->update(['completed_at' => now()]);

        // The TD RSC comes after the defense, so it cannot hold it up. (Any
        // reason left is the Title Defense fee, which this test does not set.)
        $this->assertStringNotContainsString('Checklist', $this->notReady(DefenseOutcome::TITLE_DEFENSE)['Bravo'] ?? '');
    }

    /** A CaPSA who has not given a team a checklist has not asked for this gate. */
    public function test_a_team_with_no_checklist_is_not_held_up_by_one(): void
    {
        $this->readyTeam('Charlie', DefenseOutcome::PRE_PROPOSAL);

        $this->assertArrayNotHasKey('Charlie', $this->notReady(DefenseOutcome::PRE_PROPOSAL)->all());
    }
}
