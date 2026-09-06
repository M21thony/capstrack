<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Capacity is per-program: an expert commonly takes, say, 1 BSIT team and 5
 * BSIS teams, and a single overall number can't express that (it would
 * happily let all six be BSIT). The expert sets a count per program and
 * max_teams is kept as the derived total, so the existing "is this expert
 * full?" checks keep working unchanged.
 */
class InstructorProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(): CapstoneExpert
    {
        return CapstoneExpert::factory()->create([
            'approved_status' => true,
            'max_teams' => 4,
        ]);
    }

    public function test_instructor_can_set_a_different_capacity_per_program(): void
    {
        $expert = $this->makeExpert();

        $response = $this->actingAs($expert, 'expert')->putJson(
            route('instructor.profile.update'),
            ['program_slots' => ['BSIT' => 1, 'BSIS' => 5], 'expertise' => []]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $expert = $expert->fresh();
        $this->assertSame(6, (int) $expert->max_teams, 'max_teams should be the sum of the per-program slots');
        $this->assertSame(1, $expert->slotsForProgram('BSIT'));
        $this->assertSame(5, $expert->slotsForProgram('BSIS'));
    }

    // Programs they take no teams from are dropped rather than stored as
    // zero, so "accepts BSIS?" has one answer, not two spellings of no.
    public function test_programs_with_no_slots_are_not_recorded(): void
    {
        $expert = $this->makeExpert();

        $this->actingAs($expert, 'expert')->putJson(
            route('instructor.profile.update'),
            ['program_slots' => ['BSIT' => 2, 'BSIS' => 0], 'expertise' => []]
        )->assertOk();

        $expert = $expert->fresh();
        $this->assertTrue($expert->acceptsProgram('BSIT'));
        $this->assertFalse($expert->acceptsProgram('BSIS'));
    }

    // Zero everywhere would leave them permanently unassignable, which is
    // far more likely a mis-save than an intent.
    public function test_accepting_no_teams_at_all_is_rejected(): void
    {
        $expert = $this->makeExpert();

        $this->actingAs($expert, 'expert')->putJson(
            route('instructor.profile.update'),
            ['program_slots' => ['BSIT' => 0, 'BSIS' => 0], 'expertise' => []]
        )->assertStatus(422)->assertJson(['success' => false]);

        $this->assertEquals(4, $expert->fresh()->max_teams);
    }

    public function test_an_unknown_program_is_rejected(): void
    {
        $expert = $this->makeExpert();

        $this->actingAs($expert, 'expert')->putJson(
            route('instructor.profile.update'),
            ['program_slots' => ['BSHM' => 3], 'expertise' => []]
        )->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_capacity_is_required(): void
    {
        $expert = $this->makeExpert();

        $this->actingAs($expert, 'expert')->putJson(route('instructor.profile.update'), [])
            ->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_saving_replaces_the_experts_expertise_list(): void
    {
        $expert = $this->makeExpert();

        DB::table('expert_expertise')->insert([
            'expert_id' => $expert->expert_id,
            'expertise_field' => 'An Old Skill',
        ]);

        $this->actingAs($expert, 'expert')->putJson(
            route('instructor.profile.update'),
            ['program_slots' => ['BSIT' => 1], 'expertise' => ['Machine Learning', 'Technical Writing']]
        )->assertOk();

        $skills = DB::table('expert_expertise')
            ->where('expert_id', $expert->expert_id)
            ->pluck('expertise_field')
            ->all();

        sort($skills);
        $this->assertSame(['Machine Learning', 'Technical Writing'], $skills);
    }

    public function test_guest_cannot_update_capacity(): void
    {
        // EnsureExpertIsAuthenticated always redirects to /login, even for
        // JSON requests - it doesn't check expectsJson().
        $this->putJson(route('instructor.profile.update'), ['program_slots' => ['BSIT' => 5]])
            ->assertRedirect('/login');
    }
}
