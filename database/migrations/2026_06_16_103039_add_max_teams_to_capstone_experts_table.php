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
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_teams')->default(4)->after('is_admin');
            $table->string('department', 100)->nullable()->after('max_teams');
            $table->text('rejection_reason')->nullable()->after('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn(['max_teams', 'department', 'rejection_reason']);
        });
    }
};
