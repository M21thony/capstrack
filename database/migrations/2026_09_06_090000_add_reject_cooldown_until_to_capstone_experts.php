<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three-day wait after a third rejected sign-up request.
 *
 * The column is declared in create_capstone_experts_table, which ran in June -
 * and was edited afterwards to add it. Laravel never re-runs a migration it
 * has already recorded, so an install that predates the edit has the table
 * without the column, and `migrate:status` reports nothing pending. It is
 * invisible until somebody rejects an expert, and then
 * AdminApprovalController::reject() writes to a column that is not there.
 *
 * Guarded rather than plain, because a database built from these migrations
 * today already has it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('capstone_experts', 'reject_cooldown_until')) {
            return;
        }

        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dateTime('reject_cooldown_until')->nullable();
        });
    }

    public function down(): void
    {
        // Deliberately empty. The column belongs to create_capstone_experts_table
        // as far as every other install is concerned; dropping it here would
        // take it away from those too.
    }
};
