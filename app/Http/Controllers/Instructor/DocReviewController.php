<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocReviewController extends Controller
{
    public function index()
    {
        $expertId = Auth::guard('expert')->id();

        $reviews = DB::table('capstone_document_review as r')
            ->join('capstone_documents as d', 'd.file_id', '=', 'r.file_id')
            ->leftJoin('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->leftJoin('team as t', 't.team_id', '=', 'sy.team_id')
            ->where('r.expert_id', $expertId)
            ->select([
                'r.review_id',
                'r.file_id',
                'r.role',
                'r.review_status',
                'r.reviewed_at',
                'r.version',
                'r.drive_status',
                'r.drive_web_view_link',
                'd.file_name',
                'd.file_version',
                'd.document_status',
                'd.created_at as submitted_at',
                't.team_id',
                't.team_name',
            ])
            ->orderByDesc('d.created_at')
            ->get();

        $teams = DB::table('team as t')
            ->join('student_s_y as sy', 'sy.team_id', '=', 't.team_id')
            ->join('capstone_documents as d', 'd.student_id', '=', 'sy.student_id')
            ->join('capstone_document_review as r', 'r.file_id', '=', 'd.file_id')
            ->where('r.expert_id', $expertId)
            ->select('t.team_id', 't.team_name')
            ->distinct()
            ->orderBy('t.team_name')
            ->get();

        return view('instructor.docreview', [
            'reviews' => $reviews,
            'teams' => $teams,
        ]);
    }

    public function annotate(Request $request, $review)
    {
        abort_if($review->expert_id !== Auth::guard('expert')->id(), 403);

        $review->load('document');

        return view('instructor.docreview-annotate', [
            'review' => $review,
        ]);
    }
}
