<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->timestamp('sent_to_dean_at')->nullable();
            $table->string('sent_to_dean_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropColumn(['sent_to_dean_at', 'sent_to_dean_by']);
        });
    }
};
