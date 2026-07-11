<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_records', 'receipt_number')) {
                $table->string('receipt_number', 100)->nullable()->after('description');
            }
            if (! Schema::hasColumn('financial_records', 'verified_by')) {
                $table->string('verified_by', 255)->nullable()->after('verified_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropColumn(['receipt_number', 'verified_by']);
        });
    }
};
