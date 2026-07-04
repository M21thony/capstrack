<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->integer('member_count')->default(5)->after('team_status');
        });

        DB::table('team')->update(['member_count' => 5]);
    }

    public function down(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->dropColumn('member_count');
        });
    }
};
