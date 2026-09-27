<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A milestone's own details, the way a GitHub milestone has them.
     *
     * description  what the milestone is for, shown to teams under its name
     * due_on       the date the whole milestone is due; a deliverable with no
     *              date of its own falls due on it
     * closed_at    the CaPSA closed it: it leaves the open list and sends no
     *              more reminders, while every team's progress stays as it is
     *
     * All empty by default, so every existing checklist reads as it did.
     */
    public function up(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->date('due_on')->nullable()->after('description');
            $table->timestamp('closed_at')->nullable()->after('requires_previous');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_milestones', function (Blueprint $table) {
            $table->dropColumn(['description', 'due_on', 'closed_at']);
        });
    }
};
