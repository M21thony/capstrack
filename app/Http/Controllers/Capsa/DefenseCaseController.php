<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\ExpertAwayDate;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\CapaGo;
use App\Support\DefenseBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The smaller things the Defenses page records about a team or a panelist:
 * a panelist's days away, a member who is a returnee, and the CaPA's go when
 * the CaPA gave it to the CaPSA in person.
 */
class DefenseCaseController extends Controller
{
    public function __construct(private DefenseBoard $board, private CapaGo $go) {}

    /** POST /panel_assignment/away - a panelist is away on these days and keeps the seat. */
    public function storeAway(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expert_id' => ['required', 'integer', 'exists:capstone_experts,expert_id'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', Rule::in(ExpertAwayDate::REASONS)],
        ]);

        $away = ExpertAwayDate::create($validated + ['created_by' => $this->capsa()->expert_id]);

        // Defenses already on those days stay put; the page flags them.
        $flagged = DefenseSchedule::whereNull('outcome')
            ->whereNotNull('schedule_date')
            ->whereDate('schedule_date', '>=', $away->starts_on->toDateString())
            ->whereDate('schedule_date', '<=', $away->ends_on->toDateString())
            ->whereHas('panels', fn ($q) => $q->where('expert_id', $away->expert_id))
            ->count();

        return response()->json([
            'success' => true,
            'message' => CapstoneExpert::find($away->expert_id)?->name.' is away and keeps the seat.'
                .($flagged ? ' '.$flagged.' scheduled '.($flagged === 1 ? 'defense is' : 'defenses are').' on those days.' : ''),
            'data' => ['id' => $away->id, 'flagged' => $flagged],
        ], 201);
    }

    /** DELETE /panel_assignment/away/{away} - they are not away after all. */
    public function destroyAway(ExpertAwayDate $away): JsonResponse
    {
        $away->delete();

        return response()->json(['success' => true, 'message' => 'Away dates removed.']);
    }

    /** PATCH /panel_assignment/students/{student}/returnee - mark or unmark a returnee. */
    public function returnee(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate(['is_returnee' => ['required', 'boolean']]);
        $team = Team::whereIn('team_id', StudentSchoolYear::where('student_id', $student->student_id)->pluck('team_id'))->first();

        if (! $team || ! $this->board->covers($this->capsa(), $team)) {
            return response()->json(['success' => false, 'message' => 'That student is not in your sections.'], 403);
        }

        $student->update(['is_returnee' => $validated['is_returnee']]);

        return response()->json(['success' => true, 'message' => trim($student->f_name.' '.$student->l_name).($validated['is_returnee'] ? ' is marked as a returnee.' : ' is no longer marked as a returnee.')]);
    }

    /** POST /panel_assignment/capa/{defense} - the CaPA's answer, as they gave it to the CaPSA. */
    public function capa(Request $request, DefenseSchedule $defense): JsonResponse
    {
        $validated = $request->validate([
            'answer' => ['required', Rule::in([CapaGo::GO, CapaGo::NOT_READY])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $defense->loadMissing('team');

        if (! $defense->team || ! $this->board->covers($this->capsa(), $defense->team)) {
            return response()->json(['success' => false, 'message' => 'That defense is not in your sections.'], 403);
        }

        if ($why = $this->go->cannotAnswer($defense)) {
            return response()->json(['success' => false, 'message' => $why], 422);
        }

        $this->go->answer($defense, $validated['answer'], $validated['note'] ?? null, $this->capsa());

        return response()->json(['success' => true, 'message' => $validated['answer'] === CapaGo::GO
            ? 'Team '.$defense->team->team_name.' has the go.'
            : 'Team '.$defense->team->team_name.'\'s '.$defense->defense_type.' is cancelled. They wait under Ready to schedule.']);
    }

    private function capsa(): CapstoneExpert
    {
        return auth('expert')->user();
    }
}
