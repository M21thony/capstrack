<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
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
            // The deadline printed on the form is the due date the CaPSA set on
            // the checklist item this file was handed in against. It is the only
            // deadline in the system nobody has to type again.
            ->leftJoin('deliverables as dl', 'dl.id', '=', 'd.deliverable_id')
            ->where('r.review_id', $reviewId)
            ->select([
                'r.review_id', 'r.reviewed_at', 'r.created_at as review_created_at',
                'r.verdict', 'r.verdict_at',
                'd.file_name', 'd.chapter', 'd.student_id',
                't.team_id', 't.team_name', 't.project_title',
                'dl.due_date as deadline',
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

        // A file handed in outside the checklist tiles carries no deliverable
        // link, which is most of them. Fall back to the date the CaPSA set for
        // this chapter on the team's own checklist, so the form still prints a
        // real deadline instead of the blank line she flagged.
        if (! $deadline && $review->chapter && $review->team_id) {
            $chapter = preg_replace('/\D+/', '', (string) $review->chapter);
            $checklist = ($team = Team::find($review->team_id)) ? $checklists->for($team) : null;

            if ($checklist && $chapter !== '') {
                $deadline = Deliverable::whereIn('checklist_milestone_id', $checklist->milestones()->active()->pluck('id'))
                    ->whereNull('archived_at')
                    ->where('chapter', $chapter)
                    ->min('due_date');
            }
        }

        $pdf = Pdf::loadView('instructor.pdf.consultation-log', [
            'projectTitle' => $review->project_title,
            'alias' => $review->team_name,
            'proponents' => $proponents,
            'chapter' => $review->chapter ?? '—',
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