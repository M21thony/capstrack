<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A team is now verified by its title settling, and the Verify Team
     * button is gone. A team whose title settled before this, but whose
     * CaPSA/TSA had not pressed the button yet, would have nothing left to
     * verify it - so those are verified here, once.
     */
    public function up(): void
    {
        DB::table('team')
            ->whereNotNull('selected_pre_proposal_id')
            ->where('is_confirmed', false)
            ->update(['is_confirmed' => true]);
    }

    /**
     * Which teams were verified here is not recorded, so there is nothing
     * to take back.
     */
    public function down(): void {}
};
