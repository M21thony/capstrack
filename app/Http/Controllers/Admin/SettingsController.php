<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings', [
            'currentSchoolYear' => Setting::get('current_school_year'),
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
}
