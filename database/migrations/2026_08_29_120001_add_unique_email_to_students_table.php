<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// students.email had no unique constraint at all - nothing stopped two rows
// from sharing the same email. That's a real problem for the CAPSA roster
// sync (AuthController::handleGoogleCallback() / StudentRosterController),
// which matches an uploaded roster row to "the" existing student account by
// email - if two rows can share one email, "the" account is ambiguous and
// a sync could silently update the wrong one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }
};
