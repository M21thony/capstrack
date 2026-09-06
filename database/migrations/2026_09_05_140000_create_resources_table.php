<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The resources table had no migration.
 *
 * It exists in the running database because somebody made it by hand, which
 * meant a fresh clone came up without it and nothing touching resources could
 * be tested at all - the test database is built from migrations. This is the
 * live schema written down, guarded so it is a no-op where the table is
 * already there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resources')) {
            return;
        }

        Schema::create('resources', function (Blueprint $table) {
            $table->increments('resource_id');
            $table->enum('resource_type', ['template', 'form', 'lesson']);
            $table->string('category', 50)->nullable();

            // Null means every program. Anything else is one program's
            // material, which is what keeps BSIT's templates off a BSIS
            // student's page.
            $table->enum('resource_for', ['BSIT', 'BSCS', 'BLIS', 'BSIS'])->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->enum('content_type', ['file', 'link'])->default('file');
            $table->string('external_url', 500)->nullable();

            // Google Drive is where the file actually lives; these carry the
            // sync back to it.
            $table->string('drive_file_id')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('drive_web_view_link', 500)->nullable();

            $table->boolean('is_downloadable')->default(true);
            $table->unsignedInteger('downloads')->default(0);
            $table->dateTime('uploaded_at')->nullable();

            // The CaPSA who uploaded it. They see their own and nobody else's.
            $table->integer('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
