<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_update_team_capacity(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'max_teams' => 4,
        ]);

        $response = $this->actingAs($expert, 'expert')->postJson(
            route('instructor.profile.update'),
            ['max_teams' => 7]
        );

        $response->assertOk()->assertJson(['success' => true, 'max_teams' => 7]);
        $this->assertEquals(7, $expert->fresh()->max_teams);
    }

    public function test_team_capacity_must_be_between_one_and_ten(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'max_teams' => 4,
        ]);

        $response = $this->actingAs($expert, 'expert')->postJson(
            route('instructor.profile.update'),
            ['max_teams' => 11]
        );

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertEquals(4, $expert->fresh()->max_teams);
    }

    public function test_team_capacity_is_required(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'max_teams' => 4,
        ]);

        $response = $this->actingAs($expert, 'expert')->postJson(
            route('instructor.profile.update'),
            []
        );

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_guest_cannot_update_team_capacity(): void
    {
        // EnsureExpertIsAuthenticated always redirects to /login, even for
        // JSON requests - it doesn't check expectsJson().
        $this->postJson(route('instructor.profile.update'), ['max_teams' => 5])
            ->assertRedirect('/login');
    }
}
