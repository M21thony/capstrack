<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Receipts no longer wait on the team leader: an upload goes straight to the
 * CaPSA. Any receipt still held by a leader when this ships is released to
 * the CaPSA, waiting to be checked, so nobody's receipt is stranded in a
 * status nothing reads any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('financial_records')
            ->where('f_record_status', 'held')
            ->update(['f_record_status' => 'pending']);
    }

    /** There is no way back to holding: the leader's send step is gone. */
    public function down(): void {}
};
