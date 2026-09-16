<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * A defense is not scheduled until everything above it on the team's
 * checklist is done: Getting started for the Pre-Proposal, everything through
 * the signed Hearing Notice for the Title Defense.
 */
class ChecklistGatesDefenseSchedulingTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $admin;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
        $this->capsa = $this->capsaFor(['BSIT-3A']);
        DefenseWindow::create(['defense_type' => DefenseOutcome::PRE_PROPOSAL, 'start_date' => '2027-03-15', 'end_date' => '2027-03-16']);
        DefenseWindow::create(['defense_type' => DefenseOutcome::TITLE_DEFENSE, 'start_date' => '2027-04-01', 'end_date' => '2027-04-02']);
    }

    private function readyTeam(string $name, string $stage, string $yearSection = '3rd · A'): Team
    {
        $team = Team::create(['team_name' => $name, 'project_title' => $name.' Title', 'team_status' => 'Active', 'is_confirmed' => true]);
        $member = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => $yearSection,
        ]);
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
        return collect($this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => $stage])
            ->assertOk()
            ->json('data.not_ready'))->pluck('reason', 'team_name');
    }

    private function complete(Deliverable $deliverable, Team $team): void
    {
        DeliverableCheck::create(['deliverable_id' => $deliverable->id, 'team_id' => $team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);
    }

    public function test_the_pre_proposal_waits_for_getting_started(): void
    {
        $team = $this->readyTeam('Alpha', DefenseOutcome::PRE_PROPOSAL);
        $checklist = $this->departmentChecklist($this->capsa);

        $reason = $this->notReady(DefenseOutcome::PRE_PROPOSAL)['Alpha'] ?? '';
        $this->assertStringContainsString('Signed Project Team Assignment Form', $reason);
        $this->assertStringContainsString('4 more', $reason);

        $checklist->milestones()->where('name', 'Getting started')->first()->deliverables
            ->each(fn (Deliverable $d) => $this->complete($d, $team));

        $this->assertArrayNotHasKey('Alpha', $this->notReady(DefenseOutcome::PRE_PROPOSAL)->all());
    }

    public function test_the_title_defense_waits_for_everything_above_it_and_nothing_below(): void
    {
        $team = $this->readyTeam('Bravo', DefenseOutcome::TITLE_DEFENSE);
        DefenseSchedule::create([
            'team_id' => $team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1,
            'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now(),
        ]);

        $checklist = $this->emptyChecklist($this->capsa);
        $notice = $this->addDeliverable($checklist, ['title' => 'Signed Title Defense Hearing Notice']);
        $this->addDeliverable($checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => DefenseOutcome::TITLE_DEFENSE]);
        $this->addDeliverable($checklist, ['title' => 'Signed (Noted) TD RSC']);

        $this->assertStringContainsString('Signed Title Defense Hearing Notice', $this->notReady(DefenseOutcome::TITLE_DEFENSE)['Bravo'] ?? '');

        $this->complete($notice, $team);

        // The TD RSC comes after the defense, so it cannot hold it up. (Any
        // reason left is the Title Defense fee, which this test does not set.)
        $this->assertStringNotContainsString('Checklist', $this->notReady(DefenseOutcome::TITLE_DEFENSE)['Bravo'] ?? '');
    }

    /** A team whose section has no checklist has not been given this gate. */
    public function test_a_team_with_no_checklist_is_not_held_up_by_one(): void
    {
        $this->departmentChecklist($this->capsa);
        $this->readyTeam('Charlie', DefenseOutcome::PRE_PROPOSAL, '3rd · Z');

        $this->assertArrayNotHasKey('Charlie', $this->notReady(DefenseOutcome::PRE_PROPOSAL)->all());
    }
}
