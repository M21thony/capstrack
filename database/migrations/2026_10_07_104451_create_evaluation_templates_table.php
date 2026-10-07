<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The questions a CaPSA asks team leaders about their experts after a
 * defense: one set per defense stage and the roles it covers (adviser,
 * consultant, grammarian), each question answered on a 1-5 scale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_templates', function (Blueprint $table) {
            $table->id();
            // The CaPSA who wrote it; it applies to the teams in their sections.
            $table->unsignedBigInteger('capsa_id');
            $table->string('title', 150);
            // Pre-proposal Defense, Title Defense, Pre Oral Defense or Final Oral Defense.
            $table->string('defense_type', 40);
            // Which engaged experts it is about: adviser, consultant, grammarian.
            $table->json('roles');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['capsa_id', 'defense_type', 'is_active']);
        });

        Schema::create('evaluation_template_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_template_id');
            $table->string('question', 300);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('evaluation_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_template_questions');
        Schema::dropIfExists('evaluation_templates');
    }
};
