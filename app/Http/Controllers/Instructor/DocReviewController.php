<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\GoogleDriveService;
use App\Support\StudentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocReviewController extends Controller
{
    public function __construct(private GoogleDriveService $drive) {}

    public function index()
    {
        $expertId = Auth::guard('expert')->id();

        // The team a file was sent for, not the uploader's team today: a
        // student who moved took their old files to the new team's name, or
        // to a dash if they have none now. Files from before uploads recorded
        // a team fall back to the uploader's current one.
        $reviews = DB::table('capstone_document_review as r')
            ->join('capstone_documents as d', 'd.file_id', '=', 'r.file_id')
            ->leftJoin('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->leftJoin('team as t', 't.team_id', '=', DB::raw('COALESCE(d.team_id, sy.team_id)'))
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
                'd.deliverable_id',
                'd.chapter',
                'd.created_at as submitted_at',
                't.team_id',
                't.team_name',
            ])
            ->get();

        // One row per document: its latest version. Earlier ones open from
        // inside the annotator, matched the way CapstoneDocument counts
        // versions - the checklist line, else the chapter, else the file name.
        $reviews = $reviews
            ->groupBy(fn ($r) => implode('|', [
                $r->team_id, $r->role,
                $r->deliverable_id ? 'd'.$r->deliverable_id : ($r->chapter ? 'c'.$r->chapter : 'f'.$r->file_name),
            ]))
            ->map(fn ($versions) => $versions->sortByDesc(fn ($r) => [(int) $r->file_version, $r->submitted_at])->first())
            ->values();

        // Teams in the order they need you - whoever has waited longest first,
        // teams with nothing waiting last - and within a team, waiting files
        // oldest first, then reviewed ones newest first.
        $isWaiting = fn ($r) => $r->review_status !== 'Reviewed';
        $reviews = $reviews
            ->groupBy(fn ($r) => $r->team_name ?? '')
            ->sortBy(fn ($rows, $team) => [
                $rows->contains($isWaiting) ? 0 : 1,
                $rows->filter($isWaiting)->min('submitted_at') ?? '',
                $team,
            ])
            ->map(fn ($rows) => $rows->filter($isWaiting)->sortBy('submitted_at')
                ->concat($rows->reject($isWaiting)->sortByDesc('submitted_at'))
                ->values());

        return view('instructor.docreview', [
            'reviews' => $reviews->flatten(1),
            'reviewsByTeam' => $reviews,
            'teams' => $reviews->keys()->filter()->sort()->values(),
            'driveConnected' => $this->drive->isConnected($expertId),
        ]);
    }

    public function annotate(Request $request, $review)
    {
        abort_if($review->expert_id !== Auth::guard('expert')->id(), 403);

        $review->load('document.deliverable');

        $document = $review->document;
        $item = $document?->deliverable;

        return view('instructor.docreview-annotate', [
            'review' => $review,
            // What the annotator's top bar says about the document in front
            // of the reviewer: whose it is, which requirement, which round.
            'context' => [
                'reviewId' => $review->review_id,
                'title' => $item?->title ?? $document?->file_name,
                'fileName' => $document?->file_name,
                'team' => $document
                    ? ($document->team_id ? Team::find($document->team_id) : StudentTeam::for($document->student_id))?->team_name
                    : null,
                'role' => $review->role,
                'round' => $document?->roundLabel(),
                'nextRound' => $document?->nextRoundLabel(),
                'requirement' => $item?->title,
                'due' => $item?->due_date?->format('M j'),
                'backUrl' => route('instructor.docreview'),
                // The draw.io files of its diagrams, when the team sent them.
                'diagrams' => $document?->diagram_files ? route('reviews.diagrams', $review->review_id) : null,
                // Earlier rounds of the same requirement, to open beside this one.
                'versions' => $document ? $document->earlierVersions()->map(fn ($v) => [
                    'id' => (int) $v->file_id,
                    'round' => $v->roundLabel(),
                    'uploaded' => $v->created_at?->format('M j, Y'),
                    'file' => route('reviews.versions.file', [$review->review_id, $v->file_id]),
                    'remarks' => route('reviews.versions.remarks', [$review->review_id, $v->file_id]),
                    'diagrams' => $v->diagram_files ? route('reviews.versions.diagrams', [$review->review_id, $v->file_id]) : null,
                ])->values() : [],
            ],
        ]);
    }
}
