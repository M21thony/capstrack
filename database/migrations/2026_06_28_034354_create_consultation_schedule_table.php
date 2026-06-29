<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_schedule', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->unsignedBigInteger('expert_id');
            $table->enum('available_date', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('consultation_limit')->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('google_event_id')->nullable();
            $table->timestamps();

            $table->foreign('expert_id')
                ->references('expert_id')
                ->on('capstone_experts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_schedule');
    }
};
