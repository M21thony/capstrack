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
        return $team->preProposals->map(fn ($p) => [
                'id' => $p->id,
                'slot' => $p->slot,
                'title' => $p->title,
                'status' => $p->status,
                'rejection_reason' => $p->rejection_reason,
                'sdg_goals' => $p->sdg_goals,
                'research_agenda' => $p->research_agenda,
                'problem_statement' => $p->problem_statement,
                'proposed_solution' => $p->proposed_solution,
                'scope' => $p->scope,
                'limitations' => $p->limitations,
                'sdm' => $p->sdm,
                // The whole duplication report, not the top three with their
                // titles. The CAPSA is deciding whether a title is too close
                // to something already done - she needs the matched
                // abstracts and what the check said about them, which is
                // exactly what the team saw before submitting.
                'duplication' => $p->duplicationCheck ? [
                    'verdict' => $p->duplicationCheck->verdict,
                    'top_score' => $p->duplicationCheck->top_score,
                    'matches' => collect($p->duplicationCheck->matches ?? [])->map(fn ($m) => [
                        'title' => $m['title'] ?? '',
                        'score' => $m['score'] ?? null,
                        'reason' => $m['reason'] ?? null,
                        'program' => $m['program'] ?? null,
                        'academic_year' => $m['academic_year'] ?? null,
                        'abstract' => $m['abstract'] ?? null,
                        'similarities' => $m['similarities'] ?? null,
                        'differences' => $m['differences'] ?? null,
                        'suggestion' => $m['suggestion'] ?? null,
                    ])->values(),
                    'highlight_words' => app(\App\Services\SimilarityService::class)
                        ->extractHighlightWords($p->duplicationCheck->concepts ?? []),
                ] : null,
                'document_url' => $p->duplicationCheck ? route('admin.verify_teams.pre_proposals.document', $p->id) : null,
                'approve_url' => route('admin.verify_teams.pre_proposals.approve', $p->id),
                'reject_url' => route('admin.verify_teams.pre_proposals.reject', $p->id),
            ])->values()->all();
    }
}
