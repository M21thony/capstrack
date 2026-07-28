<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->unsignedBigInteger('milestone_id')->nullable()->after('team_id');
            $table->foreign('milestone_id')->references('id')->on('milestones')->onDelete('set null');
            $table->index('milestone_id');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['milestone_id']);
            $table->dropIndex(['milestone_id']);
            $table->dropColumn('milestone_id');
        });
    }
};
