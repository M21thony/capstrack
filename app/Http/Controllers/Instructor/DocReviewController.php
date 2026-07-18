<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneDocumentReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
            ->whereNotNull('r.drive_file_id')
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

    /**
     * Show the PDF annotator page for a single review.
     * $review is a CapstoneDocumentReview model instance, injected automatically
     * by Laravel's route-model binding (see Route::model('review', ...) in
     * AppServiceProvider). NOT a raw ID.
     */
    public function annotate(CapstoneDocumentReview $review)
    {
        $expertId = Auth::guard('expert')->id();

        abort_if($review->expert_id != $expertId, 403, 'You do not have access to this review.');

        // Load the related document (file_name etc.) via the model relationship.
        $review->load('document');

        return view('instructor.docreview-annotate', [
            'review' => $review,
        ]);
    }

    /**
     * Stream the PDF for a review. Tries local storage first, falls back to Google Drive.
     * Route: GET /reviews/{review}/pdf
     */
    public function pdf(CapstoneDocumentReview $review)
    {
        $expertId = Auth::guard('expert')->id();

        abort_if($review->expert_id != $expertId, 403, 'You do not have access to this review.');

        $fileName = $review->document->file_name ?? null;
        abort_if(! $fileName, 404, 'No file associated with this review.');

        // 1. Local storage: storage/app/public/documents/{file_name}
        $localPath = 'documents/'.$fileName;
        if (Storage::disk('public')->exists($localPath)) {
            return Storage::disk('public')->response($localPath, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        // 2. Google Drive fallback
        if ($review->drive_web_view_link) {
            if (preg_match('/[-\w]{25,}/', $review->drive_web_view_link, $matches)) {
                $fileId = $matches[0];
                $directUrl = "https://drive.google.com/uc?export=download&id={$fileId}";
                $response = Http::get($directUrl);

                if ($response->successful()) {
                    return response($response->body())->header('Content-Type', 'application/pdf');
                }
            }

            return redirect($review->drive_web_view_link);
        }

        abort(404, 'No file available for this review.');
    }

    /**
     * Return saved remarks (annotations) for a review as JSON.
     * Route: GET /reviews/{review}/remarks
     */
    public function getRemarks(CapstoneDocumentReview $review)
    {
        $expertId = Auth::guard('expert')->id();

        abort_if($review->expert_id != $expertId, 403, 'You do not have access to this review.');

        $remarks = $review->pdfRemarks()->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'page' => $r->page_number,
                'top' => $r->top_position,
                'left' => $r->left_position,
                'width' => $r->highlight_width,
                'height' => $r->highlight_height,
                'color' => $r->color,
                'text' => $r->comment,
            ];
        });

        return response()->json(['comments' => $remarks]);
    }

    /**
     * Replace all remarks (annotations) for a review in one request.
     * Route: POST /reviews/{review}/remarks
     */
    public function saveRemarks(Request $request, CapstoneDocumentReview $review)
    {
        $expertId = Auth::guard('expert')->id();

        abort_if($review->expert_id != $expertId, 403, 'You do not have access to this review.');

        $validated = $request->validate([
            'comments' => 'present|array',
            'comments.*.page' => 'required|integer',
            'comments.*.top' => 'required|numeric',
            'comments.*.left' => 'required|numeric',
            'comments.*.width' => 'nullable|numeric',
            'comments.*.height' => 'nullable|numeric',
            'comments.*.color' => 'nullable|string|max:20',
            'comments.*.text' => 'nullable|string',
        ]);

        DB::transaction(function () use ($review, $validated) {
            // Replace-all: delete existing remarks, insert the new set.
            $review->pdfRemarks()->delete();

            foreach ($validated['comments'] as $comment) {
                $review->pdfRemarks()->create([
                    'page_number' => $comment['page'],
                    'top_position' => $comment['top'],
                    'left_position' => $comment['left'],
                    'highlight_width' => $comment['width'] ?? 0,
                    'highlight_height' => $comment['height'] ?? 0,
                    'color' => $comment['color'] ?? 'yellow',
                    'comment' => $comment['text'] ?? null,
                ]);
            }
        });

        return response()->json(['success' => true]);
    }
}
