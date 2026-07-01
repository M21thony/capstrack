<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->unsignedBigInteger('leader_id')->nullable()->after('team_id');
            $table->boolean('is_confirmed')->default(false)->after('team_status');
        });
    }

    public function down(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->dropColumn(['leader_id', 'is_confirmed']);
        });
    }
};
