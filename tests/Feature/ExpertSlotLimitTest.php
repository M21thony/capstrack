<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An expert never ends up with more teams than their slots, even when
 * several applications were waiting at once and each fit on its own.
 */
class ExpertSlotLimitTest extends TestCase
{
    use RefreshDatabase;

    private function adviser(array $attributes): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true] + $attributes);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    private function application(CapstoneExpert $expert, string $program, string $status = 'pending'): TeamExpertAssignment
    {
        $leader = Student::factory()->withCompleteProfile()->create(['course' => $program]);
        $team = Team::create(['team_name' => 'Team '.$leader->student_id, 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return TeamExpertAssignment::create(['team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => $status]);
    }

    private function approve(CapstoneExpert $expert, TeamExpertAssignment $assignment)
    {
        return $this->actingAs($expert, 'expert')
            ->patchJson(route('instructor.assignments.update', $assignment->assignment_id), ['status' => 'approved']);
    }

    public function test_approving_stops_at_the_programs_own_cap(): void
    {
        $expert = $this->adviser(['max_teams' => 4, 'program_slots' => '1 - BSIT, 3 - BSIS']);
        $this->application($expert, 'BSIT', 'approved');
        $secondBsit = $this->application($expert, 'BSIT');
        $bsis = $this->application($expert, 'BSIS');

        $this->approve($expert, $secondBsit)->assertStatus(422)
            ->assertJson(['message' => 'You have reached your limit of 1 BSIT team(s).']);
        $this->assertSame('pending', $secondBsit->fresh()->status);

        // The other program still has room.
        $this->approve($expert, $bsis)->assertOk();
        $this->assertSame('approved', $bsis->fresh()->status);
    }

    public function test_approving_stops_at_the_overall_cap(): void
    {
        $expert = $this->adviser(['max_teams' => 2, 'program_slots' => '2 - BSIT, 2 - BSIS']);
        $this->application($expert, 'BSIT', 'approved');
        $this->application($expert, 'BSIS', 'approved');
        $third = $this->application($expert, 'BSIS');

        $this->approve($expert, $third)->assertStatus(422)
            ->assertJson(['message' => 'You have reached your maximum number of teams.']);
        $this->assertSame('pending', $third->fresh()->status);
    }

    public function test_an_expert_with_room_approves(): void
    {
        $expert = $this->adviser(['max_teams' => null, 'program_slots' => null]);
        $assignment = $this->application($expert, 'BSIT');

        $this->approve($expert, $assignment)->assertOk();
        $this->assertSame('approved', $assignment->fresh()->status);
    }
}
