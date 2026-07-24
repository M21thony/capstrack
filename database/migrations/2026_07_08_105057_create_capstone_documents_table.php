<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capstone_documents', function (Blueprint $table) {
            $table->id('file_id');
            $table->unsignedBigInteger('student_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('file_version')->default(1);
            $table->string('document_status')->default('pending');
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capstone_documents');
    }
};
