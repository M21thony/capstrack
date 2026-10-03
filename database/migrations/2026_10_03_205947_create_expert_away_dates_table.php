<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days a panelist is away - travel, a seminar, sick. They keep their seat on
 * every panel; nobody is scheduled in front of them on those days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_away_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id')->index();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 60);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_away_dates');
    }
};
