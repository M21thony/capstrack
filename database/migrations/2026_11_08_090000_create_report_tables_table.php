<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A CaPSA/TSA's saved report-builder tables: the columns picked and the filters set. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_tables', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('expert_id')->index();
            $t->string('name', 70);
            $t->string('mode', 10)->default('students');
            $t->json('columns');
            $t->json('filters')->nullable();
            $t->timestamps();
            $t->unique(['expert_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_tables');
    }
};
