<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertEvaluation;
use App\Models\Role;
use App\Models\Student;
use App\Models\Team;
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

    /** The profile is a page: what the expert can change, and how students see them. */
    public function test_the_profile_page_shows_the_form_and_what_students_see(): void
    {
        $expert = $this->makeExpert();
        $expert->update(['program_slots' => '3 - BSIT, 2 - BSIS', 'max_teams' => 5, 'credentials' => 'MIT']);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);
        DB::table('expert_expertise')->insert(['expert_id' => $expert->expert_id, 'expertise_field' => 'Research Methods', 'side' => 'adviser']);

        $response = $this->actingAs($expert->fresh(), 'expert')->get(route('instructor.profile'))->assertOk();

        $response->assertSee('What students see')
            ->assertSee($expert->email)
            ->assertSee('<option value="MIT" selected>MIT</option>', false)
            ->assertSee('data-program="BSIT"', false)
            ->assertSee('data-side="adviser"', false)
            ->assertSee('Research Methods')
            ->assertSee('No ratings from team leaders yet')
            ->assertDontSee('id="ep-overlay"', false);

        $this->assertSame(['BSIT' => 3, 'BSIS' => 2], $response->viewData('programSlots'));
        $this->assertSame(['adviser' => ['Research Methods'], 'panel' => []], $response->viewData('researchAreas'));
        $this->assertSame(0, $response->viewData('teamsHandled'));
    }

    public function test_the_profile_page_shows_the_rating_from_team_leaders(): void
    {
        $expert = $this->makeExpert();
        $leader = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Rated', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $leader->student_id]);
        foreach ([5, 4] as $rating) {
            ExpertEvaluation::factory()->create(['expert_id' => $expert->expert_id, 'team_id' => $team->team_id, 'student_id' => $leader->student_id, 'rating' => $rating]);
        }

        $this->actingAs($expert, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('<b>4.5</b> from 2 team leader ratings', false);
    }

    public function test_guest_cannot_update_capacity(): void
    {
        // EnsureExpertIsAuthenticated always redirects to /login, even for
        // JSON requests - it doesn't check expectsJson().
        $this->putJson(route('instructor.profile.update'), ['program_slots' => ['BSIT' => 5]])
            ->assertRedirect('/login');
    }
}
