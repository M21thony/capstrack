<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel RSC: an expert picks the courses they sit panels for. None picked is
 * every course; a team whose course is unknown is never blocked.
 */
class PanelProgramsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expert_saves_their_panel_courses_and_none_is_every_course(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'max_teams' => 4, 'program_slots' => '4 - BSIT', 'first_login_at' => now(),
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);
        $save = fn (array $extra) => $this->actingAs($expert, 'expert')->putJson(route('instructor.profile.update'), [
            'program_slots' => ['BSIT' => 4],
            'expertise_by_side' => ['adviser' => ['Databases']],
        ] + $extra);

        $this->actingAs($expert, 'expert')->get(route('instructor.profile'))->assertOk()->assertSee('data-panel-program="BSIS"', false);

        $save(['panel_programs' => ['BSIS', 'BSIT']])->assertOk();
        $this->assertSame(['BSIT', 'BSIS'], $expert->fresh()->panel_programs);
        $this->assertTrue($expert->fresh()->sitsPanelsFor('BSIT'));
        $this->assertFalse($expert->fresh()->sitsPanelsFor('BSCS'));
        $this->assertTrue($expert->fresh()->sitsPanelsFor(null));

        $save([])->assertOk();
        $this->assertSame(['BSIT', 'BSIS'], $expert->fresh()->panel_programs);

        $save(['panel_programs' => ['NOPE']])->assertStatus(422);

        $save(['panel_programs' => []])->assertOk();
        $this->assertNull($expert->fresh()->panel_programs);
        $this->assertTrue($expert->fresh()->sitsPanelsFor('BSCS'));
    }
}
