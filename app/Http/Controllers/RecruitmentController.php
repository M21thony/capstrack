<?php

namespace App\Http\Controllers;

use App\Models\Recruitment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecruitmentController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'recruitment_goal' => ['required', Rule::in(['build_a_team', 'find_a_team'])],
            'looking_for' => ['nullable', 'string', 'max:500'],
            'introduction' => ['nullable', 'string', 'max:500'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'max:50'],
        ]);

        $student = $request->user();

        $recruitment = Recruitment::create([
            'student_id' => $student->student_id,
            'recruitment_goal' => $validated['recruitment_goal'],
            'roles' => $validated['roles'],
            'looking_for' => $validated['looking_for'] ?? null,
            'introduction' => $validated['introduction'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Recruitment post published.',
                'recruitment' => $recruitment,
            ], 201);
        }

        return redirect()
            ->back()
            ->with('status', 'Recruitment post published!');
    }

    public function destroy(Request $request, Recruitment $recruitment): RedirectResponse
    {
        $student = $request->user();

        if ($recruitment->student_id !== $student->student_id) {
            abort(403);
        }

        $recruitment->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Recruitment post removed.',
            ]);
        }

        return redirect()
            ->back()
            ->with('status', 'Recruitment post removed.');
    }
}
