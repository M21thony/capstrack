<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS `archived_doc_tags` (
              `tag_id` INT PRIMARY KEY AUTO_INCREMENT,
              `tag_name` VARCHAR(100),
              `tag_color` VARCHAR(10)
            )
        ');

        DB::statement('
            CREATE TABLE IF NOT EXISTS `archived_documents` (
              `archived_id` INT PRIMARY KEY AUTO_INCREMENT,
              `team_id` INT NOT NULL,
              `title` VARCHAR(255) NOT NULL,
              `abstract` TEXT,
              `academic_year` YEAR NOT NULL,
              `program` VARCHAR(50) NOT NULL,
              `file_name` VARCHAR(255),
              `file_path` VARCHAR(500) NOT NULL,
              `file_size` BIGINT
            )
        ');

        DB::statement('
            CREATE TABLE IF NOT EXISTS `archived_tag_relation` (
              `archive_id` INT NOT NULL,
              `tag_id` INT NOT NULL
            )
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `archived_tag_relation`');
        DB::statement('DROP TABLE IF EXISTS `archived_documents`');
        DB::statement('DROP TABLE IF EXISTS `archived_doc_tags`');
    }
};