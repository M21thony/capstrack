<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team', function (Blueprint $table) {
            // The live column's default had drifted to 1 (every migration
            // that (re)created this column specified default(false)/0, but
            // the actual running schema disagreed) - MySQL was silently
            // marking every newly-created team as admin-verified the
            // instant it was inserted, since nothing in the app code sets
            // is_confirmed at creation time; it relied entirely on the
            // column's own default.
            $table->boolean('is_confirmed')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->boolean('is_confirmed')->default(true)->change();
        });
    }
};
