<?php

namespace App\Support;

use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\MilestoneItem;

/**
 * The official checklists, as starting points.
 *
 * Each milestone is a phase of the department's TCP and its requirements are
 * that phase's deliverables. Capstone 1 is transcribed from the department's
 * own CAPSTONE PROJECT 1 CHECKLIST - nineteen deliverables in the order they
 * appear on the paper, so a CaPSA can work down the screen and the sheet
 * together. Applying a
 * template is a starting point, not a cage: every item can be renamed,
 * re-dated, reordered or removed afterwards, and a CaPSA can add their own.
 *
 * Each item carries a template_key so a revised checklist can be applied
 * again over a milestone that is already partly ticked, matching items up by
 * identity instead of by title. Renaming an item on the paper then does not
 * orphan the work already recorded against it.
 *
 * Capstone 2 carries on in the same checklist below Title Defense, through
 * Pre Oral and Final Oral Defense, the Public Presentation and the archive
 * entry, so a team's checklist runs without a break from start to end.
 */
class MilestoneTemplate
{
    public const CAPSTONE_1 = 'capstone_1';

    public const CAPSTONE_2 = 'capstone_2';

    /** A line only Capstone 2 has, to tell whether a checklist already carries it. */
    public const CAPSTONE_2_MARKER = 'pre_oral_defense';

    /**
     * The milestones a team cannot use until it has passed the defense above
     * them. Final Manuscript is an older checklist's name.
     */
    public const GATES = ['Title Defense', 'Pre Oral Defense', 'Final Oral Defense', 'Public Presentation', 'Final Manuscript', 'Archive Entry', 'Bluebook Clearance'];

    /** The milestones open from the start, older checklists' names too; the CaPSA opens every other one. Bluebook is the dean's, so it opens on the Final Oral Defense pass, not the CaPSA. */
    public const OPEN_FROM_THE_START = ['Pre-proposal Defense', 'Getting started', 'Pre-proposal', 'Bluebook Clearance'];

    public static function all(): array
    {
        return [
            self::CAPSTONE_1 => [
                'name' => 'Capstone 1',
                'items' => self::capstone1Items(),
            ],
            self::CAPSTONE_2 => [
                'name' => 'Capstone 2',
                'items' => self::capstone2Items(),
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
     * Start a CaPSA's checklist from the department's, grouping its lines
     * into milestones by phase in the order the paper gives them. Capstone 1
     * comes first and Capstone 2 carries on below it.
     */
    public static function createChecklist(int $capsaId, string $program, string $key = self::CAPSTONE_1): Checklist
    {
        $template = self::all()[$key];

        $checklist = Checklist::create([
            'capsa_id' => $capsaId,
            'program' => $program,
            'title' => $template['name'].' Checklist',
        ]);

        self::addMilestones($checklist, $template['items']);

        if ($key === self::CAPSTONE_1) {
            self::appendCapstone2($checklist);
        }

        return $checklist;
    }

    /**
     * Put Capstone 2's milestones below everything a checklist already has.
     * Safe to run again: a checklist that carries them already, even archived
     * or removed, is left alone.
     *
     * @return int the milestones added
     */
    public static function appendCapstone2(Checklist $checklist): int
    {
        $has = $checklist->deliverables()->withTrashedParents()->withTrashed()
            ->where('deliverables.template_key', self::CAPSTONE_2_MARKER)
            ->exists();

        if ($has) {
            return 0;
        }

        $after = (int) $checklist->milestones()->withTrashed()->max('position') + 1;

        return self::addMilestones($checklist, self::capstone2Items(), $after);
    }

    /**
     * Put the Archive Entry milestone below a checklist that has Capstone 2
     * from before it existed. Safe to run again, like appendCapstone2().
     *
     * @return int the milestones added
     */
    public static function appendArchiveEntry(Checklist $checklist): int
    {
        $has = fn (string $key) => $checklist->deliverables()->withTrashedParents()->withTrashed()
            ->where('deliverables.template_key', $key)
            ->exists();

        if (! $has(self::CAPSTONE_2_MARKER) || $has(Deliverable::ARCHIVE_ENTRY)) {
            return 0;
        }

        $after = (int) $checklist->milestones()->withTrashed()->max('position') + 1;
        $lines = array_filter(self::capstone2Items(), fn ($line) => $line['template_key'] === Deliverable::ARCHIVE_ENTRY);

        return self::addMilestones($checklist, $lines, $after);
    }

    /** The Bluebook Clearance milestone for a Capstone 2 checklist from before it. Safe to run again. */
    public static function appendBluebook(Checklist $checklist): int
    {
        $has = fn (string $key) => $checklist->deliverables()->withTrashedParents()->withTrashed()
            ->where('deliverables.template_key', $key)
            ->exists();

        if (! $has(self::CAPSTONE_2_MARKER) || $has(Deliverable::BLUEBOOK_CLEARANCE)) {
            return 0;
        }

        $after = (int) $checklist->milestones()->withTrashed()->max('position') + 1;

        return self::addMilestones($checklist, self::bluebookItems(), $after);
    }

    /** @return int the milestones added */
    private static function addMilestones(Checklist $checklist, array $items, int $from = 0): int
    {
        $groups = collect($items)->groupBy('phase')->values();

        foreach ($groups as $at => $lines) {
            $milestone = $checklist->milestones()->create([
                'name' => $lines->first()['phase'],
                'position' => $from + $at,
                'requires_previous' => in_array($lines->first()['phase'], self::GATES, true),
                'opened_at' => in_array($lines->first()['phase'], self::OPEN_FROM_THE_START, true) ? now() : null,
            ]);

            foreach ($lines->values() as $at => $line) {
                $milestone->deliverables()->create([
                    'template_key' => $line['template_key'],
                    'title' => $line['title'],
                    'kind' => $line['kind'],
                    'role' => $line['role'] ?? null,
                    'chapter' => $line['chapter'] ?? null,
                    'defense_type' => $line['defense_type'] ?? null,
                    'position' => $at,
                ]);
            }
        }

        return $groups->count();
    }

    /**
     * CAPSTONE PROJECT 1 CHECKLIST, in order, under the TCP's two Capstone 1
     * phases: the Pre-proposal Defense, then the Title Defense with the
     * chapter drafts that lead up to it.
     *
     * Beyond its title each line carries a short label for the grid, where
     * the columns only fit if the header stops repeating what the milestone
     * band above it already says.
     *
     * The paper pairs each chapter draft with the CLF approving it as two
     * separate lines, which is why a draft closes on being handed in and the
     * approval is its own item.
     */
    private static function capstone1Items(): array
    {
        $F = MilestoneItem::FORM;
        $D = MilestoneItem::DOCUMENT;
        $A = MilestoneItem::APPROVAL;
        $X = MilestoneItem::DEFENSE;

        return [
            ['template_key' => 'team_assignment_form', 'title' => 'Signed Project Team Assignment Form', 'short' => 'Team Assignment', 'phase' => 'Pre-proposal Defense', 'kind' => $F],
            ['template_key' => 'pre_proposal_title_form', 'title' => 'Recommended Pre-Proposal Title Form', 'short' => 'Pre-Proposal Title', 'phase' => 'Pre-proposal Defense', 'kind' => $F],
            ['template_key' => 'role_acceptance_adviser', 'title' => 'Role Acceptance Form (Adviser/CaPA)', 'short' => 'Adviser', 'phase' => 'Pre-proposal Defense', 'kind' => $F],
            ['template_key' => 'role_acceptance_consultant', 'title' => 'Role Acceptance Form (Consultant)', 'short' => 'Consultant', 'phase' => 'Pre-proposal Defense', 'kind' => $F],
            ['template_key' => 'role_acceptance_grammarian', 'title' => 'Role Acceptance Form (Grammarian)', 'short' => 'Grammarian', 'phase' => 'Pre-proposal Defense', 'kind' => $F],
            ['template_key' => 'pre_proposal_defense', 'title' => 'Pre-Proposal Oral Presentation', 'short' => 'Oral Defense', 'phase' => 'Pre-proposal Defense', 'kind' => $X, 'defense_type' => DefenseOutcome::PRE_PROPOSAL],
            ['template_key' => 'final_title_form', 'title' => 'Signed Final Project Title Form', 'short' => 'Final Title', 'phase' => 'Pre-proposal Defense', 'kind' => $F],

            ['template_key' => 'chapter_1_draft', 'title' => 'Chapter 1 Draft', 'short' => 'Ch 1 Draft', 'phase' => 'Title Defense', 'kind' => $D, 'chapter' => '1'],
            ['template_key' => 'clf_chapter_1', 'title' => 'Signed CLF Approving Chapter 1 (CaPA/Adviser)', 'short' => 'Ch 1 CLF', 'phase' => 'Title Defense', 'kind' => $A, 'role' => 'adviser', 'chapter' => '1'],
            ['template_key' => 'chapter_2_draft', 'title' => 'Chapter 2 Draft', 'short' => 'Ch 2 Draft', 'phase' => 'Title Defense', 'kind' => $D, 'chapter' => '2'],
            ['template_key' => 'clf_chapter_2', 'title' => 'Signed CLF Approving Chapter 2 (CaPA/Adviser)', 'short' => 'Ch 2 CLF', 'phase' => 'Title Defense', 'kind' => $A, 'role' => 'adviser', 'chapter' => '2'],
            ['template_key' => 'chapter_3_draft', 'title' => 'Chapter 3 Draft', 'short' => 'Ch 3 Draft', 'phase' => 'Title Defense', 'kind' => $D, 'chapter' => '3'],
            ['template_key' => 'clf_chapter_3', 'title' => 'Signed CLF Approving Chapter 3 (CaPA/Adviser)', 'short' => 'Ch 3 CLF', 'phase' => 'Title Defense', 'kind' => $A, 'role' => 'adviser', 'chapter' => '3'],
            ['template_key' => 'clf_recommend_td_adviser', 'title' => 'Signed CLF Recommending for Title Defense (CaPA/Adviser)', 'short' => 'Adviser CLF', 'phase' => 'Title Defense', 'kind' => $A, 'role' => 'adviser'],
            ['template_key' => 'clf_recommend_td_consultant', 'title' => 'Signed CLF Recommending for Title Defense (Consultant)', 'short' => 'Consultant CLF', 'phase' => 'Title Defense', 'kind' => $A, 'role' => 'consultant'],
            ['template_key' => 'td_hearing_notice', 'title' => 'Signed Title Defense Hearing Notice', 'short' => 'Hearing Notice', 'phase' => 'Title Defense', 'kind' => $F],
            ['template_key' => 'title_defense', 'title' => 'Title Defense', 'short' => 'Defense', 'phase' => 'Title Defense', 'kind' => $X, 'defense_type' => 'Title Defense'],
            ['template_key' => 'revised_manuscript', 'title' => 'Revised Proposal Manuscript', 'short' => 'Manuscript', 'phase' => 'Title Defense', 'kind' => $D, 'chapter' => 'revised'],
            ['template_key' => 'td_rsc', 'title' => 'Signed (Noted) TD RSC', 'short' => 'TD RSC', 'phase' => 'Title Defense', 'kind' => $F],
        ];
    }

    /**
     * Capstone 2, in order, under the TCP's phases: the prototypes and the
     * last two chapters up to the Pre Oral Defense, the Final Oral Defense
     * and everything handed in after it, the Public Presentation, and the
     * archive entry. Each waits for the defense above it.
     */
    private static function capstone2Items(): array
    {
        $F = MilestoneItem::FORM;
        $D = MilestoneItem::DOCUMENT;
        $A = MilestoneItem::APPROVAL;
        $X = MilestoneItem::DEFENSE;
        $C = Deliverable::CUSTOM;

        return [
            ['template_key' => 'prototype_60', 'title' => '1st Prototype (60%)', 'short' => 'Prototype 60%', 'phase' => 'Pre Oral Defense', 'kind' => $C],
            ['template_key' => 'prototype_80', 'title' => '2nd Prototype (80%)', 'short' => 'Prototype 80%', 'phase' => 'Pre Oral Defense', 'kind' => $C],
            ['template_key' => 'chapter_4_draft', 'title' => 'Chapter 4 Draft', 'short' => 'Ch 4 Draft', 'phase' => 'Pre Oral Defense', 'kind' => $D, 'chapter' => '4'],
            ['template_key' => 'clf_chapter_4', 'title' => 'Signed CLF Approving Chapter 4 (CaPA/Adviser)', 'short' => 'Ch 4 CLF', 'phase' => 'Pre Oral Defense', 'kind' => $A, 'role' => 'adviser', 'chapter' => '4'],
            ['template_key' => 'chapter_5_draft', 'title' => 'Chapter 5 Draft', 'short' => 'Ch 5 Draft', 'phase' => 'Pre Oral Defense', 'kind' => $D, 'chapter' => '5'],
            ['template_key' => 'clf_chapter_5', 'title' => 'Signed CLF Approving Chapter 5 (CaPA/Adviser)', 'short' => 'Ch 5 CLF', 'phase' => 'Pre Oral Defense', 'kind' => $A, 'role' => 'adviser', 'chapter' => '5'],
            ['template_key' => 'pod_manuscript', 'title' => 'Pre Oral Defense Manuscript (Chapters 1-5)', 'short' => 'Manuscript', 'phase' => 'Pre Oral Defense', 'kind' => $D],
            ['template_key' => 'clf_recommend_pod_adviser', 'title' => 'Signed CLF Recommending for Pre Oral Defense (CaPA/Adviser)', 'short' => 'Adviser CLF', 'phase' => 'Pre Oral Defense', 'kind' => $A, 'role' => 'adviser'],
            ['template_key' => 'clf_recommend_pod_consultant', 'title' => 'Signed CLF Recommending for Pre Oral Defense (Consultant)', 'short' => 'Consultant CLF', 'phase' => 'Pre Oral Defense', 'kind' => $A, 'role' => 'consultant'],
            ['template_key' => 'pod_hearing_notice', 'title' => 'Signed Pre Oral Defense Hearing Notice', 'short' => 'Hearing Notice', 'phase' => 'Pre Oral Defense', 'kind' => $F],
            ['template_key' => self::CAPSTONE_2_MARKER, 'title' => 'Pre Oral Defense', 'short' => 'Defense', 'phase' => 'Pre Oral Defense', 'kind' => $X, 'defense_type' => 'Pre Oral Defense'],
            ['template_key' => 'pod_rsc', 'title' => 'Signed (Noted) POD RSC', 'short' => 'POD RSC', 'phase' => 'Pre Oral Defense', 'kind' => $F],

            ['template_key' => 'pod_revised_manuscript', 'title' => 'Revised Manuscript (per POD RSC)', 'short' => 'Revised', 'phase' => 'Final Oral Defense', 'kind' => $D],
            ['template_key' => 'fod_manuscript', 'title' => 'Final Oral Defense Manuscript', 'short' => 'Manuscript', 'phase' => 'Final Oral Defense', 'kind' => $D],
            ['template_key' => 'capstone_fee_receipt', 'title' => 'Thesis/Capstone Fee Receipt', 'short' => 'Fee Receipt', 'phase' => 'Final Oral Defense', 'kind' => $F],
            ['template_key' => 'clf_recommend_fod_adviser', 'title' => 'Signed CLF Recommending for Final Oral Defense (CaPA/Adviser)', 'short' => 'Adviser CLF', 'phase' => 'Final Oral Defense', 'kind' => $A, 'role' => 'adviser'],
            ['template_key' => 'clf_recommend_fod_consultant', 'title' => 'Signed CLF Recommending for Final Oral Defense (Consultant)', 'short' => 'Consultant CLF', 'phase' => 'Final Oral Defense', 'kind' => $A, 'role' => 'consultant'],
            ['template_key' => 'fod_hearing_notice', 'title' => 'Signed Final Oral Defense Hearing Notice', 'short' => 'Hearing Notice', 'phase' => 'Final Oral Defense', 'kind' => $F],
            ['template_key' => 'final_oral_defense', 'title' => 'Final Oral Defense', 'short' => 'Defense', 'phase' => 'Final Oral Defense', 'kind' => $X, 'defense_type' => 'Final Oral Defense'],
            ['template_key' => 'fod_rsc', 'title' => 'Signed (Noted) FOD RSC', 'short' => 'FOD RSC', 'phase' => 'Final Oral Defense', 'kind' => $F],
            ['template_key' => 'utilization_certificate', 'title' => 'Signed Certificate of Utilization or Transfer', 'short' => 'Utilization', 'phase' => 'Final Oral Defense', 'kind' => $F],
            ['template_key' => 'joint_affidavit', 'title' => 'Joint Affidavit of Undertaking', 'short' => 'Affidavit', 'phase' => 'Final Oral Defense', 'kind' => $F],
            ['template_key' => 'approval_sheet', 'title' => 'Signed Approval Sheet', 'short' => 'Approval Sheet', 'phase' => 'Final Oral Defense', 'kind' => $F],
            ['template_key' => 'editing_certificate', 'title' => "Grammarian's Certificate", 'short' => 'Grammarian', 'phase' => 'Final Oral Defense', 'kind' => $F],
            ['template_key' => 'final_manuscript', 'title' => 'Final Manuscript', 'short' => 'Final', 'phase' => 'Final Oral Defense', 'kind' => $D, 'chapter' => 'final'],
            ['template_key' => 'acm_manuscript', 'title' => 'ACM-Formatted Manuscript', 'short' => 'ACM', 'phase' => 'Final Oral Defense', 'kind' => $D],
            ['template_key' => 'hardbound_manuscript', 'title' => 'Manuscript in Hardbound', 'short' => 'Hardbound', 'phase' => 'Final Oral Defense', 'kind' => $C],

            ['template_key' => 'participation_certificate', 'title' => 'Certificate of Participation', 'short' => 'Participation', 'phase' => 'Public Presentation', 'kind' => $F],

            // Its own milestone, so the CaPSA opens and closes it apart from the rest.
            ['template_key' => Deliverable::ARCHIVE_ENTRY, 'title' => 'Archive Entry (Title, Abstract, Proponents)', 'short' => 'Archive Entry', 'phase' => 'Archive Entry', 'kind' => $C],

            ...self::bluebookItems(),
        ];
    }

    /**
     * What the dean's office checks before the bluebook. Courses differ, so a
     * CaPSA edits these lines in the milestone editor; the clearance line
     * stays last and closes when the dean clears the team.
     */
    private static function bluebookItems(): array
    {
        $B = Deliverable::BLUEBOOK;

        return [
            ['template_key' => 'bluebook_manuscript', 'title' => 'Final Manuscript', 'short' => 'Manuscript', 'phase' => 'Bluebook Clearance', 'kind' => $B],
            ['template_key' => 'bluebook_approval_sheet', 'title' => 'Approval Sheet', 'short' => 'Approval Sheet', 'phase' => 'Bluebook Clearance', 'kind' => $B],
            ['template_key' => 'bluebook_similarity', 'title' => 'Similarity Report', 'short' => 'Similarity', 'phase' => 'Bluebook Clearance', 'kind' => $B],
            ['template_key' => 'bluebook_video', 'title' => 'System Demo Video', 'short' => 'Demo Video', 'phase' => 'Bluebook Clearance', 'kind' => $B],
            ['template_key' => Deliverable::BLUEBOOK_CLEARANCE, 'title' => 'Bluebook Clearance', 'short' => 'Clearance', 'phase' => 'Bluebook Clearance', 'kind' => $B],
        ];
    }
}
