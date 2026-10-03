<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every section shares a stage's window, agreed at the CaPSAs' meeting. Who
 * last set it, and when, so the others can see it was not changed behind
 * their backs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defense_windows', function (Blueprint $table) {
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('defense_windows', function (Blueprint $table) {
            $table->dropColumn(['updated_by', 'updated_at']);
        });
    }
};
