<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

/**
 * The welcome tour a new account is walked through once.
 */
class PortalTourController extends Controller
{
    /**
     * Records that the student is done with the tour, finished or skipped.
     *
     * Both count as done: someone who skips has said they would rather find
     * their own way, and showing it again on the next page would be nagging.
     * The first time is the one kept, so replaying it from the sidebar later
     * does not rewrite when they first got through it.
     */
    public function update(): JsonResponse
    {
        /** @var Student $student */
        $student = auth()->user();

        if ($student->tutorial_completed_at === null) {
            $student->forceFill(['tutorial_completed_at' => now()])->save();
        }

        return response()->json(['success' => true]);
    }
}
