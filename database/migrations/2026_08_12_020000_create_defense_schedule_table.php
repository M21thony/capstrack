<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defense_schedule', function (Blueprint $table) {
            $table->id('defense_id');
            $table->unsignedBigInteger('team_id');
            $table->enum('defense_type', [
                'Pre-proposal Defense',
                'Title Defense',
                'Pre Oral Defense',
                'Final Oral Defense',
            ])->nullable();
            $table->dateTime('schedule_date')->nullable();
            $table->enum('venue', ['Mac Lab', 'Open Lab'])->nullable();

            $table->foreign('team_id')->references('team_id')->on('team')->onDelete('cascade');
            $table->unique(['team_id', 'defense_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_schedule');
    }
};
