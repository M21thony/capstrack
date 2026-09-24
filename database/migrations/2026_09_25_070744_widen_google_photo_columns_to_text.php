<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Google account's photo address is saved on every sign-in, and a school
 * account's can run past 255 characters (the "a-/ALV-Uj..." kind). MySQL
 * refused it, so those students hit a 500 on every sign-in and could not get
 * in at all. Text holds any length; experts sign in the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->text('p_picture')->nullable()->change();
        });

        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->text('profile_picture')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('p_picture', 255)->nullable()->change();
        });

        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->string('profile_picture', 255)->nullable()->change();
        });
    }
};
