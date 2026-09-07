<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\PdfRemark;
use App\Support\StudentTeam;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsultationLogController extends Controller
{
    public function generate(int $reviewId)
    {
        $review = DB::table('capstone_document_review as r')
            ->join('capstone_documents as d', 'd.file_id', '=', 'r.file_id')
            ->leftJoin('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->leftJoin('team as t', 't.team_id', '=', 'sy.team_id')
            ->where('r.review_id', $reviewId)
            ->select([
                'r.review_id', 'r.reviewed_at', 'r.created_at as review_created_at',
                'd.file_name', 'd.chapter', 'd.student_id',
                't.team_id', 't.team_name', 't.project_title',
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

        $pdf = Pdf::loadView('instructor.pdf.consultation-log', [
            'projectTitle' => $review->project_title,
            'alias' => $review->team_name,
            'proponents' => $proponents,
            'chapter' => $review->chapter ?? '—',
            'consultationDate' => $consultationDate,
            'remarksBySection' => $remarksBySection,
            'remarks' => $remarks,
            'generalRemarks' => $generalRemarks,
        ])->setPaper('letter', 'portrait');

        $fileName = 'CLF_'.str_replace(' ', '_', $review->team_name ?? 'Team').'_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($fileName);
    }
}