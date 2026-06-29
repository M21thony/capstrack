<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->boolean('approved_status')->default(false)->after('is_admin');
            $table->unsignedSmallInteger('max_teams')->default(4)->after('approved_status');
            $table->string('department', 100)->nullable()->after('max_teams');
            $table->text('rejection_reason')->nullable()->after('department');
            $table->unsignedInteger('reject_attempts')->default(0)->after('rejection_reason');
            $table->dateTime('reject_cooldown_until')->nullable()->after('reject_attempts');
            $table->text('google_token')->nullable()->after('reject_cooldown_until');
            $table->dateTime('google_token_expires_at')->nullable()->after('google_token');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn(['approved_status', 'max_teams', 'department', 'rejection_reason', 'reject_attempts', 'reject_cooldown_until', 'google_token', 'google_token_expires_at']);
        });
    }
};