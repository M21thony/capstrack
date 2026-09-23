<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Setting;
use App\Support\SectionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings', [
            'currentSchoolYear' => Setting::get('current_school_year'),
            'sections' => SectionCatalog::defined(),
            'programs' => CapstoneExpert::PROGRAMS,
            'years' => SectionCatalog::CAPSTONE_YEARS,
            'letters' => str_split(SectionCatalog::LETTERS),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // e.g. "2026-2027" - matches the format already used elsewhere
            // (CapstoneExpert::getResolvedSchoolYearAttribute()).
            'current_school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        Setting::set('current_school_year', $validated['current_school_year']);

        return back()->with('success', 'Current school year updated.');
    }

    /**
     * Write down a run of sections - programs and a range of letters, for
     * each capstone year level (3rd and 4th).
     *
     * The Admin used to pick year levels too. Capstone only ever runs in 3rd
     * and 4th year, so that choice could only be got wrong.
     *
     * A range rather than one at a time because a department does not run
     * section D without running A, B and C, and typing eight labels by hand
     * is eight chances to typo one into a section nobody covers.
     */
    public function storeSections(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'programs' => ['required', 'array', 'min:1'],
            'programs.*' => ['string', 'in:'.implode(',', CapstoneExpert::PROGRAMS)],
            'from' => ['required', 'string', 'size:1', 'alpha'],
            'to' => ['required', 'string', 'size:1', 'alpha'],
        ], [
            'programs.required' => 'Choose at least one program.',
        ]);

        $made = [];

        foreach ($validated['programs'] as $program) {
            foreach (SectionCatalog::CAPSTONE_YEARS as $year) {
                $made = array_merge($made, SectionCatalog::range($program, (int) $year, $validated['from'], $validated['to']));
            }
        }

        if ($made === []) {
            return back()->withErrors(['from' => 'That range produced no sections.']);
        }

        $before = SectionCatalog::defined();
        SectionCatalog::store(array_merge($before, $made));

        $added = count(SectionCatalog::defined()) - count($before);

        return back()->with('success', $added === 0
            ? 'Those sections were already listed.'
            : $added.' section'.($added === 1 ? '' : 's').' added.');
    }

    /** Remove one section from the list. It says nothing about the students in it. */
    public function destroySection(Request $request): RedirectResponse
    {
        $validated = $request->validate(['section' => ['required', 'string']]);

        SectionCatalog::store(array_values(array_filter(
            SectionCatalog::defined(),
            fn ($s) => $s !== $validated['section']
        )));

        return back()->with('success', $validated['section'].' removed.');
    }
}
