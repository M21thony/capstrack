<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            $table->index('student_id');
            $table->index('team_id');
            $table->index('school_year');
            $table->index(['school_year', 'team_id']);
            $table->index(['school_year', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            $table->dropIndex(['student_id']);
            $table->dropIndex(['team_id']);
            $table->dropIndex(['school_year']);
            $table->dropIndex(['school_year', 'team_id']);
            $table->dropIndex(['school_year', 'student_id']);
        });
    }
};
