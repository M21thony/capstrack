<?php

namespace App\Support;

use App\Models\Team;

/**
 * A team's pre-proposals in the shape the review modal reads.
 *
 * It lived inside the page controller, which meant approving a title could
 * only refresh the modal by reloading the whole page - and reloading closed
 * the modal, so a CaPSA working through three titles had to reopen it after
 * every single decision. The approve and reject endpoints hand back the same
 * shape now, so the modal can redraw itself where it stands.
 */
class PreProposalPayload
{
    public static function forTeam(Team $team): array
    {
        // The adviser's name, only looked up when a title is waiting on or
        // was decided by the adviser (a new title after a redefense).
        $adviserName = null;
        $adviser = fn () => $adviserName ??= \App\Models\CapstoneExpert::find(
            app(\App\Services\RecipientResolver::class)->resolveTeamRoleExpert((int) $team->team_id, 'adviser')
        )?->name;

        // Only titles the team has turned in for checking. A draft they are
        // still writing is not the CaPSA's to read - the counts on the
        // Pre-Proposals tab have always filtered on this, but the review
        // modal was built from every row, so opening it showed drafts that
        // had never been submitted. A title the adviser sent back is set
        // aside, but it stays here so the CaPSA sees why the team is back.
        return $team->allPreProposals
            ->whereNotNull('submitted_at')
            ->filter(fn ($p) => ! $p->retired_at || $p->adviser_status === TitleRevision::DECLINED)
            ->map(fn ($p) => [
                'id' => $p->id,
                'slot' => $p->slot,
                'title' => $p->title,
                'status' => $p->status,
                'rejection_reason' => $p->rejection_reason,
                'adviser' => $p->adviser_status ? [
                    'status' => $p->adviser_status,
                    'name' => $adviser(),
                    'note' => $p->adviser_note,
                    'decided_at' => $p->adviser_decided_at?->format('M j, Y'),
                ] : null,
                'set_aside' => (bool) $p->retired_at,
                'sdg_goals' => $p->sdg_goals,
                'research_agenda' => $p->research_agenda,
                'problem_statement' => $p->problem_statement,
                'proposed_solution' => $p->proposed_solution,
                'scope' => $p->scope,
                'limitations' => $p->limitations,
                'sdm' => $p->sdm,
                'tags' => $p->tags ?? [],
                // The whole duplication report, not the top three with their
                // titles. The CAPSA is deciding whether a title is too close
                // to something already done - she needs the matched
                // abstracts and what the check said about them, which is
                // exactly what the team saw before submitting.
                'duplication' => $p->duplicationCheck ? [
                    'verdict' => $p->duplicationCheck->verdict,
                    'top_score' => $p->duplicationCheck->top_score,
                    'matches' => collect($p->duplicationCheck->matchesWithAuthors())->map(fn ($m) => [
                        'title' => $m['title'] ?? '',
                        'score' => $m['score'] ?? null,
                        'reason' => $m['reason'] ?? null,
                        'program' => $m['program'] ?? null,
                        'academic_year' => $m['academic_year'] ?? null,
                        'authors' => $m['authors'] ?? null,
                        'abstract' => $m['abstract'] ?? null,
                        'similarities' => $m['similarities'] ?? null,
                        'differences' => $m['differences'] ?? null,
                        'suggestion' => $m['suggestion'] ?? null,
                    ])->values(),
                    'highlight_words' => app(\App\Services\SimilarityService::class)
                        ->extractHighlightWords($p->duplicationCheck->concepts ?? []),
                ] : null,
                'document_url' => $p->duplicationCheck ? route('capsa.verify_teams.pre_proposals.document', $p->id) : null,
                'update_url' => route('capsa.verify_teams.pre_proposals.update', $p->id),
            ])->values()->all();
    }
}
