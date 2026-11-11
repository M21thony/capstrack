<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three tidy-ups on capstone_experts:
     *
     * 1. Drop is_capsa. "CaPSA/TSA" is Capstone Subject Adviser, which is the
     *    capstone_adviser row in `roles` - the same fact stored twice. It is
     *    now derived from expert_roles (CapstoneExpert::getIsCapsaAttribute),
     *    so nothing reads this column. Keeping both is what let "Remove
     *    CaPSA/TSA" leave the role behind.
     *
     * 2. max_teams_raw -> program_slots. It started out holding the roster
     *    upload's raw "Max Number of Teams" text, but now holds the expert's
     *    own per-program capacity ("1 - BSIT, 5 - BSIS"). The old name reads
     *    like leftover import junk.
     *
     * 3. department -> program. It has always held a program (BSIT, BSIS,
     *    ...), never a department - CCS is the department; BSIT/BSIS/BSCS/
     *    BLIS are programs. Every user-facing label already says "Program".
     */
    public function up(): void
    {
        // Separate Blueprint calls on purpose: mixing a drop with renames in
        // one closure is driver-dependent and can be reordered badly.
        if (Schema::hasColumn('capstone_experts', 'is_capsa')) {
            Schema::table('capstone_experts', function (Blueprint $table) {
                $table->dropColumn('is_capsa');
            });
        }

        if (Schema::hasColumn('capstone_experts', 'max_teams_raw')) {
            Schema::table('capstone_experts', function (Blueprint $table) {
                $table->renameColumn('max_teams_raw', 'program_slots');
            });
        }

        if (Schema::hasColumn('capstone_experts', 'department')) {
            Schema::table('capstone_experts', function (Blueprint $table) {
                $table->renameColumn('department', 'program');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('capstone_experts', 'program')) {
            Schema::table('capstone_experts', function (Blueprint $table) {
                $table->renameColumn('program', 'department');
            });
        }

        if (Schema::hasColumn('capstone_experts', 'program_slots')) {
            Schema::table('capstone_experts', function (Blueprint $table) {
                $table->renameColumn('program_slots', 'max_teams_raw');
            });
        }

        if (! Schema::hasColumn('capstone_experts', 'is_capsa')) {
            Schema::table('capstone_experts', function (Blueprint $table) {
                $table->boolean('is_capsa')->default(false)->after('is_admin');
            });
        }
    }
};
