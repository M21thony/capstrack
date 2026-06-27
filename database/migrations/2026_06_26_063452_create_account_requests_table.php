<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('account_requests');

        Schema::create('account_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email', 250)->unique();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('credentials', 50)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('reject_attempts')->default(0);
            $table->dateTime('reject_cooldown_until')->nullable();
            $table->unsignedSmallInteger('max_teams')->default(4);
            $table->timestamps();
        });

        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn([
                'rejection_reason',
                'reject_attempts',
                'reject_cooldown_until',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_requests');

        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approved_status');
            $table->unsignedInteger('reject_attempts')->default(0)->after('rejection_reason');
            $table->dateTime('reject_cooldown_until')->nullable()->after('reject_attempts');
        });
    }
};
