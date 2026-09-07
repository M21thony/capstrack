<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_expert_assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('expert_id');
            $table->unsignedBigInteger('role_id');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('team_id')->references('team_id')->on('team')->onDelete('cascade');
            $table->foreign('expert_id')->references('expert_id')->on('capstone_experts')->onDelete('cascade');
            $table->foreign('role_id')->references('role_id')->on('roles')->onDelete('cascade');

            // No unique(['team_id', 'expert_id']) here any more. It was
            // written when this table also had role_id, meaning "one role per
            // expert per team"; role_id went in
            // 2026_07_08_113731_remove_role_id_column and left the constraint
            // saying "one row per expert per team, ever" - which stops a team
            // applying again after being turned down, the normal course of
            // that page. StudentExpertController::store() enforces what was
            // actually meant.
            //
            // A later migration drops it for databases that already have it.
            // That drop cannot reach sqlite, where unique() inside create()
            // becomes an unnamed inline table constraint, so it has to not be
            // created in the first place - otherwise every test carries a
            // constraint production does not have.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_expert_assignments');
    }
};
