<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Each course has its own defense window. A row with no program is the
    // window set before this, and still serves any course without its own.
    public function up(): void
    {
        Schema::table('defense_windows', function (Blueprint $table) {
            $table->string('program', 10)->nullable();
            $table->dropUnique(['defense_type']);
            $table->unique(['defense_type', 'program']);
        });
    }

    public function down(): void
    {
        Schema::table('defense_windows', function (Blueprint $table) {
            $table->dropUnique(['defense_type', 'program']);
            $table->unique('defense_type');
            $table->dropColumn('program');
        });
    }
};
