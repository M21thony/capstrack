<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            // How many times the admin rejected this expert.
            $table->unsignedInteger('reject_attempts')->default(0)->after('rejection_reason');

            // Timestamp until when the expert is blocked from submitting new requests.
            // This is used for the 1-minute cooldown rule after max rejects.
            $table->dateTime('reject_cooldown_until')->nullable()->after('reject_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn(['reject_attempts', 'reject_cooldown_until']);
        });
    }
};
