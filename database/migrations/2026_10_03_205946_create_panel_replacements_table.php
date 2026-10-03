<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A panelist on a final panel who can no longer sit - resigned, on long
 * leave - and who took the seat. Defenses already held keep the panelist
 * who sat them; this is the record of why the later ones do not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_replacements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('panel_role', 20);
            $table->unsignedBigInteger('old_expert_id');
            $table->unsignedBigInteger('new_expert_id');
            $table->string('reason');
            $table->unsignedBigInteger('replaced_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_replacements');
    }
};
