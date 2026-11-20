<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel RSC: an expert may take up to 10 teams on their own say; more waits
 * for a CaPSA/TSA of those programs to approve.
 */
class TeamCapApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function expert(array $attrs = []): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create($attrs + [
            'approved_status' => true, 'max_teams' => 8, 'program_slots' => '8 - BSIT', 'first_login_at' => now(),
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    private function capsa(array $sections): CapstoneExpert
    {
        $capsa = $this->expert(['sections' => $sections]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function save(CapstoneExpert $expert, array $slots)
    {
        // A real request loads the expert fresh; the test guard would reuse a stale copy.
        return $this->actingAs($expert->refresh(), 'expert')->putJson(route('instructor.profile.update'), [
            'program_slots' => $slots,
            'expertise_by_side' => ['adviser' => ['Databases']],
        ]);
    }

    public function test_up_to_ten_saves_at_once_and_more_waits_for_the_capsa_of_that_program(): void
    {
        $bsit = $this->capsa(['BSIT-3A']);
        $bscs = $this->capsa(['BSCS-3A']);
        $expert = $this->expert();

        $this->save($expert, ['BSIT' => 10])->assertOk()->assertJson(['pending' => null]);
        $this->assertSame(10, (int) $expert->fresh()->max_teams);

        $this->save($expert, ['BSIT' => 12])->assertOk()->assertJson(['pending' => 12]);
        $fresh = $expert->fresh();
        $this->assertSame(10, (int) $fresh->max_teams);
        $this->assertSame(['BSIT' => 12], $fresh->teams_cap_request);
        $this->assertSame(1, Notification::where('expert_id', $bsit->expert_id)->where('type', 'team_cap_request')->count());
        $this->assertSame(0, Notification::where('expert_id', $bscs->expert_id)->where('type', 'team_cap_request')->count());

        // Saving the same ask again does not ring the bell twice.
        $this->save($expert, ['BSIT' => 12])->assertOk();
        $this->assertSame(1, Notification::where('expert_id', $bsit->expert_id)->where('type', 'team_cap_request')->count());

        $this->actingAs($bsit, 'expert')->get(route('capsa.experts'))->assertOk()->assertSee('asks to take', false);

        // A CaPSA/TSA of another program cannot answer it.
        $this->actingAs($bscs, 'expert')->putJson(route('capsa.experts.team-cap.decide', $expert), ['approve' => true])->assertForbidden();

        $this->actingAs($bsit, 'expert')->putJson(route('capsa.experts.team-cap.decide', $expert), ['approve' => true])->assertOk();
        $fresh = $expert->fresh();
        $this->assertSame(12, (int) $fresh->max_teams);
        $this->assertSame('12 - BSIT', $fresh->program_slots);
        $this->assertNull($fresh->teams_cap_request);
        $this->assertSame(1, Notification::where('expert_id', $expert->expert_id)->where('type', 'team_cap_decided')->count());

        // Lowering from an approved number needs nobody.
        $this->save($expert, ['BSIT' => 11])->assertOk()->assertJson(['pending' => null]);
        $this->assertSame(11, (int) $expert->fresh()->max_teams);
    }

    public function test_declining_keeps_the_old_slots_and_lowering_takes_a_request_back(): void
    {
        $capsa = $this->capsa(['BSIT-3A']);
        $expert = $this->expert();

        $this->save($expert, ['BSIT' => 15])->assertOk();
        $this->actingAs($capsa, 'expert')->putJson(route('capsa.experts.team-cap.decide', $expert), ['approve' => false])->assertOk();
        $fresh = $expert->fresh();
        $this->assertSame(8, (int) $fresh->max_teams);
        $this->assertNull($fresh->teams_cap_request);

        $this->save($expert, ['BSIT' => 15])->assertOk();
        $this->save($expert, ['BSIT' => 9])->assertOk();
        $fresh = $expert->fresh();
        $this->assertSame(9, (int) $fresh->max_teams);
        $this->assertNull($fresh->teams_cap_request);
        $this->actingAs($capsa, 'expert')->putJson(route('capsa.experts.team-cap.decide', $expert), ['approve' => true])->assertStatus(422);
    }
}
