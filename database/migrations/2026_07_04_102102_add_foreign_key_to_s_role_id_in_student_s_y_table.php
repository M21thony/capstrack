<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            $table->unsignedBigInteger('s_role_id')->nullable()->change();
            $table->foreign('s_role_id')
                ->references('s_role_id')
                ->on('student_roles')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            $table->dropForeign(['s_role_id']);
            $table->integer('s_role_id')->nullable()->change();
        });
    }
};
