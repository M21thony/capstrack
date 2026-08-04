<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->json('target_audience')->nullable()->after('priority');
            $table->unsignedBigInteger('team_id')->nullable()->after('target_audience');

            $table->foreign('team_id')
                ->references('team_id')
                ->on('team')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn(['target_audience', 'team_id']);
        });
    }
};
