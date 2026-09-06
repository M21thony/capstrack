<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A team that was turned down could never apply to that expert again.
 *
 * The index was created as unique(team_id, expert_id, role_id) and meant "one
 * role per expert per team". role_id was dropped in
 * 2026_07_08_113731_remove_role_id_column, which left the index behind on the
 * two remaining columns - so it quietly became unique(team_id, expert_id):
 * one row per team per expert, ever, whatever its status.
 *
 * StudentExpertController::store() already enforces the rule that was
 * actually wanted - no second PENDING or APPROVED application with the same
 * expert - and a rejected one has to be allowed through, because being turned
 * down and asking again is the normal course of this page. The database was
 * overruling the controller, and the only way past it was to delete the
 * rejected row by hand.
 */
return new class extends Migration
{
    private const INDEX = 'team_expert_assignments_team_id_expert_id_role_id_unique';

    public function up(): void
    {
        if (! $this->indexExists()) {
            return;
        }

        DB::statement('ALTER TABLE `capstone_expert_application` DROP INDEX `'.self::INDEX.'`');
    }

    public function down(): void
    {
        // Deliberately not restored. Putting it back re-breaks re-applying,
        // and the shape it was meant to have needs a column that no longer
        // exists.
    }

    private function indexExists(): bool
    {
        if (! Schema::hasTable('capstone_expert_application')) {
            return false;
        }

        // sqlite (the test database) names its indexes the same way, so the
        // same lookup answers for both.
        return collect(Schema::getIndexes('capstone_expert_application'))
            ->contains(fn ($index) => ($index['name'] ?? '') === self::INDEX);
    }
};
