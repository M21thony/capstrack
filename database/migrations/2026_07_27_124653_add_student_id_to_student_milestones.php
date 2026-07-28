<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_milestones', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable()->after('team_id');
            $table->foreign('student_id')->references('student_id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_milestones', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
        });
    }
};
