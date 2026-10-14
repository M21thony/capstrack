<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team Rating: students rate each teammate on PeerRatingRubric, per activity.
 *
 * A form is one named rating ("Midterm Team Participation Rating") with one
 * rating window; its items are the activities it covers, each from the
 * checklist or typed in. A rater has submitted once their rows for the form
 * carry submitted_at - there is no separate submissions table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_rating_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('capsa_id')->index();
            $table->string('name');
            $table->json('sections');
            $table->boolean('follows_checklist')->default(false);
            $table->unsignedSmallInteger('days_to_rate')->default(7);
            $table->date('opens_on')->nullable();
            $table->date('due_on')->nullable();
            $table->boolean('date_overridden')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('opened_notified_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('team_rating_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->index();
            $table->string('label');
            $table->unsignedBigInteger('checklist_milestone_id')->nullable()->index();
            $table->unsignedBigInteger('deliverable_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('team_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->index();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('rater_id');
            $table->unsignedBigInteger('ratee_id');
            $table->json('scores');
            $table->unsignedTinyInteger('total')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'rater_id', 'ratee_id']);
            $table->index(['form_id', 'rater_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_ratings');
        Schema::dropIfExists('team_rating_items');
        Schema::dropIfExists('team_rating_forms');
    }
};
