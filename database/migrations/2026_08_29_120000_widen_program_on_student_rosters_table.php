<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The original 20-char cap assumed a short code (BSIT/BSCS/BSIS/BLIS), but
// real CAPSA roster files spell out the full program name (e.g. "Bachelor
// of Science in Information Technology"), which doesn't fit - matching
// students.course, which has no explicit length cap at all.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_rosters', function (Blueprint $table) {
            $table->string('program', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_rosters', function (Blueprint $table) {
            $table->string('program', 20)->change();
        });
    }
};
