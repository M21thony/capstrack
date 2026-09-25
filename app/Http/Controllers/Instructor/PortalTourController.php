<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;

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
}
