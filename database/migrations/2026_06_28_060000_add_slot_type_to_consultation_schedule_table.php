<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_schedule', function (Blueprint $table) {
            $table->string('slot_type')->nullable()->after('consultation_limit');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_schedule', function (Blueprint $table) {
            $table->dropColumn('slot_type');
        });
    }
};
