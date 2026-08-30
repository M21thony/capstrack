<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->boolean('is_capsa')->default(false)->after('is_admin');
        });

        // Whoever currently holds is_admin has been doing both jobs at once
        // (there was no split before this) - keep them working as CAPSA too
        // immediately after this deploys, until Admin reassigns it properly
        // through the new toggle.
        DB::table('capstone_experts')->where('is_admin', true)->update(['is_capsa' => true]);
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('is_capsa');
        });
    }
};
