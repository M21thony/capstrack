<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Calendar's "booked appointment settings": time left between
 * appointments, and a cap on how many an expert accepts in one day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_availability', function (Blueprint $table) {
            $table->unsignedSmallInteger('buffer_minutes')->default(0)->after('duration_minutes');
            // Null means no cap.
            $table->unsignedSmallInteger('max_per_day')->nullable()->after('buffer_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_availability', function (Blueprint $table) {
            $table->dropColumn(['buffer_minutes', 'max_per_day']);
        });
    }
};
