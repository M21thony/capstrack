<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capstone_document_review', function (Blueprint $table) {
            $table->id('review_id');
            $table->unsignedBigInteger('file_id');
            $table->unsignedBigInteger('expert_id');
            $table->string('role')->nullable();
            $table->string('review_status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('drive_file_id')->nullable();
            $table->text('drive_web_view_link')->nullable();
            $table->string('drive_status')->nullable();
            $table->text('drive_error')->nullable();
            $table->timestamps();

            $table->foreign('file_id')->references('file_id')->on('capstone_documents')->cascadeOnDelete();
            $table->foreign('expert_id')->references('expert_id')->on('capstone_experts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capstone_document_review');
    }
};
