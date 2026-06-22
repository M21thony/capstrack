<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_doc_tags', function (Blueprint $table) {
            $table->id('tag_id');
            $table->string('tag_name', 100)->nullable();
            $table->string('tag_color', 10)->nullable();
        });

        Schema::create('archived_documents', function (Blueprint $table) {
            $table->id('archived_id');
            $table->unsignedInteger('team_id');
            $table->string('title', 255);
            $table->text('abstract')->nullable();
            $table->year('academic_year');
            $table->string('program', 50);
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->nullable();
        });

        Schema::create('archived_tag_relation', function (Blueprint $table) {
            $table->unsignedInteger('archive_id');
            $table->unsignedInteger('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_tag_relation');
        Schema::dropIfExists('archived_documents');
        Schema::dropIfExists('archived_doc_tags');
    }
};
