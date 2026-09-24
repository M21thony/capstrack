<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An upload belongs to the team it was sent for, not to whoever sent it.
 *
 * Until now a team's uploads were found through its members, so a student
 * who moved to a new team brought their old team's papers along: a team made
 * that day already showed its Getting started forms as handed in and waiting
 * on the CaPSA's check.
 *
 * Existing uploads are given the uploader's current team only when that team
 * already existed when the file went up. One sent before it existed was for
 * an earlier team and stays with none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('student_id')->index();
        });

        $teams = DB::table('student_s_y as sy')
            ->join('team as t', 't.team_id', '=', 'sy.team_id')
            ->orderBy('sy.school_year')
            ->orderBy('sy.s_y_id')
            ->get(['sy.student_id', 'sy.team_id', 't.created_at'])
            // The latest row wins, as it does for "the student's team" everywhere else.
            ->keyBy('student_id');

        DB::table('capstone_documents')
            ->whereNull('team_id')
            ->chunkById(500, function ($documents) use ($teams) {
                foreach ($documents as $document) {
                    $team = $teams->get($document->student_id);

                    if ($team && (! $team->created_at || $document->created_at >= $team->created_at)) {
                        DB::table('capstone_documents')->where('file_id', $document->file_id)->update(['team_id' => $team->team_id]);
                    }
                }
            }, 'file_id');
    }

    public function down(): void
    {
        Schema::table('capstone_documents', function (Blueprint $table) {
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};
