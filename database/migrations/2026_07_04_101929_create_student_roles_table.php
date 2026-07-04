<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_roles')) {
            Schema::create('student_roles', function (Blueprint $table) {
                $table->integer('s_role_id', true);
                $table->string('s_role_name', 100)->nullable();
                $table->text('s_role_description')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_roles');
    }
};
