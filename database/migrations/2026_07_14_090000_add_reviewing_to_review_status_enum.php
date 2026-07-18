<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the "Reviewing" state to the review_status enum so a document can be
     * marked as being checked (between "To Review" and "Reviewed").
     */
    public function up(): void
    {
        if (Schema::hasColumn('capstone_document_review', 'review_status') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE capstone_document_review MODIFY review_status ENUM('To Review','Reviewing','Reviewed') NOT NULL DEFAULT 'To Review'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('capstone_document_review', 'review_status') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE capstone_document_review MODIFY review_status ENUM('To Review','Reviewed') NOT NULL DEFAULT 'To Review'");
        }
    }
};
