<?php

namespace App\Http\Controllers;

use App\Models\CapstoneDocument;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Student;
use App\Models\Team;
use App\Services\Notifier;
use App\Support\Bluebook;
use App\Support\MilestoneProgress;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** The Dean's Office checks each team's Bluebook requirements, then clears the team. */
class AdminBluebookController extends Controller
{
    public const TABS = [
        Bluebook::NOT_SUBMITTED => 'Not Submitted',
        Bluebook::WAITING => 'Waiting for You',
        Bluebook::SENT_BACK => 'Sent Back',
        Bluebook::CLEARED => 'Cleared',
    ];

    public function index(Request $request): View
    {
        $all = Bluebook::forDean();
        $status = (string) $request->query('status');
        $status = isset(self::TABS[$status]) ? $status : Bluebook::WAITING;
        $q = mb_strtolower(trim((string) $request->query('q')));

        $rows = $all->where('bluebook.status', $status)
            ->when($request->query('program'), fn ($c, $p) => $c->where('program', $p))
            ->when($request->query('section'), fn ($c, $s) => $c->where('section', $s))
            ->when($q !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r->team->team_name.' '.$r->team->project_title), $q)));

        return view('admin.bluebook', [
            'status' => $status,
            'rows' => $rows,
            'counts' => $all->countBy('bluebook.status'),
            'programs' => $all->pluck('program')->filter()->unique()->sort()->values(),
            'sections' => $all->pluck('section')->filter()->unique()->sort()->values(),
        ]);
    }

    public function show(Request $request, Team $team): View
    {
        $row = $this->row($team);
        $lines = $row->bluebook->lines;
        $line = $lines->first(fn ($l) => (int) $l->item->id === (int) $request->query('line'))
            ?? $lines->first(fn ($l) => $l->waiting_on === MilestoneProgress::WAITING_ON_DEAN)
            ?? $lines->first();

        return view('admin.bluebook', [
            'row' => $row,
            'line' => $line,
            'uploader' => $line?->file ? Student::find($line->file->student_id) : null,
        ]);
    }

    /** Accept a line, or send it back with a reason the team is told. */
    public function check(Request $request, Team $team, Deliverable $deliverable): RedirectResponse
    {
        $line = $this->line($team, $deliverable);
        abort_unless($line->file, 422);

        $data = $request->validate([
            'status' => ['required', 'in:'.DeliverableCheck::COMPLETE.','.DeliverableCheck::SENT_BACK],
            'note' => ['nullable', 'required_if:status,'.DeliverableCheck::SENT_BACK, 'string', 'max:1000'],
        ], ['note.required_if' => 'Write why it is sent back.']);
        $back = $data['status'] === DeliverableCheck::SENT_BACK;

        DeliverableCheck::updateOrCreate(
            ['deliverable_id' => $deliverable->id, 'team_id' => $team->team_id],
            [
                'status' => $data['status'],
                'note' => $back ? trim($data['note']) : null,
                'file_id' => $line->file_id,
                'checked_by' => auth('expert')->id(),
                'checked_at' => now(),
            ],
        );

        if ($back) {
            Notifier::toStudents(
                $team->members,
                'paper_sent_back',
                'Sent back: '.$deliverable->title,
                'The Dean\'s Office sent back <strong>'.e($deliverable->title).'</strong>: “'.e(trim($data['note'])).'” Send a corrected copy.',
                route('student.bluebook'),
                ['team_id' => $team->team_id, 'deliverable_id' => $deliverable->id],
            );
        }

        return redirect()->route('admin.bluebook.show', [$team, 'line' => $deliverable->id]);
    }

    public function undo(Team $team, Deliverable $deliverable): RedirectResponse
    {
        $this->line($team, $deliverable);
        DeliverableCheck::where('deliverable_id', $deliverable->id)->where('team_id', $team->team_id)->delete();

        return redirect()->route('admin.bluebook.show', [$team, 'line' => $deliverable->id]);
    }

    /** Every line accepted: close the clearance line. Its check is the slip. */
    public function clear(Team $team): RedirectResponse
    {
        $bluebook = $this->row($team)->bluebook;
        abort_if($bluebook->cleared || ! $bluebook->clearance || $bluebook->lines->isEmpty() || $bluebook->accepted < $bluebook->lines->count(), 422);

        DeliverableCheck::updateOrCreate(
            ['deliverable_id' => $bluebook->clearance->item->id, 'team_id' => $team->team_id],
            ['status' => DeliverableCheck::COMPLETE, 'note' => null, 'file_id' => null, 'checked_by' => auth('expert')->id(), 'checked_at' => now()],
        );

        return redirect()->route('admin.bluebook.show', [$team, 'slip' => 1]);
    }

    public function slip(Team $team): Response
    {
        $row = $this->row($team);
        abort_unless($row->bluebook->cleared, 404);

        return Pdf::loadView('admin.bluebook-slip', ['row' => $row, 'pdf' => true])
            ->setPaper('a4')
            ->download($row->bluebook->slip_no.'.pdf');
    }

    public function file(CapstoneDocument $document): Response
    {
        abort_unless($document->deliverable?->kind === Deliverable::BLUEBOOK, 404);

        return Bluebook::fileResponse($document);
    }

    private function row(Team $team): object
    {
        return Bluebook::forDean($team->team_id)->first() ?? abort(404);
    }

    /** One line of a team that is not cleared yet: a cleared slip is final. */
    private function line(Team $team, Deliverable $deliverable): object
    {
        $bluebook = $this->row($team)->bluebook;
        abort_if($bluebook->cleared, 422);

        return $bluebook->lines->first(fn ($l) => (int) $l->item->id === (int) $deliverable->id) ?? abort(404);
    }
}
