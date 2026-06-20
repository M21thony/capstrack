<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            // Align column types first: students.student_id is bigint unsigned
            $table->unsignedBigInteger('student_id')->change();
            // team.team_id is also bigint unsigned; keep nullable since students can exist without a team
            $table->unsignedBigInteger('team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['team_id']);
            $table->integer('student_id')->change();
            $table->integer('team_id')->nullable()->change();
        });
    }
};
