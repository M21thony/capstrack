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
        Schema::create('duplication_checks', function (Blueprint $table) {
            $table->id();
            // student_s_y already pairs a student with their team for a
            // school year (StudentSchoolYear::student()/::team()) - a single
            // FK here avoids storing that same pairing a second time.
            $table->unsignedBigInteger('student_s_y_id');
            $table->string('document_path');
            $table->json('concepts');
            $table->unsignedTinyInteger('top_score');
            $table->string('verdict');
            $table->json('matches');
            $table->json('instructors_suggested');
            $table->timestamps();

            $table->foreign('student_s_y_id')->references('s_y_id')->on('student_s_y')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplication_checks');
    }
};
