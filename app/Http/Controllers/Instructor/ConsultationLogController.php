<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneDocument;
use App\Models\Deliverable;
use App\Models\PdfRemark;
use App\Models\Team;
use App\Support\StudentTeam;
use App\Support\TeamChecklist;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsultationLogController extends Controller
{
    public function generate(int $reviewId, TeamChecklist $checklists)
    {
        $review = DB::table('capstone_document_review as r')
            ->join('capstone_documents as d', 'd.file_id', '=', 'r.file_id')
            ->leftJoin('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->leftJoin('team as t', 't.team_id', '=', 'sy.team_id')
            // The deadline printed on the form is the due date the CaPSA/TSA set on
            // the checklist item this file was handed in against. It is the only
            // deadline in the system nobody has to type again.
            ->leftJoin('deliverables as dl', 'dl.id', '=', 'd.deliverable_id')
            // A line with no date of its own is due when its milestone is.
            ->leftJoin('checklist_milestones as dm', 'dm.id', '=', 'dl.checklist_milestone_id')
            ->where('r.review_id', $reviewId)
            ->select([
                'r.review_id', 'r.file_id', 'r.reviewed_at', 'r.created_at as review_created_at',
                'r.verdict', 'r.verdict_at',
                'd.file_name', 'd.chapter', 'd.student_id',
                't.team_id', 't.team_name', 't.project_title',
                DB::raw('COALESCE(dl.due_date, dm.due_on) as deadline'),
            ])
            ->first();

        if (! $review) {
            abort(404, 'Review not found.');
        }

        // Experts can pull any CLF today (pre-existing behavior); a student
        // may download the log for anything their team submitted. It used to
        // be the uploader alone, which the block directly below contradicts:
        // it lists every member of the team as a proponent on the form. One
        // teammate could download a log naming all of them, and the rest got
        // a 403.
        if (! Auth::guard('expert')->check()) {
            abort_unless(
                Auth::check()
                && in_array((int) $review->student_id, StudentTeam::memberIdsFor(Auth::id()), true),
                403
            );
        }

        // Proponents: every student on this team, across all school-year rows
        $proponents = DB::table('student_s_y as sy')
            ->join('students as s', 's.student_id', '=', 'sy.student_id')
            ->where('sy.team_id', $review->team_id)
            ->select('s.f_name', 's.l_name')
            ->distinct()
            ->get()
            ->map(fn ($s) => trim("{$s->f_name} {$s->l_name}"));

        $grouped = PdfRemark::groupedForReview($reviewId);
        $remarksBySection = $grouped['bySection'];
        $remarks = $grouped['remarks'];
        $generalRemarks = $grouped['general'];

        $consultationDate = $review->reviewed_at ?? $review->review_created_at;

        $deadline = $review->deadline;

        // A whole manuscript sent with no "Chapter N" in its name has no chapter
        // stored. Read it from the name ("Chapters 1-5"), else from the chapters
        // its remarks were written on.
        $chapter = $review->chapter;
        if (! $chapter) {
            $name = (string) $review->file_name;
            if (preg_match('/chapters?[\s_-]*1\s*(?:-|–|to)\s*([35])\b/i', $name, $m)) {
                $chapter = "Chapters 1–{$m[1]}";
            } elseif (preg_match('/\b(?:final|full)[\s_-]*manus/i', $name)) {
                $chapter = 'Chapters 1–5';
            } elseif (preg_match('/\bproposal\b/i', $name)) {
                $chapter = 'Chapters 1–3';
            } else {
                $nums = collect($remarksBySection)->keys()
                    ->map(fn ($k) => preg_match('/^Chapter\s+(\d+)/i', $k, $m) ? (int) $m[1] : null)
                    ->filter()->unique()->sort()->values();
                $chapter = match (true) {
                    $nums->count() === 1 => "Chapter {$nums[0]}",
                    $nums->count() > 1 => "Chapters {$nums->first()}–{$nums->last()}",
                    default => null,
                };
            }
        }

        // A file handed in outside the checklist tiles carries no deliverable
        // link, which is most of them. Fall back to the date the CaPSA/TSA set for
        // this chapter on the team's own checklist, so the form still prints a
        // real deadline instead of the blank line she flagged. Chapters 1-3 is
        // the Revised Proposal line and 1-5 the Final Manuscript line.
        if (! $deadline && $chapter && $review->team_id) {
            $key = match (1) {
                preg_match('/1\s*[–-]\s*3$/', $chapter) => 'revised',
                preg_match('/1\s*[–-]\s*5$/', $chapter) => 'final',
                default => preg_replace('/\D+/', '', (string) $chapter),
            };
            $checklist = ($team = Team::find($review->team_id)) ? $checklists->for($team) : null;

            if ($checklist && $key !== '') {
                $deadline = Deliverable::query()
                    ->leftJoin('checklist_milestones as m', 'm.id', '=', 'deliverables.checklist_milestone_id')
                    ->whereIn('deliverables.checklist_milestone_id', $checklist->milestones()->active()->pluck('id'))
                    ->whereNull('deliverables.archived_at')
                    ->where('deliverables.chapter', $key)
                    ->min(DB::raw('COALESCE(deliverables.due_date, m.due_on)'));
            }
        }

        $pdf = Pdf::loadView('instructor.pdf.consultation-log', [
            'projectTitle' => $review->project_title,
            'alias' => $review->team_name,
            'proponents' => $proponents,
            'chapter' => $chapter ?? '—',
            // What was reviewed: a .drawio or .vsdx sent on its own is a diagram.
            'kind' => CapstoneDocument::find($review->file_id)?->isDiagram() ? 'DIAGRAM' : 'MANUSCRIPT',
            'consultationDate' => $consultationDate,
            'deadline' => $deadline,
            'approvedAt' => $review->verdict === 'approved'
                ? ($review->verdict_at ?? $review->reviewed_at)
                : null,
            'remarksBySection' => $remarksBySection,
            'remarks' => $remarks,
            'generalRemarks' => $generalRemarks,
        ])->setPaper('letter', 'portrait');

        $fileName = 'CLF_'.str_replace(' ', '_', $review->team_name ?? 'Team').'_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($fileName);
    }
}
