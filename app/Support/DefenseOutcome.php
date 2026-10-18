<?php

namespace App\Support;

/**
 * What a panel decides at the end of a defense.
 *
 * This is one decision per defense, not a tally of the panelists. The panel
 * confers first and then tells the team the result together, so the outcome
 * belongs to the defense and is recorded once, by the Panel Chair. Each
 * panelist still scores the rubric independently - that is their assessment,
 * and the team's mark is the average of it - but the verdict is the panel's.
 *
 * The options differ by stage, which a single fixed list could not express:
 * a pre-proposal can be sent back for a whole new title, and by the time a
 * team reaches Final Oral Defense that is not a thing anyone can ask for.
 *
 * There is no "rejected". A team that does not make it is one that runs out
 * of defense window (see DefenseSchedule / defense_windows), not one the
 * panel turns away on the day.
 */
class DefenseOutcome
{
    public const PASSED = 'passed';

    public const PASSED_WITH_REVISION = 'passed_with_revision';

    public const REDEFENSE = 'redefense';

    public const REDEFENSE_NEW_TITLE = 'redefense_new_title';

    /**
     * Not a panel's decision: set when the CaPA says "Not ready", before or on
     * the defense day. (Older rows were also set on the day by an automatic
     * check of open requirements, since removed.) The defense keeps its date,
     * venue and panel, and the team's next try waits for a new date.
     */
    public const CANCELLED = 'cancelled';

    public const PRE_PROPOSAL = 'Pre-proposal Defense';

    /** The end of Capstone 1 - passing it locks the team's members. */
    public const TITLE_DEFENSE = 'Title Defense';

    /**
     * The outcomes a stage may end in, in the order a panel would consider
     * them - best first.
     *
     * @return array<int, string>
     */
    public static function forStage(?string $defenseType): array
    {
        // Only the pre-proposal stage can send a team back to the drawing
        // board for a new title, or pass one subject to revisions. Later
        // stages either pass or come back.
        if ($defenseType === self::PRE_PROPOSAL) {
            return [
                self::PASSED,
                self::PASSED_WITH_REVISION,
                self::REDEFENSE,
                self::REDEFENSE_NEW_TITLE,
            ];
        }

        return [self::PASSED, self::REDEFENSE];
    }

    public static function allowedForStage(?string $defenseType, ?string $outcome): bool
    {
        return $outcome !== null && in_array($outcome, self::forStage($defenseType), true);
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::PASSED, self::PASSED_WITH_REVISION, self::REDEFENSE, self::REDEFENSE_NEW_TITLE];
    }

    public static function label(?string $outcome, ?string $defenseType = null): string
    {
        return match ($outcome) {
            self::PASSED => 'Passed',
            self::PASSED_WITH_REVISION => 'Passed with revision',
            // Outside the pre-proposal stage there is only one kind of
            // redefense, so the qualifier is noise there.
            self::REDEFENSE => $defenseType === self::PRE_PROPOSAL ? 'Redefense with major revision' : 'Redefense',
            self::REDEFENSE_NEW_TITLE => 'Redefense with a new title',
            self::CANCELLED => 'Cancelled',
            default => 'Not decided yet',
        };
    }

    public static function description(?string $outcome, ?string $defenseType = null): string
    {
        return match ($outcome) {
            self::PASSED => 'The team moves on to the next stage.',
            self::PASSED_WITH_REVISION => 'The team moves on, but has corrections to make first.',
            self::REDEFENSE => 'The team defends this stage again with the same title.',
            self::REDEFENSE_NEW_TITLE => 'The team starts over with a new title, and submits fresh pre-proposals.',
            self::CANCELLED => 'The defense was cancelled. The team waits for a new date.',
            default => '',
        };
    }

    /** Whether this outcome sends the team back to defend the stage again. */
    public static function requiresRedefense(?string $outcome): bool
    {
        return in_array($outcome, [self::REDEFENSE, self::REDEFENSE_NEW_TITLE], true);
    }

    /**
     * How serious the outcome is, for its colour: a pass is green, a
     * redefense orange, and starting over with a new title red.
     */
    public static function tone(?string $outcome): string
    {
        return match ($outcome) {
            self::PASSED, self::PASSED_WITH_REVISION => 'pass',
            self::REDEFENSE => 'major',
            self::REDEFENSE_NEW_TITLE, self::CANCELLED => 'fail',
            default => 'none',
        };
    }

    /** Whether the team got through this stage, revisions or not. */
    public static function isPass(?string $outcome): bool
    {
        return in_array($outcome, [self::PASSED, self::PASSED_WITH_REVISION], true);
    }

    /**
     * Whether the team has to put up a new title, which reopens their
     * pre-proposals so they can submit one.
     */
    public static function requiresNewTitle(?string $outcome): bool
    {
        return $outcome === self::REDEFENSE_NEW_TITLE;
    }
}
