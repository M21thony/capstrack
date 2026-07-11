<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_records', 'expense_post_id')) {
                $table->unsignedInteger('expense_post_id')->nullable()->after('student_id');
            }
            if (! Schema::hasColumn('financial_records', 'amount')) {
                $table->decimal('amount', 10, 2)->nullable()->after('payment_for');
            }
            if (! Schema::hasColumn('financial_records', 'description')) {
                $table->text('description')->nullable()->after('amount');
            }
            if (! Schema::hasColumn('financial_records', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('verified_at');
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE financial_records MODIFY COLUMN f_record_status ENUM('verified','pending','rejected') DEFAULT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason']);
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE financial_records MODIFY COLUMN f_record_status ENUM('verified','pending') DEFAULT NULL");
        }
    }
};
