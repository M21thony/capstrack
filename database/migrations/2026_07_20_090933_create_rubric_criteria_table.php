<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rubric_id');
            $table->string('name');
            $table->decimal('max_score', 5, 2);
            $table->decimal('weight', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('rubric_id')->references('id')->on('rubrics')->cascadeOnDelete();
            $table->index('rubric_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
    }
};
