<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\CapsaReports;
use App\Support\DashboardStats;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The dashboards open on a summary of the data - totals, how far along the
 * teams are, how the defenses are going, the fees - and keep what is waiting
 * on the person beneath it.
 */
class DashboardSummaryTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function engage(Team $team, CapstoneExpert $expert, string $roleName): void
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $expert->roles()->syncWithoutDetaching([$role->role_id]);

        TeamExpertAssignment::create([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
        ]);
    }

    public function test_a_capsa_sees_the_summary_of_their_sections_above_the_work_waiting(): void
    {
        $capsa = $this->capsaFor();
        [$team, $leader] = $this->teamInSection('Alpha');
        $this->teamInSection('Bravo');
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->engage($team, $adviser, 'adviser');
        DefenseSchedule::create([
            'team_id' => $team->team_id, 'defense_type' => 'Title Defense', 'schedule_date' => now()->subDays(3),
            'venue' => 'IT Lab 1', 'outcome' => DefenseOutcome::PASSED,
        ]);

        $response = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))->assertOk();

        $response->assertSeeInOrder(['Your sections at a glance', 'Team Progress', 'Defense Results', 'Fee Collection', 'Experts and Sections', 'Waiting on you']);
        $response->assertSee('Teams per section');
        $response->assertSee('BSIT-3A');

        $stats = app(DashboardStats::class)->capsa($capsa, app(CapsaReports::class)->context($capsa, []));
        $this->assertSame(2, $stats['teams']);
        $this->assertSame(2, $stats['students']);
        $this->assertSame(1, collect($stats['coverage'])->firstWhere('label', 'Adviser')['have']);
        $this->assertSame(0, collect($stats['coverage'])->firstWhere('label', 'Grammarian')['have']);
        $this->assertSame([['label' => 'BSIT-3A', 'value' => 2]], $stats['bySection']);
        $this->assertCount(3, $stats['fees']);
        $this->assertSame(4, count($stats['defenses']['stages']));
    }

    public function test_an_admin_sees_the_whole_system(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        Student::factory()->count(3)->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology']);
        Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Computer Science']);
        Team::create(['team_name' => 'One', 'team_status' => 'Active', 'is_confirmed' => true]);
        Team::create(['team_name' => 'Two', 'team_status' => 'Active']);
        CapstoneExpert::factory()->create(['approved_status' => false]);

        $response = $this->actingAs($admin, 'expert')->get(route('admin.dashboard'))->assertOk();

        $response->assertSeeInOrder(['The whole system', 'Students by Program', 'Experts by Role', 'Defense Results', 'Money and Archive']);
        $response->assertSee('waiting to be approved');

        $stats = app(DashboardStats::class)->admin($admin);
        $this->assertSame(4, $stats['students']);
        $this->assertSame(2, $stats['teams']);
        $this->assertSame(1, $stats['verifiedTeams']);
        $this->assertSame(1, $stats['awaitingApproval']);
        $this->assertSame(['BSIT', 3], [$stats['byProgram'][0]['label'], $stats['byProgram'][0]['value']]);
    }

    public function test_an_expert_sees_their_own_numbers_as_counts(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'max_teams' => 4]);
        [$one] = $this->teamInSection('Alpha');
        [$two] = $this->teamInSection('Bravo');
        $this->engage($one, $expert, 'adviser');
        $this->engage($two, $expert, 'adviser');
        $this->engage($two, $expert, 'consultant');

        $response = $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))->assertOk();

        $response->assertSeeInOrder(['Your work at a glance', 'Teams You Advise', 'Your Teams', 'Reviews, Consultations and Panels', 'Waiting on you']);
        $response->assertSee('2 of 4 team slots used');

        $stats = app(DashboardStats::class)->expert($expert->fresh());
        $this->assertSame(2, $stats['teams']);
        $this->assertSame(['used' => 2, 'max' => 4, 'left' => 2], $stats['slots']);
        $this->assertSame(2, collect($stats['byRole'])->firstWhere('label', 'Adviser')['value']);
        $this->assertSame(1, collect($stats['byRole'])->firstWhere('label', 'Consultant')['value']);
        $this->assertSame(0, $stats['reviews']['total']);
        $this->assertNull($stats['reviews']['percent']);
    }

    public function test_an_expert_with_nothing_yet_still_gets_a_dashboard(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'max_teams' => null]);

        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))->assertOk()
            ->assertSee('Team limit not set')
            ->assertSee('Your work at a glance');
    }
}
