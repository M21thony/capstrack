<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Not part of the approved ERD - that diagram only captures what
        // gets stored per defense, not admin-configurable scheduling
        // settings. The date range an admin sets per defense stage (e.g.
        // "Title Defense: Aug 20-25") needs somewhere to live so the
        // auto-scheduler knows what window to search within.
        Schema::create('defense_windows', function (Blueprint $table) {
            $table->id('defense_window_id');
            $table->enum('defense_type', [
                'Pre-proposal Defense',
                'Title Defense',
                'Pre Oral Defense',
                'Final Oral Defense',
            ])->unique();
            $table->date('start_date');
            $table->date('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_windows');
    }
};
