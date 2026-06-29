<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recruitment', function (Blueprint $table) {
            $table->tinyInteger('member_count')->unsigned()->nullable()->after('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment', function (Blueprint $table) {
            $table->dropColumn('member_count');
        });
    }
};
