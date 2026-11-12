<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use App\Support\Bluebook;
use App\Support\DefenseOutcome;
use App\Support\SectionCatalog;
use App\Support\TeamChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $term = Term::active();
        $year = $term?->school_year ?? Setting::get('current_school_year');
        $sections = SectionCatalog::defined();

        // Who would be carried into a new school year, by the year they are in.
        $staying = Student::whereIn('student_id', $this->unfinished()->pluck('student_id'))->pluck('year_section');

        $semesters = Term::orderBy('semester')->get()->groupBy('school_year');
        $prefix = SectionCatalog::KEY.':';
        $past = Setting::where('key', 'like', $prefix.'%')->orderByDesc('key')->pluck('key')
            ->map(fn ($key) => substr($key, strlen($prefix)))
            ->reject(fn ($y) => $y === $year)
            ->mapWithKeys(fn ($y) => [$y => [
                'sections' => SectionCatalog::defined($y),
                'semesters' => ($semesters[$y] ?? collect())->pluck('semester')->unique()
                    ->map(fn ($s) => $s === 1 ? '1st' : '2nd')->implode(' · '),
            ]])->all();

        return view('admin.settings', [
            'currentSchoolYear' => $year,
            'semester' => $term?->semester ?? 1,
            'sections' => $sections,
            'inUse' => self::inUse($sections),
            'notPromoted' => $staying->filter(fn ($y) => str_starts_with((string) $y, '3'))->count(),
            'repeaters' => $staying->filter(fn ($y) => str_starts_with((string) $y, '4'))->count(),
            'past' => $past,
            'programs' => CapstoneExpert::PROGRAMS,
            'years' => [SectionCatalog::STARTING_YEAR],
        ]);
    }

    /**
     * Declare the school year and semester that is running.
     *
     * Students and teams that have not finished are carried into it; a team
     * that passed Final Oral Defense stays behind in the semester it finished
     * in. A new school year also starts its own section list, and takes the
     * 3rd-year sections off every CaPSA/TSA - 4th-year ones stay, because a
     * class keeps its section from Capstone 1 into Capstone 2.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // e.g. "2026-2027" - matches the format already used elsewhere
            // (CapstoneExpert::getResolvedSchoolYearAttribute()).
            'current_school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['nullable', 'in:1,2'],
        ]);

        $old = Term::active();
        $year = $validated['current_school_year'];
        $semester = (int) ($validated['semester'] ?? $old?->semester ?? 1);

        if ($old && $old->school_year === $year && $old->semester === $semester) {
            return back()->with('success', 'Current school year updated.');
        }

        $sections = SectionCatalog::defined();
        $carry = $old ? $this->unfinished() : collect();

        // The Admin never picks Capstone 1 or 2 - the semester does not decide
        // a team's stage - so the stage the table insists on is always 1.
        $new = Term::firstOrCreate(['school_year' => $year, 'semester' => $semester], ['capstone_stage' => 1]);

        // Rows written before terms existed belong to the semester they were written in.
        StudentSchoolYear::acrossAllTerms()->whereNull('term_id')->update(['term_id' => ($old ?? $new)->term_id]);

        $have = StudentSchoolYear::forTerm($new->term_id)->pluck('student_id')->all();

        foreach ($carry as $row) {
            if (! in_array($row->student_id, $have)) {
                StudentSchoolYear::create([
                    'student_id' => $row->student_id,
                    'term_id' => $new->term_id,
                    'team_id' => $row->team_id,
                    's_role_id' => $row->s_role_id,
                    'school_year' => $year,
                    'program' => $row->program,
                    'student_status' => $row->student_status,
                ]);
            }
        }

        $new->activate();
        Setting::set('current_school_year', $year);

        if (! $old) {
            // The first year ever declared: the list already written is its list.
            SectionCatalog::store($sections);
        } elseif ($old->school_year !== $year) {
            Setting::set(SectionCatalog::KEY.':'.$old->school_year, json_encode($sections));

            // Going back to a year that was already run keeps the list it had.
            if (Setting::get(SectionCatalog::KEY.':'.$year) === null) {
                SectionCatalog::store([]);
            }

            CapstoneExpert::whereNotNull('sections')->get()->each(fn ($expert) => $expert->forceFill([
                'sections' => array_values(preg_grep('/^[A-Z]+-3/', $expert->sections ?? [], PREG_GREP_INVERT)),
            ])->save());

            // Still unfinished in 4th year when a new year starts: a repeater.
            Student::whereIn('student_id', $carry->pluck('student_id'))
                ->where('year_section', 'like', '4%')->update(['is_returnee' => true]);

            TeamChecklist::startYear($old->school_year, $year);
        }

        return back()->with('success', 'Now '.$year.', '.($semester === 1 ? '1st' : '2nd').' semester.');
    }

    /**
     * Set how many 3rd-year sections each ticked program runs.
     *
     * The number is the total: 5 means A to E. Raising it adds the missing
     * ones, lowering it takes the last ones off - but never one that still has
     * students or a CaPSA/TSA.
     *
     * The Admin picks no year. 4th-year sections are not made here: made for
     * both years at once, every CaPSA/TSA looked like they ran 3rd and 4th year
     * together. They come from the student list once students are in them.
     */
    public function storeSections(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'programs' => ['required', 'array', 'min:1'],
            'programs.*' => ['string', 'in:'.implode(',', CapstoneExpert::PROGRAMS)],
            'count' => ['required', 'integer', 'min:1', 'max:'.strlen(SectionCatalog::LETTERS)],
        ], [
            'programs.required' => 'Choose at least one program.',
        ]);

        $before = SectionCatalog::defined();
        $after = $before;
        $year = SectionCatalog::STARTING_YEAR;

        foreach ($validated['programs'] as $program) {
            $after = array_merge(
                array_filter($after, fn ($s) => ! str_starts_with($s, $program.'-'.$year)),
                SectionCatalog::range($program, $year, 'A', SectionCatalog::LETTERS[$validated['count'] - 1]),
            );
        }

        $added = count(array_diff($after, $before));
        $dropped = array_values(array_diff($before, $after));

        if ($busy = self::inUse($dropped)) {
            return back()->withInput()->withErrors(['count' => 'In use, cannot be removed: '.implode(', ', $busy)]);
        }

        SectionCatalog::store($after);

        $said = array_filter([
            $added ? $added.' section'.($added === 1 ? '' : 's').' added' : null,
            $dropped ? count($dropped).' section'.(count($dropped) === 1 ? '' : 's').' removed' : null,
        ]);

        return back()->with('success', $said ? ucfirst(implode(', ', $said)).'.' : 'Those sections were already listed.');
    }

    /** Remove one or more sections from the list - never one with students or a CaPSA/TSA in it. */
    public function destroySection(Request $request): RedirectResponse
    {
        $validated = $request->validate(['section' => ['required'], 'section.*' => ['string']]);
        $drop = (array) $validated['section'];

        if ($busy = self::inUse($drop)) {
            return back()->withErrors(['section' => 'In use, cannot be removed: '.implode(', ', $busy)]);
        }

        SectionCatalog::store(array_values(array_diff(SectionCatalog::defined(), $drop)));

        return back()->with('success', count($drop) === 1 ? $drop[0].' removed.' : count($drop).' sections removed.');
    }

    /**
     * Which of these sections still have a student or a CaPSA/TSA.
     *
     * @param  string[]  $labels
     * @return string[]
     */
    private static function inUse(array $labels): array
    {
        if ($labels === []) {
            return [];
        }

        $used = CapstoneExpert::whereNotNull('sections')->get()->pluck('sections')->flatten()
            ->merge(Student::whereNotNull('course')->whereNotNull('year_section')->distinct()->get(['course', 'year_section'])
                ->map(fn ($s) => Student::computeSectionLabel($s->course, $s->year_section)))
            ->all();

        return array_values(array_intersect($labels, $used));
    }

    /** This semester's enrolment rows, minus the teams that have passed Final Oral Defense. */
    private function unfinished(): Collection
    {
        $done = DefenseSchedule::where('defense_type', 'Final Oral Defense')
            ->whereIn('outcome', [DefenseOutcome::PASSED, DefenseOutcome::PASSED_WITH_REVISION])
            ->pluck('team_id');

        // Passing the last defense is not the end: the team stays until its Bluebook is cleared.
        $done = Team::whereIn('team_id', $done)->get()
            ->filter(fn (Team $team) => Bluebook::of($team)?->cleared ?? true)
            ->pluck('team_id');

        return StudentSchoolYear::where(fn ($q) => $q->whereNull('team_id')->orWhereNotIn('team_id', $done))->get();
    }
}
