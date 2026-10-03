<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A defense date that was called off because the CaPA said the team was not
 * ready. The team keeps its place in line and waits for its CaPSA to give it
 * a new date; this keeps the date it lost, and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defense_cancellations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('defense_id')->index();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('defense_type', 40);
            $table->dateTime('was_scheduled_at');
            $table->string('venue', 40)->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_cancellations');
    }
};
