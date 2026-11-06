<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Records that a page's tour, or one of its tabs', was finished or
     * skipped (key: the route name, or "route:tab"), so it opens by itself
     * only on the first visit.
     */
    public function page(Request $request): JsonResponse
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_.:-]+$/']])['key'];
        $account = auth()->user();
        $seen = $account->page_tours_seen ?? [];
        if (! in_array($key, $seen, true)) {
            $account->forceFill(['page_tours_seen' => [...$seen, $key]])->save();
        }

        return response()->json(['success' => true]);
    }
}
