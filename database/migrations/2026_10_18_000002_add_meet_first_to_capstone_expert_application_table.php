<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel comment 11: an expert can answer a request with "Meet first" - a note
 * to the team, who then book one of the expert's consultation times. The
 * request stays pending; the expert approves or declines after the meeting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_expert_application', function (Blueprint $table) {
            $table->text('meet_note')->nullable();
            $table->timestamp('meet_asked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('capstone_expert_application', function (Blueprint $table) {
            $table->dropColumn(['meet_note', 'meet_asked_at']);
        });
    }
};
