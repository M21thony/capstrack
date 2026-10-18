<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a team brings to a consultation, so the expert can prepare: the
 * topics (paper, system, diagrams, manuscript - see ConsultationTopics) and
 * an optional note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->json('topics')->nullable()->after('booking_status');
            $table->text('note')->nullable()->after('topics');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->dropColumn(['topics', 'note']);
        });
    }
};
