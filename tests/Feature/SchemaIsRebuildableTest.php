<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * What the migrations have to produce for the app to run.
 *
 * Both of these went missing the same way, and neither was visible: a column
 * added to a migration that had already run, and a table made by hand with
 * `queue:table` instead of by a migration. `migrate:status` reports nothing
 * pending in either case, so the only thing that finds them is asking whether
 * a database built from git alone has what the code reads.
 */
class SchemaIsRebuildableTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cooldown_column_exists(): void
    {
        // AdminApprovalController::reject() writes it, and AuthController and
        // ExpertController read it on sign-in.
        $this->assertTrue(Schema::hasColumn('capstone_experts', 'reject_cooldown_until'));
    }

    public function test_the_queue_has_a_table_to_sit_in(): void
    {
        // QUEUE_CONNECTION is database and every mail is queued, so without
        // this the first approval mail throws.
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));

        foreach (['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('jobs', $column), $column);
        }
    }

    public function test_both_migrations_are_safe_where_the_thing_already_exists(): void
    {
        // Which is the case that matters: the install they were written for
        // already has one of them.
        $cooldown = require database_path('migrations/2026_09_06_090000_add_reject_cooldown_until_to_capstone_experts.php');
        $jobs = require database_path('migrations/2026_09_06_090100_create_jobs_table.php');

        $cooldown->up();
        $jobs->up();

        $this->assertTrue(Schema::hasColumn('capstone_experts', 'reject_cooldown_until'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }
}
