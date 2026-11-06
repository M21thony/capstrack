<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Which pages' and tabs' tours an account has finished or skipped, so each opens by itself once. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['students', 'capstone_experts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('page_tours_seen')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['students', 'capstone_experts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('page_tours_seen');
            });
        }
    }
};
