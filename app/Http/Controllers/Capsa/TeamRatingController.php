<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\TeamRatingForm;
use App\Models\TeamRatingItem;
use App\Support\PeerRatingRubric;
use App\Support\TableWorkbook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A CaPSA/TSA's named team ratings: create one, add activities while it waits to
 * open, move its due date, open or close it early, remind, and export.
 * The ratings themselves are given on the student side.
 */
class TeamRatingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $capsa = auth('expert')->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['string', Rule::in($capsa->sections ?? [])],
            'follows_checklist' => ['required', 'boolean'],
            'opens_on' => ['exclude_if:follows_checklist,true', 'required', 'date', 'after_or_equal:today'],
            'days_to_rate' => ['required', 'integer', 'min:1', 'max:30'],
            ...$this->activityRules(),
        ]);

        $items = $this->activities($request, $capsa->expert_id);

        $form = new TeamRatingForm([
            'capsa_id' => $capsa->expert_id,
            'name' => trim($data['name']),
            'sections' => array_values(array_unique($data['sections'])),
            'follows_checklist' => $data['follows_checklist'],
            'days_to_rate' => $data['days_to_rate'],
        ]);
        $form->setRelation('items', collect($items)->map(fn ($i) => new TeamRatingItem($i)));
        $form->created_at = now();

        if ($form->follows_checklist && $form->items->map->dueOn()->filter()->isEmpty()) {
            throw ValidationException::withMessages(['milestones' => 'None of the picked activities has a due date on the checklist. Pick a dated one, or choose the date yourself.']);
        }

        if (! $form->follows_checklist) {
            $form->opens_on = $data['opens_on'];
            $form->due_on = $form->opens_on->copy()->addDays($form->days_to_rate);
        } else {
            // Stored too, so the rating still has dates if its milestones lose theirs.
            [$form->opens_on, $form->due_on] = $form->window();
        }

        $form->save();
        $form->items()->createMany($items);

        return response()->json(['id' => $form->id], 201);
    }

    /** More activities, only while it has not opened: the window and the activities students see stay fixed once it has. */
    public function addItems(Request $request, TeamRatingForm $form): JsonResponse
    {
        $this->own($form);
        $request->validate($this->activityRules());

        if ($form->status() !== 'scheduled') {
            abort(422, 'Activities can only be added before the rating opens.');
        }

        $form->items()->createMany($this->activities($request, $form->capsa_id));

        return response()->json(['ok' => true]);
    }

    /** Change its due date, open it now, or close it early. */
    public function update(Request $request, TeamRatingForm $form): JsonResponse
    {
        $this->own($form);
        $data = $request->validate([
            'action' => ['required', Rule::in(['due', 'open_now', 'close'])],
            'due_on' => ['required_if:action,due', 'date', 'after_or_equal:today'],
        ]);

        if ($form->closed_at) {
            abort(422, 'This rating is closed.');
        }

        if ($data['action'] === 'due') {
            $due = $request->date('due_on');
            $form->update(['opens_on' => $form->opensOn()->min($due), 'due_on' => $due, 'date_overridden' => true]);
        } elseif ($data['action'] === 'open_now') {
            abort_unless($form->status() === 'scheduled', 422, 'This rating is already open.');
            $form->update(['opens_on' => today(), 'due_on' => today()->addDays($form->days_to_rate), 'date_overridden' => true]);
            $form->announceOpen();
        } else {
            $form->update(['closed_at' => now()]);
        }

        return response()->json(['status' => $form->fresh()->status()]);
    }

    public function remind(TeamRatingForm $form): JsonResponse
    {
        $this->own($form);
        abort_unless($form->isOpen(), 422, 'Only an open rating can be reminded.');

        return response()->json(['reminded' => $form->remind()]);
    }

    /** Every rating of this CaPSA/TSA, one sheet each, laid out like FINALS-PARTICIPATION.xlsx. */
    public function export(): StreamedResponse
    {
        $forms = TeamRatingForm::with('items.milestone.deliverables', 'items.deliverable')
            ->where('capsa_id', auth('expert')->user()->expert_id)->orderBy('id')->get();
        $book = null;

        foreach ($forms as $form) {
            $results = $form->results();
            $done = $form->submittedRaterIds();
            $rows = [];

            foreach ($form->teams() as $t) {
                foreach ($t['members'] as $s) {
                    $mine = $results[$s->student_id] ?? [];
                    $pct = TeamRatingForm::percentFor($mine);
                    $rows[] = [
                        $t['team']->team_name,
                        $t['section'],
                        trim($s->f_name.' '.$s->l_name),
                        ...$form->items->map(fn ($i) => isset($mine[$i->id]) ? (string) $mine[$i->id]['avg'] : '—')->all(),
                        $pct === null ? '—' : $pct.'%',
                        in_array((int) $s->student_id, $done, true) ? 'Yes' : 'No',
                    ];
                }
            }

            $sheet = TableWorkbook::build(
                $form->name,
                implode(', ', $form->sections).' · '.$form->opensOn()->format('M j').' to '.$form->dueOn()->format('M j, Y').' · each activity out of '.PeerRatingRubric::MAX,
                ['Team', 'Section', 'Student', ...$form->items->pluck('label')->all(), 'Rating %', 'Submitted'],
                $rows,
            );
            $book ? $book->addExternalSheet($sheet->getActiveSheet()) : $book = $sheet;
        }

        $book ??= TableWorkbook::build('Team Ratings', 'No ratings yet', ['Team', 'Section', 'Student'], []);

        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            'team-ratings-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function own(TeamRatingForm $form): void
    {
        abort_unless((int) $form->capsa_id === (int) auth('expert')->user()->expert_id, 403);
    }

    private function activityRules(): array
    {
        return [
            'milestones' => ['array'],
            'milestones.*' => ['integer'],
            'deliverables' => ['array'],
            'deliverables.*' => ['integer'],
            'own' => ['array'],
            'own.*' => ['string', 'max:120'],
        ];
    }

    /**
     * The picked activities as item rows. Each must be on this CaPSA/TSA's own
     * checklist, and in no rating yet - a milestone counts as used when it or
     * any of its deliverables is, so nothing is rated twice.
     *
     * @return list<array{label: string, checklist_milestone_id: ?int, deliverable_id: ?int}>
     */
    private function activities(Request $request, int $capsaId): array
    {
        $milestones = ChecklistMilestone::with('checklist', 'deliverables')->whereIn('id', $request->input('milestones', []))->get();
        $deliverables = Deliverable::with('milestone.checklist')->whereIn('id', $request->input('deliverables', []))->get();
        $own = collect($request->input('own', []))->map(fn ($l) => trim((string) $l))->filter()->unique();

        $mine = fn ($checklist) => $checklist && (int) $checklist->capsa_id === $capsaId;
        abort_if($milestones->count() !== count(array_unique($request->input('milestones', []))) || $milestones->contains(fn ($m) => ! $mine($m->checklist)), 403);
        abort_if($deliverables->count() !== count(array_unique($request->input('deliverables', []))) || $deliverables->contains(fn ($d) => ! $mine($d->milestone?->checklist)), 403);

        if ($milestones->isEmpty() && $deliverables->isEmpty() && $own->isEmpty()) {
            throw ValidationException::withMessages(['milestones' => 'Pick at least one activity.']);
        }

        $milestoneIds = $milestones->pluck('id')->merge($deliverables->pluck('checklist_milestone_id'));
        $deliverableIds = $deliverables->pluck('id')->merge($milestones->flatMap(fn ($m) => $m->deliverables->pluck('id')));
        $used = TeamRatingItem::whereIn('checklist_milestone_id', $milestoneIds)->orWhereIn('deliverable_id', $deliverableIds)->exists();

        if ($used || $deliverables->contains(fn ($d) => $milestones->contains('id', $d->checklist_milestone_id))) {
            throw ValidationException::withMessages(['milestones' => 'An activity you picked is already in a team rating.']);
        }

        return [
            ...$milestones->map(fn ($m) => ['label' => $m->name, 'checklist_milestone_id' => $m->id, 'deliverable_id' => null]),
            ...$deliverables->map(fn ($d) => ['label' => $d->title, 'checklist_milestone_id' => null, 'deliverable_id' => $d->id]),
            ...$own->map(fn ($l) => ['label' => $l, 'checklist_milestone_id' => null, 'deliverable_id' => null]),
        ];
    }
}
