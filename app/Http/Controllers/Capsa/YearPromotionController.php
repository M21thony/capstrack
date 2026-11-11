<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Year Promotion without a file: the CaPSA/TSA picks their sections and moves the
 * students in them up one year, "3rd · A" to "4th · A".
 *
 * The other way is uploading the new school year's class list, where the
 * year comes from the list. This one is for when there is no new list yet,
 * or only a few sections moved. Accounts, teams and work all stay; only the
 * year on each student changes. A CaPSA/TSA may or may not keep the section in
 * the new year, so staying on is a choice made here too.
 */
class YearPromotionController extends Controller
{
    /** Capstone ends in 4th year; nobody is promoted past it. */
    public const TOP_YEAR = 4;

    private const ORDINALS = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];

    /** The CaPSA/TSA's sections below 4th year, each with its students and where they would go. */
    public function preview(): JsonResponse
    {
        return response()->json(['success' => true, 'sections' => $this->plan()->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['string'],
            'exclude' => ['nullable', 'array'],
            'exclude.*' => ['integer'],
            'stay_on' => ['nullable', 'array'],
            'stay_on.*' => ['string'],
        ], [
            'sections.required' => 'Pick at least one section to move up.',
        ]);

        // Only the CaPSA/TSA's own sections, whatever the request names.
        $plan = $this->plan()->whereIn('from', $validated['sections']);
        $leaveOut = array_map('intval', $validated['exclude'] ?? []);

        if ($plan->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'None of those sections are yours to move up.'], 422);
        }

        $promoted = 0;

        DB::transaction(function () use ($plan, $leaveOut, &$promoted) {
            foreach ($plan as $section) {
                foreach ($section['students'] as $s) {
                    if (in_array($s['id'], $leaveOut, true)) {
                        continue;
                    }

                    $student = Student::find($s['id']);
                    $next = $student ? self::nextYearSection((string) $student->year_section) : null;
                    if (! $next) {
                        continue;
                    }

                    $student->update(['year_section' => $next]);
                    // The class list says the same, so a later re-upload of last year's file is the odd one out.
                    StudentRoster::whereRaw('LOWER(email) = ?', [mb_strtolower((string) $student->email)])->update(['section' => $next]);
                    $promoted++;
                }
            }
        });

        $capsa = auth('expert')->user();
        $kept = $plan->filter(fn ($p) => ! $p['already_mine'] && $p['covered_by'] === null)
            ->pluck('to')->intersect($validated['stay_on'] ?? [])->unique()->values()->all();

        if ($kept !== []) {
            $capsa->update(['sections' => array_values(array_unique([...($capsa->sections ?? []), ...$kept]))]);
        }

        return response()->json([
            'success' => true,
            'promoted' => $promoted,
            'kept_sections' => $kept,
            'message' => $promoted.' '.($promoted === 1 ? 'student' : 'students').' moved up a year.',
        ]);
    }

    /**
     * "3rd · A" gives "4th · A"; 4th year and anything unreadable give null.
     */
    public static function nextYearSection(string $yearSection): ?string
    {
        if (! preg_match('/^\s*(\d+)\s*(?:st|nd|rd|th)?\b(.*)$/i', $yearSection, $m)) {
            return null;
        }

        $year = (int) $m[1];

        return $year >= 1 && $year < self::TOP_YEAR ? self::ORDINALS[$year + 1].$m[2] : null;
    }

    /**
     * @return Collection<int, array{from: string, to: string, students: list<array{id: int, name: string, team: ?string}>, already_mine: bool, covered_by: ?string}>
     */
    private function plan(): Collection
    {
        $capsa = auth('expert')->user();
        $mine = $capsa->sections ?? [];
        $owners = CapstoneExpert::whereNotNull('sections')->where('expert_id', '!=', $capsa->expert_id)->get();

        return Student::whereNotNull('course')->whereNotNull('year_section')->with('team')->get()
            ->map(function (Student $s) {
                $from = Student::computeSectionLabel($s->course, $s->year_section);
                $next = self::nextYearSection((string) $s->year_section);

                return $from && $next ? [$s, $from, Student::computeSectionLabel($s->course, $next)] : null;
            })
            ->filter(fn ($x) => $x && in_array($x[1], $mine, true))
            ->groupBy(fn ($x) => $x[1])
            ->map(fn (Collection $g, string $from) => [
                'from' => $from,
                'to' => $g->first()[2],
                'students' => $g->map(fn ($x) => [
                    'id' => (int) $x[0]->student_id,
                    'name' => trim($x[0]->f_name.' '.$x[0]->l_name) ?: $x[0]->email,
                    'team' => $x[0]->team?->team_name,
                ])->sortBy('name')->values()->all(),
                'already_mine' => in_array($g->first()[2], $mine, true),
                'covered_by' => $owners->first(fn ($e) => in_array($g->first()[2], $e->sections ?? [], true))?->name,
            ])
            ->sortKeys();
    }
}
