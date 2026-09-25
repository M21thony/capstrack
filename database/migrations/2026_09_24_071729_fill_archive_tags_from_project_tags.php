<?php

use App\Support\ArchiveTagGuesser;
use App\Support\ProjectTags;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give the archive its tags.
 *
 * archived_doc_tags was empty, so an archived capstone could not be tagged
 * and Analytics' archive chart had nothing to count. It now holds the same
 * list a team picks from on its pre-proposal. Every capstone a team handed
 * in takes the tags of the pre-proposal it chose, and every other one - the
 * library's list - is tagged from its title and abstract, so none is left
 * uncounted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $have = DB::table('archived_doc_tags')->pluck('tag_id', 'tag_name');

        foreach (ProjectTags::OPTIONS as $name) {
            if (! $have->has($name)) {
                $have[$name] = DB::table('archived_doc_tags')->insertGetId(['tag_name' => $name], 'tag_id');
            }
        }

        $tagged = DB::table('archived_tag_relation')->pluck('archive_id')->unique()->all();

        $rows = DB::table('archived_documents as a')
            ->join('team as t', 't.team_id', '=', 'a.team_id')
            ->join('capstone_pre_proposals as p', 'p.id', '=', 't.selected_pre_proposal_id')
            ->whereNotNull('a.team_id')
            ->whereNotIn('a.archived_id', $tagged)
            ->get(['a.archived_id', 'p.tags']);

        foreach ($rows as $row) {
            foreach (array_unique((array) json_decode((string) $row->tags, true)) as $name) {
                if (is_string($name) && $have->has($name)) {
                    DB::table('archived_tag_relation')->insert(['archive_id' => $row->archived_id, 'tag_id' => $have[$name]]);
                }
            }
        }

        $tagged = DB::table('archived_tag_relation')->pluck('archive_id')->unique()->all();

        DB::table('archived_documents')
            ->whereNotIn('archived_id', $tagged)
            ->orderBy('archived_id')
            ->get(['archived_id', 'title', 'abstract'])
            ->each(function ($doc) use ($have) {
                foreach (ArchiveTagGuesser::guess($doc->title, $doc->abstract) as $name) {
                    DB::table('archived_tag_relation')->insert(['archive_id' => $doc->archived_id, 'tag_id' => $have[$name]]);
                }
            });
    }

    public function down(): void
    {
        // The tags stay: removing them would untag capstones people tagged by hand.
    }
};
