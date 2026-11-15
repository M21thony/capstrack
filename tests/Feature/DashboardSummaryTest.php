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
use Illuminate\Support\Facades\DB;
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

        $response->assertSeeInOrder(['Team &amp; Expert Coverage', 'Pending Issues', 'Upcoming Deadlines', 'Recent Activity', 'Quick Actions'], false);

        $stats = app(DashboardStats::class)->capsa($capsa, app(CapsaReports::class)->context($capsa, []));
        $this->assertSame(2, $stats['teams']);
        $this->assertSame(2, $stats['students']);
        $this->assertSame(2, $stats['newTeams']);
        $this->assertSame(2, $stats['newStudents']);
        $this->assertSame(1, collect($stats['coverage'])->firstWhere('label', 'Adviser')['have']);
        $this->assertSame(0, collect($stats['coverage'])->firstWhere('label', 'Grammarian')['have']);
        $this->assertSame([['label' => 'BSIT-3A', 'value' => 2]], $stats['bySection']);
        $this->assertCount(3, $stats['fees']);
        $this->assertSame(4, count($stats['defenses']['stages']));
    }

    public function test_a_team_older_than_thirty_days_is_not_counted_as_new(): void
    {
        $capsa = $this->capsaFor();
        [$old] = $this->teamInSection('Alpha');
        $this->teamInSection('Bravo');
        DB::table('team')->where('team_id', $old->team_id)->update(['created_at' => now()->subDays(31)]);

        $stats = app(DashboardStats::class)->capsa($capsa, app(CapsaReports::class)->context($capsa, []));

        $this->assertSame(2, $stats['teams']);
        $this->assertSame(1, $stats['newTeams']);
        $this->assertSame(1, $stats['newStudents']);
    }

    public function test_an_admin_sees_the_whole_system(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        Student::factory()->count(3)->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology']);
        Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Computer Science']);
        Team::create(['team_name' => 'One', 'team_status' => 'Active', 'is_confirmed' => true]);
        Team::create(['team_name' => 'Two', 'team_status' => 'Active']);

        $response = $this->actingAs($admin, 'expert')->get(route('admin.dashboard'))->assertOk();

        $response->assertSeeInOrder(['Students Overview', 'Experts by Role', 'Defense Results', 'Fees and Archive']);

        $stats = app(DashboardStats::class)->admin($admin);
        $this->assertSame(4, $stats['students']);
        $this->assertSame(2, $stats['teams']);
        $this->assertSame(1, $stats['verifiedTeams']);
        $this->assertSame(4, $stats['active'] + $stats['inactive']);
    }

    /** The chart and the Students tile must agree: a deleted account's old row counts for neither. */
    public function test_a_deleted_students_year_row_is_not_counted(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        $student = Student::factory()->withCompleteProfile()->create();

        DB::statement('PRAGMA foreign_keys = OFF');
        foreach ([$student->student_id, 999999] as $id) {
            DB::table('student_s_y')->insert(['student_id' => $id, 'school_year' => '2026-2027']);
        }

        $stats = app(DashboardStats::class)->admin($admin);
        $this->assertSame(1, $stats['active']);
        $this->assertSame([['label' => '2026-2027', 'value' => 1]], $stats['studentsByYear']);
    }

    public function test_the_students_chart_has_a_point_per_semester(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        [$stays, $drops] = [Student::factory()->withCompleteProfile()->create(), Student::factory()->withCompleteProfile()->create()];
        $term = fn (int $sem) => \App\Models\Term::firstOrCreate(['school_year' => '2026-2027', 'semester' => $sem, 'capstone_stage' => $sem])->term_id;
        [$first, $second] = [$term(1), $term(2)];

        foreach ([[$stays, $first], [$drops, $first], [$stays, $second]] as [$student, $termId]) {
            DB::table('student_s_y')->insert(['student_id' => $student->student_id, 'school_year' => '2026-2027', 'term_id' => $termId]);
        }

        $this->assertSame(
            [['label' => '2026-2027 · 1st sem', 'value' => 2], ['label' => '2026-2027 · 2nd sem', 'value' => 1]],
            app(DashboardStats::class)->admin($admin)['studentsByYear'],
        );
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

        $response->assertSeeInOrder(['My Workload', 'Team Distribution', 'Consultations', 'Upcoming Tasks', 'Announcements', 'Recent Activity', 'Quick Access']);
        $response->assertSee('2 of 4 team slots used');

        $stats = app(DashboardStats::class)->expert($expert->fresh());
        $this->assertSame(2, $stats['teams']);
        $this->assertSame(['used' => 2, 'max' => 4, 'left' => 2], $stats['slots']);
        $this->assertSame(2, collect($stats['byRole'])->firstWhere('label', 'Adviser')['value']);
        $this->assertSame(1, collect($stats['byRole'])->firstWhere('label', 'Consultant')['value']);
        $this->assertSame(0, $stats['reviews']['total']);
        $this->assertNull($stats['reviews']['percent']);
    }

    /** A weekly slot has no date of its own: the booking's date is the one that counts. */
    public function test_an_experts_consultations_go_by_the_date_booked(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        [$team, $leader] = $this->teamInSection('Alpha');
        $this->engage($team, $expert, 'adviser');

        $slot = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expert->expert_id, 'available_date' => 'Monday', 'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'consultation_limit' => 1, 'is_available' => true, 'recur' => 'weekly', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([now()->addDays(3), now()->subDays(3)] as $date) {
            DB::table('consultation_booking')->insert([
                'schedule_id' => $slot, 'team_id' => $team->team_id, 'student_id' => $leader->student_id,
                'session_date' => $date->toDateString(), 'booking_status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))->assertOk()
            ->assertSee($team->team_name)
            ->assertSee('In 3 days');

        $this->assertSame(1, app(DashboardStats::class)->expert($expert->fresh())['consultations']['upcoming']);
    }

    public function test_an_expert_with_nothing_yet_still_gets_a_dashboard(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'max_teams' => null]);

        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))->assertOk()
            ->assertSee('Team limit not set')
            ->assertSee('My Workload');
    }
}
