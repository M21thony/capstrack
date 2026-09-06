<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The queue.
 *
 * QUEUE_CONNECTION is `database`, and every mail the app sends is queued -
 * approvals, rejections, consultation reminders. failed_jobs has a migration;
 * the table those jobs sit in while they wait never did. It exists on the
 * machine this was built on because `queue:table` was run there by hand, so
 * nothing looked wrong - but a fresh clone would have thrown on the first
 * mail it tried to send.
 *
 * Guarded, so it is a no-op wherever the table was already made by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jobs')) {
            return;
        }

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
