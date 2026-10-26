<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which deliverables a team said it would bring to a consultation, kept
     * as the titles they picked or typed - a checklist line renamed later
     * should not rewrite what was booked.
     */
    public function up(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->json('deliverables')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->dropColumn('deliverables');
        });
    }
};
