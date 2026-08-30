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
        Schema::table('team', function (Blueprint $table) {
            $table->unsignedBigInteger('selected_pre_proposal_id')->nullable()->after('project_title');
            $table->foreign('selected_pre_proposal_id')->references('id')->on('capstone_pre_proposals')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->dropForeign(['selected_pre_proposal_id']);
            $table->dropColumn('selected_pre_proposal_id');
        });
    }
};
