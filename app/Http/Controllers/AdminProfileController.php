<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use App\Models\Student;
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
        $sections = array_merge($sections, \App\Support\SectionCatalog::defined());

        return \App\Support\SectionCatalog::tidy($sections);
    }

    public function index(): View
    {
        $admin = auth('expert')->user();

        $admin->load('expertise');

        $sectionList = $admin->sections ?? [];

        $allAvailableSections = self::getAllAvailableSections();

        $schoolYear = $admin->school_year;

        $expertiseList = $admin->expertise->pluck('expertise_field')->toArray();

        return view('capsa.profile', compact('admin', 'sectionList', 'allAvailableSections', 'schoolYear', 'expertiseList'));
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = auth('expert')->user();

        $sections = $request->input('sections', []);

        $data = [
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name'),
            'last_name' => $request->input('last_name'),
            'suffix' => $request->input('suffix'),
            'credentials' => $request->input('credentials'),
            'sections' => ! empty($sections) ? json_encode($sections) : null,
        ];

        if ($request->filled('school_year')) {
            $data['school_year'] = $request->input('school_year');
        }

        CapstoneExpert::where('expert_id', $admin->expert_id)->update($data);

        // Sync expertise
        $expertise = $request->input('expertise', []);

        if (is_string($expertise)) {
            $expertise = json_decode($expertise, true) ?? [];
        }

        $expertise = array_values(array_unique(array_filter(array_map('trim', (array) $expertise))));

        ExpertExpertise::where('expert_id', $admin->expert_id)->delete();

        foreach ($expertise as $field) {
            ExpertExpertise::create([
                'expert_id' => $admin->expert_id,
                'expertise_field' => $field,
            ]);
        }

        return redirect()->route('capsa.profile')->with('status', 'Profile updated successfully.');
    }
}
