<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a milestone opens only once the one before it is complete.
     *
     * The gate is the CaPSA/TSA's to set, one milestone at a time: a department
     * that lets teams draft Chapter 2 while Chapter 1 is still with the
     * adviser leaves it off, one that does not turns it on. Off by default,
     * so every existing checklist behaves exactly as it did.
     */
    public function up(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->boolean('requires_previous')->default(false)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->dropColumn('requires_previous');
        });
    }
};
