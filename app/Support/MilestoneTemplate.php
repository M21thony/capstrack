<?php

namespace App\Support;

use App\Models\Milestone;
use App\Models\MilestoneItem;

/**
 * The official checklists, as starting points.
 *
 * Capstone 1 is transcribed from the department's own CAPSTONE PROJECT 1
 * CHECKLIST - nineteen deliverables in the order they appear on the paper, so
 * a CaPSA can work down the screen and the sheet together. Applying a
 * template is a starting point, not a cage: every item can be renamed,
 * re-dated, reordered or removed afterwards, and a CaPSA can add their own.
 *
 * Each item carries a template_key so a revised checklist can be applied
 * again over a milestone that is already partly ticked, matching items up by
 * identity instead of by title. Renaming an item on the paper then does not
 * orphan the work already recorded against it.
 *
 * Capstone 2 has no entry yet: it exists as a document but its contents are
 * not settled, and guessing at them would be worse than an empty milestone a
 * CaPSA fills in.
 */
class MilestoneTemplate
{
    public const CAPSTONE_1 = 'capstone_1';

    public static function all(): array
    {
        return [
            self::CAPSTONE_1 => [
                'name' => 'Capstone 1',
                'items' => self::capstone1Items(),
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function nameFor(string $key): ?string
    {
        return self::all()[$key]['name'] ?? null;
    }

    /**
     * Write a template's items onto a milestone.
     *
     * Existing items keep their completed_at and their own due dates - this
     * is meant to be safe to re-run when the checklist changes. Items a CaPSA
     * added by hand (no template_key) are left alone entirely.
     */
    public static function applyTo(Milestone $milestone, string $key): int
    {
        $template = self::all()[$key] ?? null;

        if (! $template) {
            return 0;
        }

        $existing = $milestone->items()
            ->whereNotNull('template_key')
            ->get()
            ->keyBy('template_key');

        $written = 0;

        foreach ($template['items'] as $position => $item) {
            $current = $existing->get($item['template_key']);

            $attributes = [
                'title' => $item['title'],
                'short_label' => $item['short'] ?? null,
                'phase' => $item['phase'] ?? null,
                'kind' => $item['kind'],
                'position' => $position,
                'chapter' => $item['chapter'] ?? null,
                'role' => $item['role'] ?? null,
                'defense_type' => $item['defense_type'] ?? null,
            ];

            if ($current) {
                $current->update($attributes);
            } else {
                $milestone->items()->create($attributes + ['template_key' => $item['template_key']]);
            }

            $written++;
        }

        return $written;
    }

    /**
     * CAPSTONE PROJECT 1 CHECKLIST, in order.
     *
     * Beyond its title each line carries the phase it belongs to and a short
     * label. Both exist for the grid, where nineteen columns only fit if the
     * header stops repeating what the phase band above it already says -
     * "Chapter 1 / Draft" rather than "Chapter 1 Draft" three times over.
     *
     * The paper pairs each chapter draft with the CLF approving it as two
     * separate lines, which is why a draft closes on being handed in and the
     * approval is its own item. The Revised Proposal Manuscript is the one
     * draft with no approval line after it; left as written rather than
     * inventing one.
     */
    private static function capstone1Items(): array
    {
        $F = MilestoneItem::FORM;
        $D = MilestoneItem::DOCUMENT;
        $A = MilestoneItem::APPROVAL;
        $X = MilestoneItem::DEFENSE;

        return [
            ['template_key' => 'team_assignment_form', 'title' => 'Signed Project Team Assignment Form', 'short' => 'Team Assignment', 'phase' => 'Getting started', 'kind' => $F],
            ['template_key' => 'pre_proposal_title_form', 'title' => 'Recommended Pre-Proposal Title Form', 'short' => 'Pre-Proposal Title', 'phase' => 'Getting started', 'kind' => $F],
            ['template_key' => 'role_acceptance_adviser', 'title' => 'Role Acceptance Form (Adviser/CaPA)', 'short' => 'Adviser', 'phase' => 'Getting started', 'kind' => $F],
            ['template_key' => 'role_acceptance_consultant', 'title' => 'Role Acceptance Form (Consultant)', 'short' => 'Consultant', 'phase' => 'Getting started', 'kind' => $F],
            ['template_key' => 'role_acceptance_grammarian', 'title' => 'Role Acceptance Form (Grammarian)', 'short' => 'Grammarian', 'phase' => 'Getting started', 'kind' => $F],

            ['template_key' => 'pre_proposal_defense', 'title' => 'Pre-Proposal Oral Presentation', 'short' => 'Oral Defense', 'phase' => 'Pre-proposal', 'kind' => $X, 'defense_type' => DefenseOutcome::PRE_PROPOSAL],
            ['template_key' => 'final_title_form', 'title' => 'Signed Final Project Title Form', 'short' => 'Final Title', 'phase' => 'Pre-proposal', 'kind' => $F],

            ['template_key' => 'chapter_1_draft', 'title' => 'Chapter 1 Draft', 'short' => 'Draft', 'phase' => 'Chapter 1', 'kind' => $D, 'chapter' => '1'],
            ['template_key' => 'clf_chapter_1', 'title' => 'Signed CLF Approving Chapter 1 (CaPA/Adviser)', 'short' => 'Approved CLF', 'phase' => 'Chapter 1', 'kind' => $A, 'role' => 'adviser', 'chapter' => '1'],

            ['template_key' => 'chapter_2_draft', 'title' => 'Chapter 2 Draft', 'short' => 'Draft', 'phase' => 'Chapter 2', 'kind' => $D, 'chapter' => '2'],
            ['template_key' => 'clf_chapter_2', 'title' => 'Signed CLF Approving Chapter 2 (CaPA/Adviser)', 'short' => 'Approved CLF', 'phase' => 'Chapter 2', 'kind' => $A, 'role' => 'adviser', 'chapter' => '2'],

            ['template_key' => 'chapter_3_draft', 'title' => 'Chapter 3 Draft', 'short' => 'Draft', 'phase' => 'Chapter 3', 'kind' => $D, 'chapter' => '3'],
            ['template_key' => 'clf_chapter_3', 'title' => 'Signed CLF Approving Chapter 3 (CaPA/Adviser)', 'short' => 'Approved CLF', 'phase' => 'Chapter 3', 'kind' => $A, 'role' => 'adviser', 'chapter' => '3'],

            ['template_key' => 'clf_recommend_td_adviser', 'title' => 'Signed CLF Recommending for Title Defense (CaPA/Adviser)', 'short' => 'Adviser CLF', 'phase' => 'Title Defense', 'kind' => $A, 'role' => 'adviser'],
            ['template_key' => 'clf_recommend_td_consultant', 'title' => 'Signed CLF Recommending for Title Defense (Consultant)', 'short' => 'Consultant CLF', 'phase' => 'Title Defense', 'kind' => $A, 'role' => 'consultant'],
            ['template_key' => 'td_hearing_notice', 'title' => 'Signed Title Defense Hearing Notice', 'short' => 'Hearing Notice', 'phase' => 'Title Defense', 'kind' => $F],
            ['template_key' => 'title_defense', 'title' => 'Title Defense', 'short' => 'Defense', 'phase' => 'Title Defense', 'kind' => $X, 'defense_type' => 'Title Defense'],
            ['template_key' => 'revised_manuscript', 'title' => 'Revised Proposal Manuscript', 'short' => 'Manuscript', 'phase' => 'Title Defense', 'kind' => $D, 'chapter' => 'revised'],
            ['template_key' => 'td_rsc', 'title' => 'Signed (Noted) TD RSC', 'short' => 'TD RSC', 'phase' => 'Title Defense', 'kind' => $F],
        ];
    }
}
