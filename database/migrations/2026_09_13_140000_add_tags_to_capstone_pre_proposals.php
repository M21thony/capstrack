<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of project a pre-proposal is - "Mobile App", "IoT", "AI / Machine
 * Learning" - picked from a fixed list.
 *
 * Nullable because titles saved before this existed have none, and inventing
 * tags for them would be worse than showing none. Saving a new one requires
 * them; see ProjectTags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('sdm');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
