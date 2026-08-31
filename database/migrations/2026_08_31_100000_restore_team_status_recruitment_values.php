<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give team_status back a single meaning: is this team still recruiting?
     *
     * It had accumulated three unrelated jobs. 'closed' meant "not taking
     * members" (set automatically when the 5th joins), 'complete' meant
     * "turned in for CAPSA review", and the financial module gated receipts
     * on that same 'complete'. One column answering three questions meant a
     * recruitment change could move a team through verification.
     *
     * Submission is now recorded where it actually happens - submitted_at on
     * the pre-proposals (PreProposalController::submit) - so team_status is
     * left with recruitment alone:
     *
     *   Active - open, still has room
     *   Full   - not taking members
     *
     * This also restores the vocabulary the table was created with in
     * 2026_06_09_135811 (enum Active/Full/Need Members/Done), which
     * 2026_07_01_131744 replaced wholesale. AnalyticsController was written
     * against the original words and still queries 'Active' - it has been
     * counting zero ever since, and starts working again here.
     *
     * 'Done' is deliberately NOT restored. Finishing a stage is a fact about
     * a team's run in a term, not about the team, and a team that retakes has
     * more than one such fact.
     */
    public function up(): void
    {
        if (! Schema::hasTable('team')) {
            return;
        }

        // Map before narrowing the column, so nothing is silently truncated.
        //
        // 'complete' becomes Active, not Full: it never described recruitment
        // at all. Those teams are mid-capstone, and the fact they have
        // submitted is already carried by their pre-proposals.
        DB::table('team')->where('team_status', 'on-going')->update(['team_status' => 'Active']);
        DB::table('team')->where('team_status', 'complete')->update(['team_status' => 'Active']);
        DB::table('team')->where('team_status', 'closed')->update(['team_status' => 'Full']);

        // Anything else is legacy noise from the earlier vocabulary swap.
        DB::table('team')->whereNotIn('team_status', ['Active', 'Full'])->update(['team_status' => 'Active']);

        $this->setColumn("enum('Active','Full')", 'Active');
    }

    public function down(): void
    {
        if (! Schema::hasTable('team')) {
            return;
        }

        $this->setColumn('varchar(20)', 'on-going');

        DB::table('team')->where('team_status', 'Active')->update(['team_status' => 'on-going']);
        DB::table('team')->where('team_status', 'Full')->update(['team_status' => 'closed']);
    }

    /**
     * SQLite has no enum - it stores the declared type as free-form text and
     * never enforces it - so there the column is left a string. The DEFAULT
     * still has to move, though: a row inserted without a status would
     * otherwise arrive as 'on-going', a value nothing understands any more.
     */
    private function setColumn(string $type, string $default): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE team MODIFY team_status {$type} NOT NULL DEFAULT '{$default}'");

            return;
        }

        Schema::table('team', function (Blueprint $table) use ($default) {
            $table->string('team_status', 20)->default($default)->nullable(false)->change();
        });
    }
};
