<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team Rating rubrics a CaPSA/TSA writes herself. The department's own
 * rubric stays in code (PeerRatingRubric) and needs no row here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_rating_rubrics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // [{name, short, levels: [five descriptors, best first]}]
            $table->json('criteria');
            // The one new ratings are made with. None in use means the department's.
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_rating_rubrics');
    }
};
