<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id('student_id');
            $table->string('email', 100);
            $table->string('f_name', 50)->nullable();
            $table->string('l_name', 50)->nullable();
            $table->string('p_picture', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
