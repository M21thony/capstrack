<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One date taken out of a weekly slot.
 *
 * A slot stores a weekday, not a date - "Monday, 10:00-11:00" repeats for as
 * long as it exists. So an adviser who wanted to move a single week had
 * nothing to move: editing the slot rewrote every Monday, and the week they
 * were actually looking at vanished into the change along with all the
 * others.
 *
 * A row here says: this weekly slot does not happen on this one date. That is
 * the whole mechanism. Moving one week is then an exception plus a one-off
 * slot on the same date, and skipping a week is the exception on its own -
 * both without touching the weekly rule that every other week depends on.
 *
 * The pair is unique because the same date cannot be excluded twice, and the
 * uniqueness is what makes "skip this week" safe to press more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_schedule_exception', function (Blueprint $table) {
            $table->id('exception_id');
            $table->unsignedBigInteger('schedule_id');
            $table->date('exception_date');
            $table->timestamps();

            $table->unique(['schedule_id', 'exception_date'], 'schedule_date_unique');
            $table->index('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_schedule_exception');
    }
};
