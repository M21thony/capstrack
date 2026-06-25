<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('student_number', 20)->nullable()->unique()->after('email');
            $table->string('m_name', 50)->nullable()->after('f_name');
            $table->string('course', 100)->nullable()->after('p_picture');
            $table->string('year_section', 50)->nullable()->after('course');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['student_number', 'm_name', 'course', 'year_section']);
        });
    }
};
