<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('consultation_schedule', function (Blueprint $table) {
            $table->date('specific_date')->nullable()->after('available_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_schedule', function (Blueprint $table) {
            //
        });
    }
};
