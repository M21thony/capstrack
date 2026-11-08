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
            'years' => [SectionCatalog::STARTING_YEAR],
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
     * Write down a run of sections - programs and a range of letters - for
     * 3rd year, where capstone starts.
     *
     * The Admin picks no year. 4th-year sections are not made here: made for
     * both years at once, every CaPSA looked like they ran 3rd and 4th year
     * together. They come from the student list once students are in them.
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
            $made = array_merge($made, SectionCatalog::range($program, SectionCatalog::STARTING_YEAR, $validated['from'], $validated['to']));
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

    /** Remove one or more sections from the list. It says nothing about the students in them. */
    public function destroySection(Request $request): RedirectResponse
    {
        $validated = $request->validate(['section' => ['required'], 'section.*' => ['string']]);
        $drop = (array) $validated['section'];

        SectionCatalog::store(array_values(array_diff(SectionCatalog::defined(), $drop)));

        return back()->with('success', count($drop) === 1 ? $drop[0].' removed.' : count($drop).' sections removed.');
    }
}
