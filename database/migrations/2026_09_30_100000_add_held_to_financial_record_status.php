<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'held' is a receipt that a member has uploaded and their team leader has not
 * yet sent to the CaPSA/TSA. The CaPSA/TSA only ever sees 'pending' and after.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE financial_records MODIFY COLUMN f_record_status ENUM('held','verified','pending','rejected') DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::table('financial_records')->where('f_record_status', 'held')->update(['f_record_status' => 'pending']);
            DB::statement("ALTER TABLE financial_records MODIFY COLUMN f_record_status ENUM('verified','pending','rejected') DEFAULT NULL");
        }
    }
};
