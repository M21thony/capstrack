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
        Schema::create('capstone_experts', function (Blueprint $table) {
            $table->bigIncrements('expert_id');
            $table->string('email', 250)->unique();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('profile_picture', 255)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->dateTime('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capstone_experts');
    }
};
