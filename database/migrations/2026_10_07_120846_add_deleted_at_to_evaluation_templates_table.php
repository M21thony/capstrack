<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A CaPSA can delete an evaluation they turned off. One that team leaders
 * already answered is only hidden, so its answers stay in the results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_templates', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_templates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
