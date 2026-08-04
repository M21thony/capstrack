<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'completed', 'overdue'])->default('todo');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('team_id')->references('team_id')->on('team')->cascadeOnDelete();
            $table->index('team_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
