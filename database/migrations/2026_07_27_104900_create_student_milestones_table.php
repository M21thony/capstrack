<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('milestone_id');
            $table->unsignedBigInteger('team_id');
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->foreign('milestone_id')->references('id')->on('milestones')->cascadeOnDelete();
            $table->foreign('team_id')->references('team_id')->on('team')->cascadeOnDelete();
            $table->unique(['milestone_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_milestones');
    }
};
