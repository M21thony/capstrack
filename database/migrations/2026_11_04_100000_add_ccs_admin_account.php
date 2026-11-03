<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The department's own account as a second admin, beside the one there is.
 * Its name is a stand-in until the first Google sign-in replaces it.
 */
return new class extends Migration
{
    private const EMAIL = 'ccs@cspc.edu.ph';

    public function up(): void
    {
        if (DB::table('capstone_experts')->where('email', self::EMAIL)->exists()) {
            DB::table('capstone_experts')->where('email', self::EMAIL)
                ->update(['is_admin' => true, 'approved_status' => true, 'deactivated_at' => null]);

            return;
        }

        DB::table('capstone_experts')->insert([
            'email' => self::EMAIL,
            'first_name' => 'CCS',
            'last_name' => 'Admin',
            'is_admin' => true,
            'approved_status' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('capstone_experts')->where('email', self::EMAIL)->delete();
    }
};
