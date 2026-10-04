<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a post was tagged with, from Create Post's six choices - Leader,
 * Member, and Primary or Secondary of each of the two member roles - as a
 * list of keys (see Recruitment::ROLE_OPTIONS). Kept beside `roles`, which
 * still holds the team role names the join flow matches on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment', function (Blueprint $table) {
            $table->json('role_tags')->nullable()->after('roles');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment', function (Blueprint $table) {
            $table->dropColumn('role_tags');
        });
    }
};
