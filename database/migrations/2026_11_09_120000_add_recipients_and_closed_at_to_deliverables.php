<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a requirement's uploads go to, set by the CaPSA/TSA (null keeps the usual
 * people), and when the CaPSA/TSA closed it to further uploads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->json('recipients')->nullable()->after('role');
            $table->timestamp('closed_at')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn(['recipients', 'closed_at']);
        });
    }
};
