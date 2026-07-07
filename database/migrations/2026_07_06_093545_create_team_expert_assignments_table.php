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

            $table->unique(['team_id', 'expert_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_expert_assignments');
    }
};
