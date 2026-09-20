<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How long each defense of a stage runs; its start times follow from it.
        Schema::table('defense_windows', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(120);
        });

        // Rooms are listed in DefenseBoard::VENUES; an enum here would need a
        // migration for every new room.
        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->string('venue', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('defense_windows', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });

        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->enum('venue', ['Mac Lab', 'Open Lab'])->nullable()->change();
        });
    }
};
