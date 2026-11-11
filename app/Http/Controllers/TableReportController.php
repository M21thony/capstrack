<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * View Report, for any table on a CaPSA/TSA, Expert or Admin page.
 *
 * The page sends the table it is showing; it is kept for a few hours under a
 * random token that only the person who opened it can read, and the Reports'
 * own screen (Capsa\ReportController::snapshot) is drawn over it. Nothing is
 * looked up again, so the report is what the page showed - filters and all -
 * and any table gets the same column picker, Preview PDF and PDF or Excel
 * download the CaPSA/TSA's reports have.
 */
class TableReportController extends Controller
{
    private const KEEP_HOURS = 3;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'scope' => ['nullable', 'string', 'max:300'],
            'from' => ['nullable', 'string', 'max:500'],
            'headings' => ['required', 'array', 'min:1', 'max:40'],
            'headings.*' => ['nullable', 'string', 'max:200'],
            'rows' => ['present', 'array', 'max:5000'],
            'rows.*' => ['array', 'max:40'],
            'rows.*.*' => ['nullable', 'string', 'max:5000'],
        ]);

        $headings = array_map(fn ($h) => (string) $h, array_values($data['headings']));
        $width = count($headings);
        // Every row exactly as wide as the headings, whatever the page sent.
        $rows = array_map(
            fn (array $row) => array_slice(array_pad(array_map(fn ($cell) => (string) $cell, array_values($row)), $width, ''), 0, $width),
            array_values($data['rows']),
        );

        $back = self::safeBack($data['from'] ?? null);
        $token = Str::random(40);

        Cache::put(self::key(auth('expert')->id(), $token), [
            'title' => $data['title'],
            'scope' => trim(($data['scope'] ?? ''), ' ·'),
            'headings' => $headings,
            'rows' => $rows,
            'back' => $back,
            // The Expert portal has no CaPSA/TSA sidebar to draw the report inside.
            'portal' => $back && str_starts_with($back, '/instructor') ? 'instructor' : 'capsa',
        ], now()->addHours(self::KEEP_HOURS));

        return response()->json(['url' => route('table.report.show', ['token' => $token, 'back' => $back])]);
    }

    /** @return array<string, mixed>|null the table kept for this person, or null once it has expired */
    public static function recall(int|string|null $expertId, string $token): ?array
    {
        return preg_match('/^[A-Za-z0-9]{40}$/', $token) ? Cache::get(self::key($expertId, $token)) : null;
    }

    /** A page of this site to go back to: a path, never another address. */
    public static function safeBack(mixed $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        return $path;
    }

    private static function key(int|string|null $expertId, string $token): string
    {
        return 'table-report:'.$expertId.':'.$token;
    }
}
