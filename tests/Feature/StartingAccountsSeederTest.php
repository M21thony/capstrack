<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Database\Seeders\StartingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A new install starts with one admin and one CaPSA/TSA who can sign in,
 * and running it again on every start changes nothing.
 */
class StartingAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_install_gets_an_admin_and_a_capsa_and_a_restart_leaves_them_alone(): void
    {
        $this->seed(StartingAccountsSeeder::class);

        $admin = CapstoneExpert::where('email', 'markanthonybalilla@gmail.com')->firstOrFail();
        $capsa = CapstoneExpert::where('email', 'markanthonybalilla21@gmail.com')->firstOrFail();

        $this->assertTrue($admin->is_admin && $admin->approved_status && ! $admin->is_capsa);
        $this->assertTrue($capsa->is_capsa && $capsa->approved_status && ! $capsa->is_admin);

        // Something changed in the app afterwards must survive the next start.
        $capsa->update(['first_name' => 'Mark']);
        $admin->delete();

        $this->seed(StartingAccountsSeeder::class);

        $this->assertSame(2, CapstoneExpert::withTrashed()->count());
        $this->assertSame('Mark', $capsa->fresh()->first_name);
        $this->assertNull(CapstoneExpert::where('email', 'markanthonybalilla@gmail.com')->first());
    }
}
