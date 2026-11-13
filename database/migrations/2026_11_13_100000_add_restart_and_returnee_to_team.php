<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team', function (Blueprint $table) {
            // Sent back to the start of Capstone 1: milestone work from before this stops counting.
            $table->timestamp('restarted_at')->nullable();
            // Sent back to retake only this stage (Pre Oral or Final Oral Defense).
            $table->string('returnee_stage')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('team', fn (Blueprint $table) => $table->dropColumn(['restarted_at', 'returnee_stage']));
    }
};
