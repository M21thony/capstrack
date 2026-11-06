<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The guided tour a CaPSA, Admin or Expert is walked through once - the same
 * one a student gets, with their own portal's steps.
 */
class PortalTourController extends Controller
{
    /**
     * Records that they are done with the tour, finished or skipped.
     *
     * Skipping counts: showing it again on the next page would be nagging.
     * The first time is the one kept, so a replay from the sidebar does not
     * rewrite it.
     */
    public function update(): JsonResponse
    {
        /** @var CapstoneExpert $expert */
        $expert = auth('expert')->user();

        if ($expert->tour_completed_at === null) {
            $expert->forceFill(['tour_completed_at' => now()])->save();
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
        $account = auth('expert')->user();
        $seen = $account->page_tours_seen ?? [];
        if (! in_array($key, $seen, true)) {
            $account->forceFill(['page_tours_seen' => [...$seen, $key]])->save();
        }

        return response()->json(['success' => true]);
    }
}
