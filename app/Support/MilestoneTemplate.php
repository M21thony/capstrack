<?php

namespace App\Support;

use App\Models\Checklist;
use App\Models\Deliverable;
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
 * Capstone 2 carries on in the same checklist below Title Defense, through
 * Pre Oral and Final Oral Defense to the final manuscript, so a team's
 * checklist runs without a break from Getting started to the end. Its lines
 * follow the shape of Capstone 1 (drafts and their CLFs, recommending CLFs,
 * a hearing notice, the defense, the RSC); the department has no paper for
 * it yet, so a CaPSA renames or removes whatever does not match theirs.
 */
class MilestoneTemplate
{
    public const CAPSTONE_1 = 'capstone_1';

    public const CAPSTONE_2 = 'capstone_2';

    /** A line only Capstone 2 has, to tell whether a checklist already carries it. */
    private const CAPSTONE_2_MARKER = 'pre_oral_defense';

    /** The milestones that open only once everything above them is complete. */
    private const GATES = ['Pre-proposal', 'Chapter 1', 'Title Defense', 'System Development', 'Pre Oral Defense', 'Final Oral Defense', 'Final Manuscript'];

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

    /** @return int the milestones added */
    private static function addMilestones(Checklist $checklist, array $items, int $from = 0): int
    {
        $groups = collect($items)->groupBy('phase')->values();

        foreach ($groups as $at => $lines) {
            $milestone = $checklist->milestones()->create([
                'name' => $lines->first()['phase'],
                'position' => $from + $at,
                // Chapters open together once the pre-proposal is passed; Title
                // Defense waits for all of them.
                'requires_previous' => in_array($lines->first()['phase'], self::GATES, true),
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

    /**
     * Capstone 2, in order: building the system, Chapter 4, Pre Oral
     * Defense, Chapter 5, Final Oral Defense, and the final manuscript.
     *
     * System Development opens once Capstone 1 is complete. A chapter opens
     * together with the defense milestone above it, the way chapters run
     * beside each other in Capstone 1; each defense waits for everything
     * above it, and its RSC for the defense.
     */
    private static function capstone2Items(): array
    {
        $F = MilestoneItem::FORM;
        $D = MilestoneItem::DOCUMENT;
        $A = MilestoneItem::APPROVAL;
        $X = MilestoneItem::DEFENSE;
        $C = Deliverable::CUSTOM;

        return [
            ['template_key' => 'c2_development_plan', 'title' => 'Signed Capstone 2 Development Plan', 'short' => 'Dev Plan', 'phase' => 'System Development', 'kind' => $F],
            ['template_key' => 'c2_system_demo', 'title' => 'System Demonstration to the Adviser', 'short' => 'Demo', 'phase' => 'System Development', 'kind' => $C],

            ['template_key' => 'chapter_4_draft', 'title' => 'Chapter 4 Draft', 'short' => 'Draft', 'phase' => 'Chapter 4', 'kind' => $D, 'chapter' => '4'],
            ['template_key' => 'clf_chapter_4', 'title' => 'Signed CLF Approving Chapter 4 (CaPA/Adviser)', 'short' => 'Approved CLF', 'phase' => 'Chapter 4', 'kind' => $A, 'role' => 'adviser', 'chapter' => '4'],

            ['template_key' => 'clf_recommend_pod_adviser', 'title' => 'Signed CLF Recommending for Pre Oral Defense (CaPA/Adviser)', 'short' => 'Adviser CLF', 'phase' => 'Pre Oral Defense', 'kind' => $A, 'role' => 'adviser'],
            ['template_key' => 'clf_recommend_pod_consultant', 'title' => 'Signed CLF Recommending for Pre Oral Defense (Consultant)', 'short' => 'Consultant CLF', 'phase' => 'Pre Oral Defense', 'kind' => $A, 'role' => 'consultant'],
            ['template_key' => 'pod_hearing_notice', 'title' => 'Signed Pre Oral Defense Hearing Notice', 'short' => 'Hearing Notice', 'phase' => 'Pre Oral Defense', 'kind' => $F],
            ['template_key' => self::CAPSTONE_2_MARKER, 'title' => 'Pre Oral Defense', 'short' => 'Defense', 'phase' => 'Pre Oral Defense', 'kind' => $X, 'defense_type' => 'Pre Oral Defense'],
            ['template_key' => 'pod_rsc', 'title' => 'Signed (Noted) POD RSC', 'short' => 'POD RSC', 'phase' => 'Pre Oral Defense', 'kind' => $F],

            ['template_key' => 'chapter_5_draft', 'title' => 'Chapter 5 Draft', 'short' => 'Draft', 'phase' => 'Chapter 5', 'kind' => $D, 'chapter' => '5'],
            ['template_key' => 'clf_chapter_5', 'title' => 'Signed CLF Approving Chapter 5 (CaPA/Adviser)', 'short' => 'Approved CLF', 'phase' => 'Chapter 5', 'kind' => $A, 'role' => 'adviser', 'chapter' => '5'],

            ['template_key' => 'clf_recommend_fod_adviser', 'title' => 'Signed CLF Recommending for Final Oral Defense (CaPA/Adviser)', 'short' => 'Adviser CLF', 'phase' => 'Final Oral Defense', 'kind' => $A, 'role' => 'adviser'],
            ['template_key' => 'clf_recommend_fod_consultant', 'title' => 'Signed CLF Recommending for Final Oral Defense (Consultant)', 'short' => 'Consultant CLF', 'phase' => 'Final Oral Defense', 'kind' => $A, 'role' => 'consultant'],
            ['template_key' => 'fod_hearing_notice', 'title' => 'Signed Final Oral Defense Hearing Notice', 'short' => 'Hearing Notice', 'phase' => 'Final Oral Defense', 'kind' => $F],
            ['template_key' => 'final_oral_defense', 'title' => 'Final Oral Defense', 'short' => 'Defense', 'phase' => 'Final Oral Defense', 'kind' => $X, 'defense_type' => 'Final Oral Defense'],
            ['template_key' => 'fod_rsc', 'title' => 'Signed (Noted) FOD RSC', 'short' => 'FOD RSC', 'phase' => 'Final Oral Defense', 'kind' => $F],

            ['template_key' => 'final_manuscript', 'title' => 'Final Manuscript', 'short' => 'Manuscript', 'phase' => 'Final Manuscript', 'kind' => $D, 'chapter' => 'final'],
            ['template_key' => 'editing_certificate', 'title' => 'Signed Certificate of Editing (Grammarian)', 'short' => 'Editing', 'phase' => 'Final Manuscript', 'kind' => $F],
            ['template_key' => 'approval_sheet', 'title' => 'Signed Approval Sheet', 'short' => 'Approval Sheet', 'phase' => 'Final Manuscript', 'kind' => $F],
        ];
    }
}
