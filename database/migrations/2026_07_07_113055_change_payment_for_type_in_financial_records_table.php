<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->string('payment_for', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE financial_records MODIFY payment_for ENUM('TD','POD','FOD') DEFAULT NULL");
    }
};
