<?php

namespace App\Support;

/**
 * The UN Sustainable Development Goals a pre-proposal can serve.
 *
 * A study often serves more than one, so a pre-proposal keeps as many as the
 * team ticks, saved as one line ("SDG 4 – Quality Education, SDG 9 – ...")
 * so every page that shows the SDG reads it as it always has.
 */
class SustainableDevelopmentGoals
{
    public const ALL = [
        'SDG 1 – No Poverty', 'SDG 2 – Zero Hunger', 'SDG 3 – Good Health and Well-being',
        'SDG 4 – Quality Education', 'SDG 5 – Gender Equality', 'SDG 6 – Clean Water and Sanitation',
        'SDG 7 – Affordable and Clean Energy', 'SDG 8 – Decent Work and Economic Growth',
        'SDG 9 – Industry, Innovation and Infrastructure', 'SDG 10 – Reduced Inequality',
        'SDG 11 – Sustainable Cities and Communities', 'SDG 12 – Responsible Consumption and Production',
        'SDG 13 – Climate Action', 'SDG 14 – Life Below Water', 'SDG 15 – Life on Land',
        'SDG 16 – Peace, Justice and Strong Institutions', 'SDG 17 – Partnerships for the Goals',
    ];

    public const SEPARATOR = ', ';

    /**
     * The goals in a saved line, or in what a form sent back.
     *
     * @return string[]
     */
    public static function split(array|string|null $goals): array
    {
        if (is_array($goals)) {
            return array_values(array_filter(array_map('trim', $goals), fn ($goal) => $goal !== ''));
        }

        // Split before each "SDG n", not on commas: a goal's own name can hold one.
        // Read out of a PDF, the goals can come with only a space between them.
        $parts = preg_split('/,?\s+(?=SDG\s*\d)/i', (string) $goals);

        return array_values(array_filter(array_map('trim', $parts), fn ($goal) => $goal !== ''));
    }

    /** @param  string[]  $goals */
    public static function join(array $goals): string
    {
        return implode(self::SEPARATOR, array_values(array_unique($goals)));
    }
}
