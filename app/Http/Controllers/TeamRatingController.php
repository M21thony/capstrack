<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Team;
use App\Models\TeamRating;
use App\Models\TeamRatingForm;
use App\Services\Notifier;
use App\Support\PeerRatingRubric;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The student side of Team Rating: rate each teammate on every activity a
 * rating covers, saved as a draft pick by pick, then submitted all at once.
 *
 * A student only ever sees the scores they are giving. What teammates gave
 * them goes to the CaPSA/TSA alone.
 */
class TeamRatingController extends Controller
{
    /**
     * What the Team Rating tab shows: every rating for the team's section,
     * and for each one how far this student is with each teammate.
     *
     * @return array{forms: Collection, due: int}
     */
    public static function panel(Student $me, ?Team $team, Collection $members): array
    {
        if (! $team) {
            return ['forms' => collect(), 'due' => 0];
        }

        $section = $team->section();
        $mates = $members->reject(fn ($m) => (int) $m->student_id === (int) $me->student_id)->values();
        $forms = TeamRatingForm::with('items.milestone.deliverables', 'items.deliverable')->latest('id')->get()
            ->filter(fn (TeamRatingForm $f) => in_array($section, $f->sections, true))
            ->map(function (TeamRatingForm $f) use ($me, $mates) {
                $mine = TeamRating::where('form_id', $f->id)->where('rater_id', $me->student_id)->get();

                return (object) [
                    'form' => $f,
                    'status' => $f->status(),
                    'need' => count(PeerRatingRubric::CRITERIA) * $f->items->count(),
                    'submittedAt' => $mine->max('submitted_at'),
                    'mates' => $mates->map(fn ($m) => (object) [
                        'student' => $m,
                        'done' => self::picked($mine->where('ratee_id', $m->student_id)),
                        'totals' => $f->items->map(fn ($i) => $mine->where('ratee_id', $m->student_id)->firstWhere('item_id', $i->id)?->total),
                    ]),
                ];
            })
            ->sortBy(fn ($r) => [['open' => 0, 'scheduled' => 1, 'closed' => 2][$r->status], $r->form->dueOn()->timestamp])
            ->values();

        return ['forms' => $forms, 'due' => $forms->filter(fn ($r) => $r->status === 'open' && ! $r->submittedAt)->count()];
    }

    public function rate(TeamRatingForm $form, int $ratee)
    {
        [$me, $team, $mates] = $this->rater($form);
        $current = $mates->firstWhere('student_id', $ratee) ?? abort(404);
        $mine = TeamRating::where('form_id', $form->id)->where('rater_id', $me->student_id)->get();

        if ($mine->whereNotNull('submitted_at')->isNotEmpty() || ! $form->isOpen()) {
            return redirect()->route('student.team', ['tab' => 'rating']);
        }

        $picks = $form->items->mapWithKeys(fn ($item) => [$item->id => array_pad(
            $mine->where('ratee_id', $ratee)->firstWhere('item_id', $item->id)?->scores ?? [],
            count(PeerRatingRubric::CRITERIA),
            null,
        )]);

        return view('student.team-rating.rate', [
            'form' => $form,
            'mates' => $mates->map(fn ($m) => (object) [
                'student' => $m,
                'done' => self::picked($mine->where('ratee_id', $m->student_id)),
            ]),
            'current' => $current,
            'picks' => $picks,
            'need' => count(PeerRatingRubric::CRITERIA) * $form->items->count(),
            'savedAt' => $mine->where('ratee_id', $ratee)->max('updated_at'),
        ]);
    }

    /** Saves this teammate's picks as they are, finished or not. */
    public function draft(Request $request, TeamRatingForm $form, int $ratee): JsonResponse
    {
        [$me, $team, $mates] = $this->rater($form);
        abort_unless($mates->contains('student_id', $ratee), 404);
        $this->stillOpen($form, $me);

        $picks = $request->validate(['picks' => ['required', 'array']])['picks'];
        $n = count(PeerRatingRubric::CRITERIA);

        foreach ($form->items as $item) {
            $scores = array_pad(array_slice(array_values((array) ($picks[$item->id] ?? [])), 0, $n), $n, null);
            $scores = array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $scores);

            foreach ($scores as $v) {
                if ($v !== null && ! PeerRatingRubric::isScore($v)) {
                    throw ValidationException::withMessages(['picks' => 'Each score is 10, 7, 5, 3 or 1.']);
                }
            }

            TeamRating::updateOrCreate(
                ['item_id' => $item->id, 'rater_id' => $me->student_id, 'ratee_id' => $ratee],
                [
                    'form_id' => $form->id,
                    'team_id' => $team->team_id,
                    'scores' => $scores,
                    'total' => PeerRatingRubric::isComplete($scores) ? array_sum($scores) : null,
                ],
            );
        }

        return response()->json(['savedAt' => now()->format('g:i A')]);
    }

    /** Sends every teammate's scores to the CaPSA/TSA at once; nothing changes after. */
    public function submit(TeamRatingForm $form): JsonResponse
    {
        [$me, , $mates] = $this->rater($form);
        $this->stillOpen($form, $me);

        $mine = TeamRating::where('form_id', $form->id)->where('rater_id', $me->student_id)->get();
        $need = count(PeerRatingRubric::CRITERIA) * $form->items->count();
        $missing = $mates->filter(fn ($m) => self::picked($mine->where('ratee_id', $m->student_id)) < $need);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['ratings' => 'Rate '.$missing->map(fn ($m) => self::name($m))->join(', ', ' and ').' on every activity first.']);
        }

        TeamRating::whereIn('id', $mine->whereIn('ratee_id', $mates->pluck('student_id'))->pluck('id'))->update(['submitted_at' => now()]);

        Notifier::toExpert(
            (int) $form->capsa_id,
            'team_rating_submitted',
            self::name($me).' rated their teammates',
            '"'.$form->name.'"',
            route('capsa.team_management', ['tab' => 'team_ratings']),
        );

        return response()->json(['ok' => true]);
    }

    public static function name(Student $s): string
    {
        return trim($s->f_name.' '.$s->l_name);
    }

    /**
     * The signed-in student, their team, and the teammates they rate -
     * refused unless the rating is for their team's section.
     *
     * @return array{0: Student, 1: Team, 2: Collection<int, Student>}
     */
    private function rater(TeamRatingForm $form): array
    {
        $me = auth()->user();
        $team = Team::find(StudentTeam::idFor($me->student_id));
        abort_unless($team && in_array($team->section(), $form->sections, true), 403);

        $mates = Student::join('student_s_y', 'students.student_id', '=', 'student_s_y.student_id')
            ->leftJoin('student_roles', 'student_s_y.s_role_id', '=', 'student_roles.s_role_id')
            ->where('student_s_y.team_id', $team->team_id)
            ->where('students.student_id', '!=', $me->student_id)
            ->select('students.*', 'student_roles.s_role_name')
            ->orderByRaw('students.student_id = ? desc', [(int) $team->leader_id])
            ->orderBy('students.student_id')
            ->get();

        return [$me, $team, $mates];
    }

    private function stillOpen(TeamRatingForm $form, Student $me): void
    {
        if (! $form->isOpen()) {
            throw ValidationException::withMessages(['ratings' => 'This rating is not open.']);
        }

        if (TeamRating::where('form_id', $form->id)->where('rater_id', $me->student_id)->whereNotNull('submitted_at')->exists()) {
            throw ValidationException::withMessages(['ratings' => 'You already submitted this rating.']);
        }
    }

    /** How many of the scores a set of rows holds. */
    private static function picked(Collection $rows): int
    {
        return $rows->sum(fn ($r) => count(array_filter($r->scores ?? [], fn ($v) => $v !== null)));
    }
}
