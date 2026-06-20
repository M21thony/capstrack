<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_s_y', function (Blueprint $table) {
            $table->id('s_y_id');
            $table->integer('student_id');
            $table->integer('team_id')->nullable();
            $table->integer('s_role_id')->nullable();
            $table->year('school_year')->nullable();
            $table->enum('program', ['BSIT', 'BSCS', 'BLIS', 'BSIS'])->nullable();
            $table->enum('student_status', ['Failed', 'Passed', 'Active'])->default('Active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_s_y');
    }
};
