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
        Schema::create('consultation_booking', function (Blueprint $table) {
            $table->integer('booking_id', true);
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('schedule_id');
            $table->enum('booking_status', ['scheduled', 'done', 'rescheduled', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->foreign('student_id')
                ->references('student_id')
                ->on('students')
                ->onDelete('cascade');

            $table->foreign('schedule_id')
                ->references('schedule_id')
                ->on('consultation_schedule')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_booking');
    }
};
