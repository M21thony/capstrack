<?php

namespace App\Support;

use App\Models\DefenseSchedule;
use App\Models\GradingRubric;

/**
 * A defense rubric on paper, for a defense the system cannot reach.
 *
 * A defense goes ahead whether or not the signal or the power does. This is
 * the department's own form - the CSPC/CCS letterhead, title, date and team
 * alias, the rubric with each category's levels across the top and a
 * descriptor per criterion and level, the score per category, the verdict,
 * remarks and the panel's signatures - filled from the rubric the grading
 * page scores with. The team prints one for each panelist and brings them,
 * so the panel can grade by hand and enter the same scores in Team Grading
 * once CapsTrack is back. The PDF and the Excel copy are both built from
 * what this returns, so they cannot disagree.
 */
class GradingSheet
{
    /** The panel seats the form has a signature for, in the order it prints them. */
    private const SEATS = ['Panel Member 1', 'Panel Member 2', 'Panel Chair'];

    /**
     * @return array{
     *     team: string, title: string, stage: string, attempt: int, date: ?string, when: ?string, venue: ?string,
     *     members: list<string>, rubric: string, description: ?string, total: float, levelCount: int,
     *     sections: list<array{name: string, max: float, levelTotals: list<float>, items: list<array{name: string, description: ?string, max: float, levels: list<array{score: float, label: string, desc: string}>}>}>,
     *     outcomes: list<string>, signatures: array<string, ?string>, reference: string, printed: string, note: string
     * }
     */
    public function for(DefenseSchedule $defense, GradingRubric $rubric): array
    {
        $defense->loadMissing(['team', 'panels.expert']);
        $sections = $this->sections($rubric);

        $members = $defense->team
            ? $defense->team->members()->orderBy('l_name')->get()
                ->map(fn ($s) => trim($s->l_name.', '.$s->f_name.($s->m_name ? ' '.mb_substr($s->m_name, 0, 1).'.' : '')))
                ->filter()->values()->all()
            : [];

        // Each seat's name, so every signature line carries who sits there.
        $signatures = [];
        foreach (self::SEATS as $seat) {
            $signatures[$seat] = $defense->panels->firstWhere('panel_role', $seat)?->expert?->name;
        }

        return [
            'team' => $defense->team?->team_name ?? 'Team',
            'title' => $defense->team?->project_title ?: 'No title yet',
            'stage' => (string) $defense->defense_type,
            'attempt' => max(1, (int) $defense->attempt),
            'date' => $defense->schedule_date?->format('F j, Y'),
            'when' => $defense->schedule_date
                ? $defense->schedule_date->format('l, F j, Y · g:i A').($defense->end_date ? ' – '.$defense->end_date->format('g:i A') : '')
                : null,
            'venue' => $defense->venue,
            'members' => $members,
            'rubric' => (string) $rubric->rubric_name,
            'description' => $rubric->description ?: null,
            'total' => (float) $rubric->total_points,
            'levelCount' => max(1, (int) collect($sections)->max(fn ($s) => count($s['levelTotals']))),
            'sections' => $sections,
            'outcomes' => array_map(fn ($o) => DefenseOutcome::label($o, $defense->defense_type), DefenseOutcome::forStage($defense->defense_type, (int) $defense->attempt)),
            'signatures' => $signatures,
            // Written on the sheet so the right defense is found when the scores are typed in.
            'reference' => 'Defense #'.$defense->defense_id,
            'printed' => now()->format('M j, Y g:i A'),
            'note' => 'Printed by the team, one for each panelist · if CapsTrack cannot be reached, grade on this, then enter the scores, the remarks and the verdict in CapsTrack (Team Grading) · Defense #'.$defense->defense_id,
        ];
    }

    /**
     * The same form with nothing filled in, for a student to read before
     * their defense: the rubric, its levels and the verdicts, with the title,
     * team, scores and signatures left blank.
     *
     * @return array<string, mixed> The shape for() returns.
     */
    public function blank(GradingRubric $rubric, string $stage): array
    {
        $sections = $this->sections($rubric);

        return [
            'team' => '',
            'title' => '',
            'stage' => $stage,
            'attempt' => 1,
            'date' => null,
            'when' => null,
            'venue' => null,
            'members' => [],
            'rubric' => (string) $rubric->rubric_name,
            'description' => $rubric->description ?: null,
            'total' => (float) $rubric->total_points,
            'levelCount' => max(1, (int) collect($sections)->max(fn ($s) => count($s['levelTotals']))),
            'sections' => $sections,
            'outcomes' => array_map(fn ($o) => DefenseOutcome::label($o, $stage), DefenseOutcome::forStage($stage)),
            'signatures' => array_fill_keys(self::SEATS, null),
            'reference' => '',
            'printed' => now()->format('M j, Y g:i A'),
            'note' => 'The rubric your panel grades the '.$stage.' with · from CapsTrack, printed '.now()->format('M j, Y'),
        ];
    }

    /**
     * The rubric's criteria grouped by category, each with its levels best
     * first and the points each level is worth for the category as a whole.
     *
     * @return list<array{name: string, max: float, levelTotals: list<float>, items: list<array{name: string, description: ?string, max: float, levels: list<array{score: float, label: string, desc: string}>}>}>
     */
    private function sections(GradingRubric $rubric): array
    {
        $rubric->loadMissing(['criteria' => fn ($q) => $q->orderBy('sort_order')]);

        return $rubric->criteria
            ->groupBy(fn ($c) => $c->category ?: 'General')
            ->map(function ($criteria, $name) {
                $items = $criteria->map(fn ($c) => [
                    'name' => (string) $c->criterion_name,
                    'description' => $c->description,
                    'max' => (float) $c->max_points,
                    // Best first, the way the form reads left to right.
                    'levels' => collect($c->decodedScoreLevels())
                        ->map(fn ($l) => [
                            'score' => (float) ($l['score'] ?? 0),
                            'label' => (string) ($l['label'] ?? ''),
                            'desc' => (string) ($l['desc'] ?? ''),
                        ])
                        ->sortByDesc('score')->values()->all(),
                ])->values();

                $width = (int) $items->max(fn ($i) => count($i['levels']));

                return [
                    'name' => (string) $name,
                    'max' => (float) ($criteria->first()->category_max_points ?? $criteria->sum('max_points')),
                    // The points each level is worth for the category as a whole:
                    // the column headings of the form (20, 15, 10, 5).
                    'levelTotals' => collect(range(0, max(0, $width - 1)))
                        ->map(fn ($k) => (float) $items->sum(fn ($i) => $i['levels'][$k]['score'] ?? 0))
                        ->all(),
                    'items' => $items->all(),
                ];
            })->values()->all();
    }

    /** "Team1 - Title Defense - Rating Sheet", safe as a file name. */
    public static function fileName(array $sheet, string $extension): string
    {
        $base = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $sheet['team'].' - '.$sheet['stage'].' - Rating Sheet');

        return trim(preg_replace('/\s+/', ' ', $base)).'.'.$extension;
    }

    /** 10, 7.5, 0.25 - no trailing zeros. */
    public static function points(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /**
     * The seal and the shield of the letterhead, inlined so the PDF needs no
     * file access, and shrunk to the size they print at: the seal is a
     * 1200px image for a mark under an inch wide.
     */
    public static function logo(string $which): string
    {
        $path = public_path('images/brand/'.($which === 'ccs' ? 'ccs.png' : 'cspc.png'));

        if (! is_file($path)) {
            return '';
        }

        $png = (string) file_get_contents($path);

        if (function_exists('imagecreatefromstring') && ($src = @imagecreatefromstring($png))) {
            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min(1, 220 / max($w, $h));
            $dst = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            ob_start();
            imagepng($dst);
            $png = (string) ob_get_clean();
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
