<?php

namespace App\Support;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use Illuminate\Support\Collection;

/**
 * How many of a team's papers wait on a revision: the newest version of the
 * paper has a reviewer asking for changes. Shown beside Documents in the
 * menu on every page, so a sent-back paper is not found only by opening it.
 */
class PapersToRevise
{
    public static function count(int $teamId): int
    {
        return CapstoneDocument::ofTeam($teamId)
            ->exceptSignedPapers()
            ->with('reviews:review_id,file_id,verdict')
            ->get(['file_id', 'deliverable_id', 'chapter', 'file_name', 'file_version'])
            ->groupBy(fn (CapstoneDocument $document) => $document->paperKey())
            ->filter(fn (Collection $versions) => $versions->sortByDesc('file_version')->first()
                ->reviews->contains('verdict', CapstoneDocumentReview::NEEDS_REVISION))
            ->count();
    }
}
