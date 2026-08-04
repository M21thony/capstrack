<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_drive_folders')) {
            Schema::create('admin_drive_folders', function (Blueprint $table) {
                $table->id();
                $table->string('drive_folder_id');
                $table->string('name');
                $table->timestamps();
            });
        }

        Schema::table('admin_drive_folders', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_drive_folders', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('drive_folder_id');
            }
            if (! Schema::hasColumn('admin_drive_folders', 'parent_folder_id')) {
                $table->unsignedBigInteger('parent_folder_id')->nullable()->after('team_id');
            }
        });

        Schema::table('admin_drive_folders', function (Blueprint $table) {
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
