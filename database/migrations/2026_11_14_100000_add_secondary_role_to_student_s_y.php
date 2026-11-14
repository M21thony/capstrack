<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The role a student backs up, beside the one they hold. It takes no seat.
    public function up(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            $table->unsignedBigInteger('secondary_role_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            $table->dropColumn('secondary_role_id');
        });
    }
};
