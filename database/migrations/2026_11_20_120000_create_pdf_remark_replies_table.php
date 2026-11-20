<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_remark_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remark_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_remark_replies');
    }
};
