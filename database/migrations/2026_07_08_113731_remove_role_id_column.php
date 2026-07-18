<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_expert_application', function (Blueprint $table) {
            $table->dropForeign(['role_id']); // remove if this throws — see note below
            $table->dropColumn('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_expert_application', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('expert_id');
            $table->unique(['team_id', 'expert_id', 'role_id']);
        });
    }
};
