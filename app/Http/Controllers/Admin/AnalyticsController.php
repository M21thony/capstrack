<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\DefenseAnalytics;
use App\Support\DefenseProgress;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Analytics page, for the Admin and for every CaPSA/TSA.
 *
 * Both open the same page and see different amounts of it: an Admin every
 * team, a CaPSA/TSA the teams in their sections. That scoping is not done here -
 * DefenseAnalytics does it from the signed-in expert - so the page and the
 * CSV cannot disagree about who is in them.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, DefenseAnalytics $analytics)
    {
        $filters = $request->validate([
            // The year also goes into the CSV's filename, so only a real year.
            'school_year' => ['nullable', 'string', 'regex:/^(\d{4}-\d{4}|'.DefenseAnalytics::ALL_YEARS.')$/'],
            'program' => ['nullable', Rule::in([...array_keys(Student::PROGRAM_COURSES), 'Unassigned'])],
            'section' => ['nullable', 'string', 'max:60'],
            'tag' => ['nullable', 'string', 'max:60'],
            'export' => ['nullable', 'in:csv'],
        ]);

        $data = $analytics->build(auth('expert')->user(), $filters);

        if (($filters['export'] ?? null) === 'csv') {
            return $this->csv($data);
        }

        return view('capsa.analytics', ['a' => $data]);
    }

    /**
     * The team-by-team table, as it stands under the filters on the page.
     *
     * One row per team because that is what gets opened in a spreadsheet and
     * counted, filtered and pivoted by whoever asked for the export. Tags are
     * joined into one cell for the same reason; a row per tag would list a
     * team several times.
     *
     * @param  array<string, mixed>  $data
     */
    private function csv(array $data): StreamedResponse
    {
        $year = $data['filters']['school_year'] === DefenseAnalytics::ALL_YEARS ? 'all-years' : $data['filters']['school_year'];
        $filename = 'defense-results-'.$year.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            // Excel opens a CSV in the local code page unless it finds a BOM,
            // which turns the "·" in a section name into mojibake.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_merge(
                ['Team', 'Project title', 'Section', 'Project tags'],
                DefenseProgress::STAGES,
                ['Defense attempts'],
            ));

            foreach ($data['teams'] as $team) {
                fputcsv($out, array_merge(
                    array_map($this->safeCell(...), [$team['name'], $team['title'], $team['section'], implode(', ', $team['tags'])]),
                    array_map(
                        fn (string $stage) => $team['stages'][$stage] ? DefenseAnalytics::statusLabel($team['stages'][$stage]) : '',
                        DefenseProgress::STAGES
                    ),
                    [$team['attempts']],
                ));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A team name is typed by a student, and a cell that starts with = + - or @
     * is run as a formula when the file is opened. Prefixing a quote keeps it
     * text.
     */
    private function safeCell(?string $value): string
    {
        $value = (string) $value;
        $formulaStarts = ['=', '+', '-', '@', "\t", "\r"];

        return $value !== '' && in_array($value[0], $formulaStarts, true) ? "'".$value : $value;
    }
}
