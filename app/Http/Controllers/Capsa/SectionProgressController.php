<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Support\ChecklistTracker;
use Illuminate\Http\JsonResponse;

/** One of the CaPSA's sections, for the tracker's section tabs. */
class SectionProgressController extends Controller
{
    public function show(string $section, ChecklistTracker $tracker): JsonResponse
    {
        abort_unless(in_array($section, auth('expert')->user()->sections ?? [], true), 403);

        return response()->json($tracker->section($section));
    }
}
