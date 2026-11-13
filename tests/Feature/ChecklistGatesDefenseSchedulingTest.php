<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * What is still open above a defense on the team's checklist - Getting
 * started for the Pre-Proposal, everything through the signed Hearing Notice
 * for the Title Defense - is named on the board, but it does not keep the
 * team from a date: the panel follows the requirements up.
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

    private function notReady(string $stage): Collection
    {
        return collect($this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => $stage])
            ->assertOk()
            ->json('data.not_ready'))->pluck('reason', 'team_name');
    }

    /** The requirement the board names for each team still owing one. */
    private function owed(string $stage): Collection
    {
        return collect($this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.board', ['defense_type' => $stage]))
            ->assertOk()
            ->json('data.teams'))
            ->mapWithKeys(fn ($row) => [$row['name'] => collect($row['blockers'])->firstWhere('key', 'req')['text'] ?? null])
            ->filter();
    }

    private function complete(Deliverable $deliverable, Team $team): void
    {
        DeliverableCheck::create(['deliverable_id' => $deliverable->id, 'team_id' => $team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);
    }

    public function test_the_pre_proposal_waits_for_the_forms_above_it(): void
    {
        $team = $this->readyTeam('Alpha', DefenseOutcome::PRE_PROPOSAL);
        $checklist = $this->departmentChecklist($this->capsa);

        $reason = $this->owed(DefenseOutcome::PRE_PROPOSAL)['Alpha'] ?? '';
        $this->assertStringContainsString('Signed Project Team Assignment Form', $reason);
        $this->assertStringContainsString('4 more', $reason);

        // Named, but not in the way: Generate schedule still places the team.
        $this->assertArrayNotHasKey('Alpha', $this->notReady(DefenseOutcome::PRE_PROPOSAL)->all());

        $checklist->milestones()->where('name', 'Pre-proposal Defense')->first()->deliverables
            ->takeUntil(fn (Deliverable $d) => $d->kind === Deliverable::DEFENSE)
            ->each(fn (Deliverable $d) => $this->complete($d, $team));

        $this->assertArrayNotHasKey('Alpha', $this->owed(DefenseOutcome::PRE_PROPOSAL)->all());
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

        $this->assertStringContainsString('Signed Title Defense Hearing Notice', $this->owed(DefenseOutcome::TITLE_DEFENSE)['Bravo'] ?? '');
        // Neither the checklist nor the unpaid Title Defense fee holds the team back - only warnings.
        $this->assertArrayNotHasKey('Bravo', $this->notReady(DefenseOutcome::TITLE_DEFENSE)->all());

        $this->complete($notice, $team);

        // The TD RSC comes after the defense, so it is not owed before it.
        $this->assertArrayNotHasKey('Bravo', $this->owed(DefenseOutcome::TITLE_DEFENSE)->all());
    }

    /** A course whose list has no Pre-proposal goes straight to its Title Defense. */
    public function test_a_course_without_a_pre_proposal_starts_at_the_title_defense(): void
    {
        $team = $this->readyTeam('Delta', DefenseOutcome::TITLE_DEFENSE);
        $checklist = $this->emptyChecklist($this->capsa);
        $this->addDeliverable($checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => DefenseOutcome::TITLE_DEFENSE]);

        $this->assertSame([DefenseOutcome::TITLE_DEFENSE], $team->defenseStages());
        $this->assertArrayNotHasKey('Delta', $this->notReady(DefenseOutcome::TITLE_DEFENSE)->all());
        $this->assertArrayNotHasKey('Delta', $this->notReady(DefenseOutcome::PRE_PROPOSAL)->all());
    }

    /** A team whose section has no checklist has not been given this gate. */
    public function test_a_team_with_no_checklist_is_not_held_up_by_one(): void
    {
        $this->departmentChecklist($this->capsa);
        $this->readyTeam('Charlie', DefenseOutcome::PRE_PROPOSAL, '3rd · Z');

        $this->assertArrayNotHasKey('Charlie', $this->owed(DefenseOutcome::PRE_PROPOSAL)->all());
        $this->assertArrayNotHasKey('Charlie', $this->notReady(DefenseOutcome::PRE_PROPOSAL)->all());
    }
}
