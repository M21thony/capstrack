<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->text('google_token')->nullable()->after('approved_status');
            $table->datetime('google_token_expires_at')->nullable()->after('google_token');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn(['google_token', 'google_token_expires_at']);
        });
    }
};
