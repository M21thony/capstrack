<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->string('team_status', 20)->default('on-going')->change();
        });

        DB::table('team')->whereNotIn('team_status', ['on-going', 'complete', 'closed'])
            ->update(['team_status' => 'on-going']);
    }

    public function down(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->string('team_status', 20)->default('Active')->change();
        });
    }
};
