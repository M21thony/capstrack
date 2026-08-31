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
     * The paper pairs each chapter draft with the CLF approving it as two
     * separate lines, which is why a draft closes on being handed in and the
     * approval is its own item - handing something in and having it accepted
     * are different events, and the checklist already treats them that way.
     *
     * The Revised Proposal Manuscript is the one draft with no approval line
     * after it on the paper. Left as written rather than inventing one.
     */
    private static function capstone1Items(): array
    {
        return [
            ['template_key' => 'team_assignment_form', 'title' => 'Signed Project Team Assignment Form', 'kind' => MilestoneItem::FORM],
            ['template_key' => 'pre_proposal_title_form', 'title' => 'Recommended Pre-Proposal Title Form', 'kind' => MilestoneItem::FORM],
            ['template_key' => 'role_acceptance_adviser', 'title' => 'Role Acceptance Form (Adviser/CaPA)', 'kind' => MilestoneItem::FORM],
            ['template_key' => 'role_acceptance_consultant', 'title' => 'Role Acceptance Form (Consultant)', 'kind' => MilestoneItem::FORM],
            ['template_key' => 'role_acceptance_grammarian', 'title' => 'Role Acceptance Form (Grammarian)', 'kind' => MilestoneItem::FORM],

            ['template_key' => 'pre_proposal_defense', 'title' => 'Pre-Proposal Oral Presentation', 'kind' => MilestoneItem::DEFENSE, 'defense_type' => DefenseOutcome::PRE_PROPOSAL],

            ['template_key' => 'final_title_form', 'title' => 'Signed Final Project Title Form', 'kind' => MilestoneItem::FORM],

            ['template_key' => 'chapter_1_draft', 'title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT, 'chapter' => '1'],
            ['template_key' => 'clf_chapter_1', 'title' => 'Signed CLF Approving Chapter 1 (CaPA/Adviser)', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser', 'chapter' => '1'],

            ['template_key' => 'chapter_2_draft', 'title' => 'Chapter 2 Draft', 'kind' => MilestoneItem::DOCUMENT, 'chapter' => '2'],
            ['template_key' => 'clf_chapter_2', 'title' => 'Signed CLF Approving Chapter 2 (CaPA/Adviser)', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser', 'chapter' => '2'],

            ['template_key' => 'chapter_3_draft', 'title' => 'Chapter 3 Draft', 'kind' => MilestoneItem::DOCUMENT, 'chapter' => '3'],
            ['template_key' => 'clf_chapter_3', 'title' => 'Signed CLF Approving Chapter 3 (CaPA/Adviser)', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser', 'chapter' => '3'],

            ['template_key' => 'clf_recommend_td_adviser', 'title' => 'Signed CLF Recommending for Title Defense (CaPA/Adviser)', 'kind' => MilestoneItem::APPROVAL, 'role' => 'adviser'],
            ['template_key' => 'clf_recommend_td_consultant', 'title' => 'Signed CLF Recommending for Title Defense (Consultant)', 'kind' => MilestoneItem::APPROVAL, 'role' => 'consultant'],

            ['template_key' => 'td_hearing_notice', 'title' => 'Signed Title Defense Hearing Notice', 'kind' => MilestoneItem::FORM],
            ['template_key' => 'title_defense', 'title' => 'Title Defense', 'kind' => MilestoneItem::DEFENSE, 'defense_type' => 'Title Defense'],
            ['template_key' => 'revised_manuscript', 'title' => 'Revised Proposal Manuscript', 'kind' => MilestoneItem::DOCUMENT, 'chapter' => 'revised'],
            ['template_key' => 'td_rsc', 'title' => 'Signed (Noted) TD RSC', 'kind' => MilestoneItem::FORM],
        ];
    }
}
