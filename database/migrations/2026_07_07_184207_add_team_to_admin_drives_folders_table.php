<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_drive_folders', function (Blueprint $table) {
            // nullable: existing resource-library folders have no team;
            // submission subfolders will have both a team_id and a parent_folder_id
            $table->unsignedBigInteger('team_id')->nullable()->after('drive_folder_id');
            $table->unsignedBigInteger('parent_folder_id')->nullable()->after('team_id');

            $table->foreign('team_id')->references('team_id')->on('team')->onDelete('cascade');
            $table->foreign('parent_folder_id')->references('id')->on('admin_drive_folders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('admin_drive_folders', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['parent_folder_id']);
            $table->dropColumn(['team_id', 'parent_folder_id']);
        });
    }
};
