<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('team')->update(['member_count' => 5]);

        DB::table('recruitment')->where('recruitment_goal', 'build_a_team')->update(['member_count' => 5]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
