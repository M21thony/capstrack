<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\DefenseSchedule;
use App\Services\CapaGo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * An adviser (CaPA) saying whether their team is ready for its Title, Pre
 * Oral or Final Oral Defense date. Not ready marks it Cancelled, keeping the
 * date on record; the CaPSA gives the team a new one.
 */
class DefenseGoController extends Controller
{
    public function update(Request $request, DefenseSchedule $defense, CapaGo $go): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'answer' => ['required', Rule::in([CapaGo::GO, CapaGo::NOT_READY])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $expert = auth('expert')->user();

        abort_unless($go->capaOf($defense) === (int) $expert->expert_id, 403);

        if ($why = $go->cannotAnswer($defense)) {
            return $this->answered($request, $why, false);
        }

        $team = $defense->team?->team_name ?? 'The team';
        $go->answer($defense, $validated['answer'], $validated['note'] ?? null, $expert);

        return $this->answered($request, $validated['answer'] === CapaGo::GO
            ? $team.' has your go for the '.$defense->defense_type.'.'
            : $team.'\'s '.$defense->defense_type.' is cancelled. The CaPSA will set a new date.');
    }

    /** Back to the To do it was answered from, or the answer itself for the To do's dialog. */
    private function answered(Request $request, string $message, bool $done = true): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => $done, 'message' => $message], $done ? 200 : 422);
        }

        return redirect()->route('instructor.teams', ['tab' => 'todo'])->with('status', $message);
    }
}
