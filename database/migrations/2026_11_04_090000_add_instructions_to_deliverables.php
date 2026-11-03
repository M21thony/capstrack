<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the CaPSA tells teams about a requirement: what to hand in and how. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });
    }
};
