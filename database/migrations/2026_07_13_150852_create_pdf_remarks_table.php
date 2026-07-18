<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_create_pdf_remarks_table.php
    public function up(): void
    {
        Schema::create('pdf_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')
                ->constrained('capstone_document_review', 'review_id')
                ->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->decimal('top_position', 8, 6)->default(0);
            $table->decimal('left_position', 8, 6)->default(0);
            $table->decimal('highlight_width', 8, 6)->default(0);
            $table->decimal('highlight_height', 8, 6)->default(0);
            $table->string('color', 20)->default('yellow');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_remarks');
    }
};
