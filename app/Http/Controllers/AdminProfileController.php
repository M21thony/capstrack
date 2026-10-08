<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use App\Models\Setting;
use App\Models\Student;
use App\Support\SectionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public static function getAllAvailableSections(): array
    {
        $students = Student::whereNotNull('course')
            ->whereNotNull('year_section')
            ->get();

        $sections = [];
        foreach ($students as $student) {
            $sectionLabel = Student::computeSectionLabel($student->course, $student->year_section);
            if ($sectionLabel) {
                $sections[] = $sectionLabel;
            }
        }

        // What an Admin has written down counts as much as what a student is
        // enrolled in - before a term starts there are no students, and a
        // section nobody is in yet is still a section somebody covers.
        $sections = array_merge($sections, SectionCatalog::defined());

        return SectionCatalog::tidy($sections);
    }

    public function index(): View
    {
        $admin = auth('expert')->user();

        $admin->load('expertise');

        $sectionList = $admin->sections ?? [];

        $allAvailableSections = self::getAllAvailableSections();

        $schoolYear = Setting::get('current_school_year');

        // Research areas per side: each expert role they hold, and panels -
        // the same lists their expert profile panel edits.
        $heldRoles = $admin->roles()->pluck('role_name')->all();
        $expertSides = collect(ExpertExpertise::SIDES)
            ->filter(fn ($label, $side) => $side === 'panel' || in_array($side, $heldRoles, true))
            ->all();
        $expertiseBySide = collect($expertSides)->map(fn ($label, $side) => $admin->expertiseFor($side)->all())->all();
        $expertiseList = $admin->allExpertise()->all();

        return view('capsa.profile', compact('admin', 'sectionList', 'allAvailableSections', 'schoolYear', 'expertiseList', 'expertSides', 'expertiseBySide'));
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = auth('expert')->user();

        // Sized to the columns: anything longer was a database error (a 500)
        // rather than a message on the form.
        $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'credentials' => ['nullable', 'string', 'max:50'],
        ]);

        // Sections and the school year are the Admin's to set (Expert
        // Management and Settings); this page used to let a CaPSA change their
        // own, and a save with none ticked wiped them.

        $data = [
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name'),
            'last_name' => $request->input('last_name'),
            'suffix' => $request->input('suffix'),
            'credentials' => $request->input('credentials'),
        ];

        // The degree is read from the credentials written after their name.
        if ($data['credentials'] !== $admin->credentials) {
            $data += CapstoneExpert::degreeFromCredentials($data['credentials']);
        }

        CapstoneExpert::where('expert_id', $admin->expert_id)->update($data);

        // Research areas, one list per side. Saving the name or sections must
        // not flatten them, so the per-side lists are what is sent and kept;
        // a page that only knows one list still saves it for every side.
        $clean = fn ($fields) => array_values(array_unique(array_filter(array_map(fn ($f) => trim((string) $f), (array) $fields))));

        if ($request->has('expertise_by_side')) {
            $bySide = $request->input('expertise_by_side');
            $bySide = is_string($bySide) ? (json_decode($bySide, true) ?? []) : (array) $bySide;
            $bySide = collect($bySide)
                ->filter(fn ($fields, $side) => array_key_exists($side, ExpertExpertise::SIDES))
                ->map($clean);
        } elseif ($request->has('expertise')) {
            $flat = $request->input('expertise', []);
            $bySide = collect(['' => $clean(is_string($flat) ? (json_decode($flat, true) ?? []) : $flat)]);
        } else {
            $bySide = null;
        }

        if ($bySide !== null) {
            ExpertExpertise::where('expert_id', $admin->expert_id)->delete();

            foreach ($bySide as $side => $fields) {
                foreach ($fields as $field) {
                    ExpertExpertise::create([
                        'expert_id' => $admin->expert_id,
                        'expertise_field' => $field,
                        'side' => $side === '' ? null : $side,
                    ]);
                }
            }
        }

        // Back to the tab the save was made from.
        $tab = in_array($request->input('tab'), ['details', 'research'], true) ? $request->input('tab') : null;

        return redirect()->route('capsa.profile', array_filter(['tab' => $tab]))->with('status', 'Profile updated successfully.');
    }
}
