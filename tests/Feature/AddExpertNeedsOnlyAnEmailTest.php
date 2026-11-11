<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adding one expert asks for an address and nothing else.
 *
 * A name typed here is a guess at how somebody spells their own; Google
 * gives the real one the first time they sign in. The role is the CaPSA/TSA's to
 * set, and can be set from the row afterwards. Add CaPSA/TSA has always worked
 * this way - this brings Add Expert alongside it.
 */
class AddExpertNeedsOnlyAnEmailTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        foreach (CapstoneExpert::STUDENT_FACING_ROLES as $role) {
            Role::firstOrCreate(['role_name' => $role]);
        }

        return $capsa;
    }

    public function test_an_address_on_its_own_is_enough(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->postJson('/capsa/experts', ['email' => 'new.faculty@cspc.edu.ph'])
            ->assertOk()
            ->assertJson(['success' => true, 'created' => true]);

        $expert = CapstoneExpert::where('email', 'new.faculty@cspc.edu.ph')->first();

        $this->assertNotNull($expert);
        $this->assertSame('', trim($expert->name), 'the name waits for their first sign-in');
        $this->assertCount(0, $expert->roles, 'the CaPSA/TSA sets the role when they decide it');
    }

    /** The address is still the one thing that must be there. */
    public function test_an_empty_address_is_refused(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->postJson('/capsa/experts', ['name' => 'No Address'])
            ->assertStatus(422);
    }

    /** Giving a role still works, and still only touches student-facing ones. */
    public function test_a_role_given_at_the_start_is_kept(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->postJson('/capsa/experts', [
                'email' => 'adviser.now@cspc.edu.ph',
                'roles' => ['adviser'],
            ])->assertOk();

        $expert = CapstoneExpert::where('email', 'adviser.now@cspc.edu.ph')->first();

        $this->assertSame(['adviser'], $expert->roles->pluck('role_name')->all());
    }

    /** Adding an existing expert with no roles does not strip the ones they have. */
    public function test_adding_again_without_roles_leaves_their_roles_alone(): void
    {
        $existing = CapstoneExpert::factory()->create([
            'email' => 'already@cspc.edu.ph', 'approved_status' => true, 'is_admin' => false,
        ]);
        $existing->roles()->attach(Role::firstOrCreate(['role_name' => 'consultant'])->role_id);

        $this->actingAs($this->capsa(), 'expert')
            ->postJson('/capsa/experts', ['email' => 'already@cspc.edu.ph'])
            ->assertOk();

        $this->assertSame(['consultant'], $existing->fresh()->roles->pluck('role_name')->all());
    }
}
