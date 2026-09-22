<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A milestone now opens only once the one before it is complete, unless
     * the CaPSA unticks it for the ones where order does not matter.
     *
     * The adviser asked for this as the rule, not an option nobody finds, so
     * the existing milestones are turned on too. The first milestone's flag
     * does nothing - there is nothing before it.
     */
    public function up(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->boolean('requires_previous')->default(true)->change();
        });

        DB::table('checklist_milestones')->update(['requires_previous' => true]);
    }

    /** Which ones were off before cannot be known, so only the default goes back. */
    public function down(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->boolean('requires_previous')->default(false)->change();
        });
    }
};
