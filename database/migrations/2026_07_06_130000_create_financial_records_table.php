<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_records')) {
            return;
        }

        Schema::create('financial_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('receipt_image');
            $table->string('f_record_status')->nullable();
            $table->string('payment_for', 191)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_records');
    }
};
