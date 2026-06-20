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
            $table->text('looking_for')->nullable()->after('recruitment_goal');
            $table->text('introduction')->nullable()->after('looking_for');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment', function (Blueprint $table) {
            $table->dropColumn(['looking_for', 'introduction']);
        });
    }
};
