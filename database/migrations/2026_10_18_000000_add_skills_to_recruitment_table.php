<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel comment 2: a post says which skills it needs, picked from the same
 * catalogue experts use on their profile, so the feed can be filtered by one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment', function (Blueprint $table) {
            $table->json('skills')->nullable()->after('role_tags');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment', function (Blueprint $table) {
            $table->dropColumn('skills');
        });
    }
};
