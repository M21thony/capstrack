<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * The defense fees and who has paid them, for Financial Records.
 *
 * Payment is its own record, not a checklist line: a fee is paid once per
 * stage, a redefense needs no second payment, and Pre-proposal Defense has no
 * fee at all. Scheduling reads the same records (DefenseBoard::paidTeamIds).
 *
 * Each member uploads their own receipt to their team leader. Once every
 * member has, the leader sends them to the CaPSA together, who verifies each
 * or sends it back. Only the latest upload per member per fee counts.
 */
class FeeBoard
{
    public function __construct(private DefenseBoard $board) {}

    /** @return array<string, mixed> what the CaPSA page draws */
    public function forCapsa(CapstoneExpert $capsa): array
    {
        ['teams' => $teams, 'sections' => $sections] = $this->board->teamsFor($capsa);
        $programs = $capsa->handledPrograms();

        $fees = ExpensePost::whereIn('defense_type', DefenseBoard::PAID_STAGES)
            ->when($programs !== [], fn ($q) => $q->whereIn('course', $programs))
            ->get()
            ->sortBy(fn (ExpensePost $p) => array_search($p->defense_type, DefenseBoard::STAGES, true))
            ->values();

        $teamRows = $teams->map(fn (Team $team) => [
            'id' => (int) $team->team_id,
            'name' => $team->team_name,
            'section' => $sections[$team->team_id],
            'leader' => $team->leader_id ? (int) $team->leader_id : null,
            'members' => $this->members($team->schoolYears),
        ])->values();

        return [
            'fees' => $fees->map(fn (ExpensePost $p) => $this->fee($p))->values(),
            'teams' => $teamRows,
            'receipts' => $this->receipts($fees->pluck('id'), $teamRows->pluck('members')->flatten(1)->pluck('id')),
            'sections' => $capsa->sections ?? [],
        ];
    }

    /** @return array<string, mixed> what the student page draws */
    public function forStudent(Student $student): array
    {
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->latest('s_y_id')->first()
            ?? StudentSchoolYear::where('student_id', $student->student_id)->first();
        $team = $sy?->team_id ? Team::find($sy->team_id) : null;
        $program = Student::mapCourseToProgram($student->course ?? '') ?? $sy?->program;

        $fees = ExpensePost::whereIn('defense_type', DefenseBoard::PAID_STAGES)
            ->where('is_active', true)
            ->when($program, fn ($q) => $q->where('course', $program))
            ->get()
            ->sortBy(fn (ExpensePost $p) => array_search($p->defense_type, DefenseBoard::STAGES, true))
            ->values();

        $members = $team
            ? $this->members(StudentSchoolYear::where('team_id', $team->team_id)->with('student')->get())
            : collect([['id' => (int) $student->student_id, 'name' => $this->nameOf($student)]]);

        return [
            'me' => (int) $student->student_id,
            'team' => $team ? ['name' => $team->team_name, 'leader' => $team->leader_id ? (int) $team->leader_id : null] : null,
            'is_leader' => (bool) $team && (int) $team->leader_id === (int) $student->student_id,
            'section' => $team ? (app(SectionScope::class)->sectionFor($team) ?: null) : null,
            'fees' => $fees->map(fn (ExpensePost $p) => $this->fee($p))->values(),
            'members' => $members,
            'receipts' => $this->receipts($fees->pluck('id'), $members->pluck('id')),
            'can_submit' => (bool) $team && ($team->hasSubmittedPreProposals() || $team->is_confirmed),
        ];
    }

    private function fee(ExpensePost $post): array
    {
        return [
            'id' => (int) $post->id,
            'stage' => $post->defense_type,
            'amount' => (float) $post->amount,
            'program' => $post->course,
            'note' => $post->description,
        ];
    }

    public function members(Collection $schoolYears): Collection
    {
        return $schoolYears->filter(fn ($sy) => $sy->student)
            ->map(fn ($sy) => ['id' => (int) $sy->student_id, 'name' => $this->nameOf($sy->student)])
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function nameOf(Student $student): string
    {
        return trim(($student->f_name ?? '').' '.($student->l_name ?? '')) ?: ($student->email ?? 'Student');
    }

    /**
     * The latest receipt per member per fee, keyed "feeId:studentId".
     *
     * @return array<string, array<string, mixed>>
     */
    public function receipts(Collection $feeIds, Collection $studentIds): array
    {
        if ($feeIds->isEmpty() || $studentIds->isEmpty()) {
            return [];
        }

        $latest = FinancialRecord::whereIn('expense_post_id', $feeIds)
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('id')
            ->get()
            ->unique(fn (FinancialRecord $r) => $r->expense_post_id.':'.$r->student_id);

        // The same OR number on somebody else's receipt: one payment claimed
        // twice, or a typo. Said to the CaPSA, not refused - a parent paying
        // for two students on one receipt is real.
        $numbers = $latest->pluck('receipt_number')->filter()->unique()->values();
        $usedBy = $numbers->isEmpty() ? collect() : FinancialRecord::whereIn('receipt_number', $numbers)
            ->where(fn ($q) => $q->whereNull('f_record_status')->orWhere('f_record_status', '!=', 'rejected'))
            ->with('student:student_id,f_name,l_name')
            ->get(['id', 'student_id', 'receipt_number'])
            ->groupBy('receipt_number');

        return $latest
            ->mapWithKeys(fn (FinancialRecord $r) => [$r->expense_post_id.':'.$r->student_id => [
                'id' => (int) $r->id,
                'status' => FinancialRecord::statusKey($r->f_record_status),
                'img' => $r->receipt_image ? asset('storage/'.$r->receipt_image) : null,
                'at' => $r->uploaded_at?->format('M j, Y'),
                'on' => $r->verified_at?->format('M j, Y'),
                'reason' => $r->rejection_reason,
                'or' => $r->receipt_number,
                'or_also' => $r->receipt_number
                    ? $usedBy->get($r->receipt_number, collect())->reject(fn ($o) => $o->id === $r->id)
                        ->map(fn ($o) => trim(($o->student?->f_name ?? '').' '.($o->student?->l_name ?? '')) ?: 'another student')->unique()->values()->all()
                    : [],
            ]])
            ->all();
    }
}
