<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An expert's consultation availability, set the way Google Calendar's
 * appointment schedules are: how long each appointment runs, the hours they
 * are generally free on each weekday, and the window teams may book within.
 *
 * The bookable weekly slots are still rows in consultation_schedule, cut from
 * these hours; this keeps the settings they were cut from and the window a
 * booking has to fall inside.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_availability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id')->unique();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            // {"Monday": [["09:00","12:00"], ["13:00","16:00"]], ...}
            $table->json('hours')->nullable();
            // The scheduling window. Null dates mean "available now, until changed".
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // How far ahead a team may book, and how much notice they must give.
            $table->unsignedSmallInteger('max_days_ahead')->nullable()->default(60);
            $table->unsignedSmallInteger('min_notice_hours')->default(24);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_availability');
    }
};
