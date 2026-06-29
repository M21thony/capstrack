<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            // Stores the nomic-embed-text vector as a JSON float array (~768 dimensions)
            $table->json('embedding')->nullable()->after('abstract');
            $table->timestamp('embedding_generated_at')->nullable()->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_generated_at']);
        });
    }
};
