<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Some recruitment posts hold their roles JSON-encoded twice: a string that
 * is itself a JSON list. Read back it was a string, not a list, and the Team
 * page's implode() on it failed for everyone who could see the post. Each
 * such row is written once over, as the plain list.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('recruitment')->whereNotNull('roles')->orderBy('recruitment_id')
            ->select(['recruitment_id', 'roles'])
            ->each(function (object $row): void {
                $once = json_decode((string) $row->roles, true);

                if (! is_string($once)) {
                    return;
                }

                $twice = json_decode($once, true);

                if (is_array($twice)) {
                    DB::table('recruitment')->where('recruitment_id', $row->recruitment_id)
                        ->update(['roles' => json_encode(array_values($twice))]);
                }
            });
    }

    /** The doubled form was never meant; there is nothing to put back. */
    public function down(): void {}
};
