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
        Schema::create('expert_expertise', function (Blueprint $table) {
            $table->id('expertise_id');
            $table->unsignedBigInteger('expert_id');
            $table->string('expertise_field', 100);
            $table->foreign('expert_id')->references('expert_id')->on('capstone_experts')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expert_expertise');
    }
};
