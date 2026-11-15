<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\ReportTable;
use App\Support\CapsaReports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The report builder: one row per student (or expert), the CaPSA/TSA picks the
 * columns, and each saved table keeps its own columns and filters.
 */
class ReportBuilderController extends Controller
{
    private const FILTER_KEYS = ['year', 'term', 'program', 'section'];

    public function __construct(private CapsaReports $reports) {}

    public function show(Request $request, ReportTable $table): View
    {
        $this->own($table);

        return view('capsa.reports.builder', [
            'tables' => ReportTable::where('expert_id', auth('expert')->id())->orderBy('name')->get()->map->toClient()->values(),
            'current' => $table->id,
            'fields' => ReportTable::FIELDS,
            // Filters picked on Reports Home win over the saved ones, until saved.
            'urlFilters' => (object) $request->only(self::FILTER_KEYS),
        ]);
    }

    /** A link to a template (Expert Management's View Report): their saved copy of it, made the first time. */
    public function template(string $template): RedirectResponse
    {
        $t = collect(ReportTable::TEMPLATES)->firstWhere('key', $template) ?? abort(404);
        $table = ReportTable::firstOrCreate(['expert_id' => auth('expert')->id(), 'name' => $t['name']], [
            'mode' => $t['mode'],
            'columns' => array_map(fn ($k) => ['key' => $k, 'label' => ReportTable::FIELDS[$k], 'sources' => [$k], 'separator' => ' · '], $t['fields']),
            'filters' => [],
        ]);

        return redirect()->route('capsa.reports.builder', $table);
    }

    /** The rows and filter choices for one period, for the builder's script. */
    public function data(Request $request): JsonResponse
    {
        $ctx = $this->reports->context(auth('expert')->user(), $request->only(self::FILTER_KEYS));
        $pairs = fn (array $options) => array_map(fn ($k, $v) => [(string) $k, $v], array_keys($options), $options);

        return response()->json([
            'rows' => $this->reports->builderRows($ctx),
            'expertRows' => $this->reports->expertRows($ctx),
            'options' => [
                'year' => array_map(fn ($y) => [$y, $y === $ctx['thisYear'] ? $y.' (current)' : $y], $ctx['years']),
                'term' => $pairs($ctx['termOptions']),
                'program' => $pairs($ctx['programOptions']),
                'section' => $pairs($ctx['sectionOptions']),
            ],
            'filters' => ['year' => $ctx['year'], 'term' => (string) $ctx['term'], 'program' => $ctx['program'], 'section' => $ctx['section']],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $table = ReportTable::create($data + ['expert_id' => auth('expert')->id(), 'filters' => []]);

        return response()->json(['table' => $table->toClient(), 'url' => route('capsa.reports.builder', $table)], 201);
    }

    public function update(Request $request, ReportTable $table): JsonResponse
    {
        $this->own($table);
        $table->update($this->validated($request, $table));

        return response()->json(['table' => $table->fresh()->toClient()]);
    }

    public function destroy(ReportTable $table): JsonResponse
    {
        $this->own($table);
        $table->delete();
        $next = ReportTable::where('expert_id', auth('expert')->id())->orderBy('name')->first();

        return response()->json(['url' => $next ? route('capsa.reports.builder', $next) : route('capsa.reports')]);
    }

    private function own(ReportTable $table): void
    {
        abort_unless($table->expert_id === auth('expert')->id(), 404);
    }

    private function validated(Request $request, ?ReportTable $table): array
    {
        $need = $table ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$need, 'string', 'max:70'],
            'mode' => [$need, Rule::in(ReportTable::MODES)],
            'columns' => [$need, 'array', 'min:1', 'max:40'],
            'columns.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'columns.*.label' => ['required', 'string', 'max:60'],
            'columns.*.sources' => ['required', 'array', 'min:1', 'max:25'],
            'columns.*.sources.*' => [Rule::in(array_keys(ReportTable::FIELDS))],
            // "present", not "required": required trims first, so the new-line separator would read as empty.
            'columns.*.separator' => ['present', Rule::in(ReportTable::SEPARATORS)],
            'filters' => ['sometimes', 'array'],
            'filters.*' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            // Case doesn't make a different name: "Fees" and "fees" would look the same in the list.
            $taken = ReportTable::where('expert_id', auth('expert')->id())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
                ->when($table, fn ($q) => $q->where('id', '!=', $table->id))
                ->exists();
            if ($taken || $data['name'] === '') {
                throw ValidationException::withMessages(['name' => $taken ? 'A saved table already has that name.' : 'Give the table a name.']);
            }
        }
        if (isset($data['columns'])) {
            $data['columns'] = array_map(fn ($c) => array_intersect_key($c, array_flip(['key', 'label', 'sources', 'separator'])), $data['columns']);
        }
        if (isset($data['filters'])) {
            $data['filters'] = array_intersect_key($data['filters'], array_flip([...self::FILTER_KEYS, 'search']));
        }

        return $data;
    }
}
