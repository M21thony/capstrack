<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Team;

/**
 * Which checklist a team follows: the one its section's CaPSA keeps for the
 * program that section belongs to. A team has no checklist of its own.
 */
class TeamChecklist
{
    public function __construct(private SectionScope $sections) {}

    public function for(Team $team): ?Checklist
    {
        return $this->forSection($this->sections->sectionFor($team));
    }

    public function forSection(string $section): ?Checklist
    {
        $capsa = $this->capsaFor($section);

        return $capsa
            ? Checklist::where('capsa_id', $capsa->expert_id)->where('program', self::programOf($section))->oldest('id')->first()
            : null;
    }

    /** The CaPSA who handles a section, or null when nobody does. */
    public function capsaFor(string $section): ?CapstoneExpert
    {
        if ($section === '') {
            return null;
        }

        return CapstoneExpert::whereNotNull('sections')->orderBy('expert_id')->get()
            ->first(fn (CapstoneExpert $capsa) => in_array($section, $capsa->sections ?? [], true));
    }

    /** "BSIT-3A" is in BSIT. */
    public static function programOf(string $section): string
    {
        return strtoupper(explode('-', $section)[0]);
    }
}
