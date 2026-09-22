<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teams start Chapter 3 while still revising Chapter 2, so the chapters
     * after the first open together with it instead of each waiting for the
     * one before. A new milestone opens with the one above it unless the
     * CaPSA ticks its gate.
     */
    public function up(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->boolean('requires_previous')->default(false)->change();
        });

        DB::table('checklist_milestones')->whereIn('name', ['Chapter 2', 'Chapter 3'])->update(['requires_previous' => false]);
    }

    public function down(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->boolean('requires_previous')->default(true)->change();
        });

        DB::table('checklist_milestones')->whereIn('name', ['Chapter 2', 'Chapter 3'])->update(['requires_previous' => true]);
    }
};
