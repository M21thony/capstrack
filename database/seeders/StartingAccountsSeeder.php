<?php

namespace Database\Seeders;

use App\Models\CapstoneExpert;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The accounts a new install starts with, so somebody can sign in on day one.
 *
 * The Docker start script runs this on every start. An address that already
 * has an account - even a removed one - is left exactly as it is, so a later
 * change made in the app is never undone by a restart.
 *
 * Names are left blank: Google fills them in on the person's first sign-in.
 */
class StartingAccountsSeeder extends Seeder
{
    private const ADMIN = 'markanthonybalilla@gmail.com';

    private const CAPSA = 'markanthonybalilla21@gmail.com';

    public function run(): void
    {
        // No migration puts the roles in, and a CaPSA/TSA is a role.
        $this->call(RoleSeeder::class);

        $this->start(self::ADMIN, ['is_admin' => true]);

        $capsa = $this->start(self::CAPSA);

        if ($capsa) {
            DB::table('expert_roles')->insertOrIgnore([
                'expert_id' => $capsa->expert_id,
                'role_id' => DB::table('roles')->where('role_name', CapstoneExpert::CAPSA_ROLE)->value('role_id'),
            ]);
        }
    }

    /** The new account, or null when the address already had one. */
    private function start(string $email, array $extra = []): ?CapstoneExpert
    {
        if (CapstoneExpert::withTrashed()->where('email', $email)->exists()) {
            return null;
        }

        return CapstoneExpert::create($extra + [
            'email' => $email,
            'first_name' => '',
            'last_name' => '',
            'approved_status' => true,
            'reject_attempts' => 0,
        ]);
    }
}
