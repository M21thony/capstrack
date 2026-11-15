<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\TeamRatingRubric;
use App\Support\PeerRatingRubric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Team Rating rubrics on Grading Rubrics: the CaPSA/TSA writes her own,
 * edits them, and chooses which one new ratings are made with.
 *
 * A rating keeps its own copy of the rubric (TeamRatingForm::rubric()), so
 * nothing here changes a rating that already exists.
 */
class TeamRatingRubricController extends Controller
{
    /**
     * Every rubric as the page lists it, the department's first.
     *
     * @return list<array{id: ?int, name: string, criteria: list<array{name: string, short: string, levels: list<string>}>, max: int, in_use: bool, fixed: bool}>
     */
    public static function listing(): array
    {
        $own = TeamRatingRubric::orderBy('name')->get();
        $row = fn (PeerRatingRubric $rubric, ?int $id, bool $inUse) => $rubric->toArray() + [
            'id' => $id, 'max' => $rubric->max(), 'in_use' => $inUse, 'fixed' => $id === null,
        ];

        return [
            $row(PeerRatingRubric::default(), null, $own->doesntContain('is_active', true)),
            ...$own->map(fn (TeamRatingRubric $r) => ['name' => $r->name] + $row($r->asRubric(), $r->id, $r->is_active))->all(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $rubric = TeamRatingRubric::create($this->validated($request) + ['created_by' => auth('expert')->id()]);

        return response()->json(['id' => $rubric->id, 'rubrics' => self::listing()], 201);
    }

    public function update(Request $request, TeamRatingRubric $rubric): JsonResponse
    {
        $rubric->update($this->validated($request, $rubric));

        return response()->json(['id' => $rubric->id, 'rubrics' => self::listing()]);
    }

    public function destroy(TeamRatingRubric $rubric): JsonResponse
    {
        $rubric->delete();

        return response()->json(['rubrics' => self::listing()]);
    }

    /** Chooses the rubric new ratings are made with; 'default' is the department's. */
    public function use(string $rubric): JsonResponse
    {
        if ($rubric === 'default') {
            TeamRatingRubric::query()->update(['is_active' => false]);
        } else {
            TeamRatingRubric::findOrFail($rubric)->putInUse();
        }

        return response()->json(['rubrics' => self::listing()]);
    }

    /**
     * @return array{name: string, criteria: list<array{name: string, short: string, levels: list<string>}>}
     */
    private function validated(Request $request, ?TeamRatingRubric $rubric = null): array
    {
        $levels = count(PeerRatingRubric::LEVELS);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('team_rating_rubrics', 'name')->ignore($rubric?->id)],
            'criteria' => ['required', 'array', 'min:1', 'max:'.PeerRatingRubric::MAX_CRITERIA],
            'criteria.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'criteria.*.short' => ['nullable', 'string', 'max:24'],
            'criteria.*.levels' => ['required', 'array', 'size:'.$levels],
            'criteria.*.levels.*' => ['required', 'string', 'max:600'],
        ], [
            'name.unique' => 'Another rubric already has this name.',
            'criteria.required' => 'Add at least one criterion.',
            'criteria.max' => 'A rubric has at most '.PeerRatingRubric::MAX_CRITERIA.' criteria.',
            'criteria.*.name.required' => 'Give every criterion a name.',
            'criteria.*.name.distinct' => 'Two criteria have the same name.',
            'criteria.*.levels.*.required' => 'Describe every level of every criterion.',
        ]);

        return [
            'name' => trim($data['name']),
            'criteria' => collect($data['criteria'])->map(fn (array $c) => [
                'name' => trim($c['name']),
                'short' => trim((string) ($c['short'] ?? '')) ?: trim($c['name']),
                'levels' => array_map('trim', array_values($c['levels'])),
            ])->values()->all(),
        ];
    }
}
