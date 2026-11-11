<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Switching an account off has to actually stop it signing in - and the
 * Admin's has to be the one account nobody can switch off.
 *
 * The Admin runs the system. There is no second one, and nobody above them
 * to put it right, so an Admin locked out is the whole thing locked out.
 */
class TheAdminCannotBeLockedOutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::factory()->create([
            'email' => 'admin@cspc.edu.ph', 'is_admin' => true, 'approved_status' => true,
        ]);
    }

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'email' => 'capsa@cspc.edu.ph', 'approved_status' => true, 'is_admin' => false,
        ]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function adviser(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'email' => 'adviser@cspc.edu.ph', 'approved_status' => true, 'is_admin' => false,
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    /** The gate the Google callback runs for staff. */
    private function maySignIn(string $email): bool
    {
        $expert = CapstoneExpert::withTrashed()->where('email', $email)->first();

        return $expert && ! $expert->deactivated_at && $expert->approved_status;
    }

    // ── switching off has to work ─────────────────────────────────────────

    public function test_a_deactivated_adviser_cannot_sign_in(): void
    {
        $expert = $this->adviser();
        $this->assertTrue($this->maySignIn($expert->email));

        $this->actingAs($this->admin(), 'expert')
            ->deleteJson('/admin/experts/'.$expert->expert_id, ['reason' => 'On study leave'])
            ->assertOk();

        $this->assertFalse($this->maySignIn($expert->email), 'deactivated and still admitted');
    }

    public function test_a_deactivated_capsa_cannot_sign_in(): void
    {
        $capsa = $this->capsa();

        $this->actingAs($this->admin(), 'expert')
            ->deleteJson('/admin/experts/'.$capsa->expert_id, ['reason' => 'Stepped down'])
            ->assertOk();

        $this->assertFalse($this->maySignIn($capsa->email));
    }

    // ── except the Admin ──────────────────────────────────────────────────

    /** A CaPSA/TSA reaching the Admin's id by hand must not switch them off. */
    public function test_a_capsa_cannot_deactivate_the_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($this->capsa(), 'expert')
            ->deleteJson('/capsa/experts/'.$admin->expert_id, ['reason' => 'oops'])
            ->assertStatus(422);

        $this->assertTrue($this->maySignIn($admin->email), 'the Admin was locked out');
    }

    /** Nor may the Admin switch themselves off - there is nobody to undo it. */
    public function test_the_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'expert')
            ->deleteJson('/admin/experts/'.$admin->expert_id, ['reason' => 'testing'])
            ->assertStatus(422);

        $this->assertTrue($this->maySignIn($admin->email));
    }

    /** Editing the address is a lock-out by another route. */
    public function test_a_capsa_cannot_edit_the_admins_address(): void
    {
        $admin = $this->admin();

        $this->actingAs($this->capsa(), 'expert')
            ->putJson('/capsa/experts/'.$admin->expert_id, [
                'first_name' => 'Not', 'last_name' => 'Them',
                'email' => 'somewhere.else@cspc.edu.ph',
            ])
            ->assertStatus(422);

        $this->assertSame('admin@cspc.edu.ph', $admin->fresh()->email);
    }

    /** And the refusal must not tell a CaPSA/TSA which account this is. */
    public function test_the_refusal_does_not_identify_the_admin(): void
    {
        $admin = $this->admin();

        $message = $this->actingAs($this->capsa(), 'expert')
            ->deleteJson('/capsa/experts/'.$admin->expert_id, ['reason' => 'x'])
            ->json('message');

        foreach (['admin', 'administrator', 'system'] as $giveaway) {
            $this->assertStringNotContainsStringIgnoringCase(
                $giveaway,
                $message,
                'the directory hides the Admin; the refusal must not hand them back'
            );
        }
    }
}
