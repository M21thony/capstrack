<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A team whose title has passed asking to change part of its wording. The
 * team leader asks, with a reason; the adviser approves or declines, and
 * only an approved change becomes the team's title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('title_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('requested_by');
            $table->text('current_title');
            $table->string('new_title');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('adviser_id')->nullable();
            $table->text('adviser_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_change_requests');
    }
};
