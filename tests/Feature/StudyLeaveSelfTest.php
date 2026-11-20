<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel RSC: both the faculty member and the CaPSA/TSA can turn study leave
 * on. The CaPSA/TSA side already existed; this is the expert's own switch.
 */
class StudyLeaveSelfTest extends TestCase
{
    use RefreshDatabase;

    private function save(CapstoneExpert $expert, array $extra = [])
    {
        return $this->actingAs($expert, 'expert')->putJson(route('instructor.profile.update'), [
            'program_slots' => ['BSIT' => 4],
            'expertise_by_side' => ['adviser' => ['Databases']],
        ] + $extra);
    }

    public function test_an_expert_turns_study_leave_on_and_off_and_a_page_that_does_not_send_it_leaves_it(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'max_teams' => 4, 'program_slots' => '4 - BSIT', 'first_login_at' => now(),
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        $this->actingAs($expert, 'expert')->get(route('instructor.profile'))->assertOk()->assertSee('id="pp-leave"', false);

        $this->save($expert, ['on_study_leave' => true])->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($expert->fresh()->on_study_leave);

        $this->save($expert)->assertOk();
        $this->assertTrue($expert->fresh()->on_study_leave);

        $this->save($expert, ['on_study_leave' => false])->assertOk();
        $this->assertFalse($expert->fresh()->on_study_leave);
    }
}
