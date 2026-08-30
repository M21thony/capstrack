<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_rosters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // Matching at login only works by email - that's all Google OAuth
            // returns - so a roster row without one can never gate anyone in.
            // Nullable only so a CAPSA upload can still record a name/section
            // it couldn't fully use, rather than failing the whole row.
            $table->string('email', 250)->nullable()->unique();
            $table->string('student_number', 50)->nullable();
            $table->string('section', 50);
            $table->string('program', 20); // BSIT/BSCS/BSIS/BLIS
            $table->string('school_year', 20);
            $table->foreignId('imported_by')->nullable()
                ->constrained('capstone_experts', 'expert_id')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_rosters');
    }
};
