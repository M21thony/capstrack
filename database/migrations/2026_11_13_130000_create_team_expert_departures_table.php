<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An expert who leaves a team is deleted from capstone_expert_application,
 * which left no way to know who was there for a defense already passed. The
 * leader evaluates the expert who was there, so their leaving is kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_expert_departures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('expert_id');
            $table->string('role', 30);
            $table->timestamp('left_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_expert_departures');
    }
};
