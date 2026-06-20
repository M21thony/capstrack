<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expert_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('expert_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('expert_id')->references('expert_id')->on('capstone_experts')->onDelete('cascade');
            $table->foreign('role_id')->references('role_id')->on('roles')->onDelete('cascade');
            $table->primary(['expert_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expert_roles');
    }
};
