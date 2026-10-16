<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentReviewController;
use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\PdfRemark;
use App\Models\Student;
use App\Support\SubmissionRecipients;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Documents: what used to be two pages, Document Submission and Review
 * Viewer. The list is the team's files, one group per line (checklist line,
 * else chapter, else file name) with every version under it - who sent it,
 * when, and where each reviewer is. Opening one shows the file with its
 * reviewer's remarks beside it, on the same page.
 */
class DocumentsController extends Controller
{
    /** How many uploads the list reads, newest first. */
    private const LIMIT = 100;

    public function index(Request $request): View
    {
        $studentId = Auth::id();

        $uploads = CapstoneDocument::forTeamOf($studentId)
            ->with(['deliverable', 'reviews' => fn ($q) => $q->with('expert')->orderByDesc('reviewed_at')])
            ->latest('created_at')
            ->take(self::LIMIT)
            ->get();

        $uploaders = Student::whereIn('student_id', $uploads->pluck('student_id')->unique())->get()->keyBy('student_id');

        $groups = $uploads
            ->groupBy(fn (CapstoneDocument $document) => self::lineKey($document))
            ->map(fn (Collection $versions) => $this->group($versions->sortByDesc('file_version')->values(), $uploaders, $studentId))
            ->values();

        $opened = $request->filled('file')
            ? $uploads->firstWhere('file_id', $request->integer('file'))
            : null;

        return view('student.documents', [
            'groups' => $groups,
            'reader' => $opened ? $this->reader($opened, $groups, $request->integer('review')) : null,
            'selectedItemId' => (int) $request->query('item') ?: null,
        ]);
    }

    /** The clean file of an upload nobody has reviewed yet, for the reader. */
    public function file(CapstoneDocument $document)
    {
        $this->authorizeDocument($document);

        abort_unless($document->file_path && ! $document->isDiagram(), 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, null, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * The draw.io and Visio files of an upload, for the reader.
     *
     * @return list<array{name: string, xml?: string, vsdx?: string}>
     */
    public function diagrams(CapstoneDocument $document): array
    {
        $this->authorizeDocument($document);

        return $document->diagrams();
    }

    /** A team's upload is the whole team's to read, whoever pressed Upload. */
    private function authorizeDocument(CapstoneDocument $document): void
    {
        abort_unless(CapstoneDocument::forTeamOf(Auth::id())->whereKey($document->file_id)->exists(), 404);
    }

    /** Which line an upload is a version of, the way versions are counted. */
    private static function lineKey(CapstoneDocument $document): string
    {
        return match (true) {
            (bool) $document->deliverable_id => 'line:'.$document->deliverable_id,
            filled($document->chapter) => 'chapter:'.$document->chapter,
            default => 'file:'.$document->file_name,
        };
    }

    /**
     * One line's uploads, newest version first.
     *
     * @param  Collection<int, CapstoneDocument>  $versions
     * @param  Collection<int, Student>  $uploaders
     */
    private function group(Collection $versions, Collection $uploaders, int $studentId): object
    {
        $latest = $versions->first();

        return (object) [
            'title' => $latest->deliverable?->title ?? ($latest->chapter ?: $latest->file_name),
            'latest' => $latest,
            'status' => self::status($latest),
            // A revision asked of the newest version is the next thing to do.
            'revise' => $latest->reviews->contains('verdict', CapstoneDocumentReview::NEEDS_REVISION)
                ? ['itemId' => $latest->deliverable_id ?: 'none', 'fileId' => $latest->file_id, 'next' => $latest->nextRoundLabel()]
                : null,
            'versions' => $versions->map(fn (CapstoneDocument $document) => (object) [
                'document' => $document,
                'isLatest' => $document->is($latest),
                'uploader' => $uploaders->get($document->student_id),
                'isMine' => (int) $document->student_id === $studentId,
                'reviews' => $document->reviews->map(fn (CapstoneDocumentReview $review) => self::reviewState($review))->values(),
                'canUnsubmit' => $document->canBeUnsubmitted(),
            ]),
        ];
    }

    /** A reviewer by the name the team knows them by: CaPA, Consultant, CaPSA. */
    public static function roleLabel(?string $role): string
    {
        return SubmissionRecipients::LABELS[$role] ?? StudentReviewController::roleLabel($role);
    }

    /**
     * What kind of file an upload is, by the name it was sent under: a
     * diagram is kept on disk under another extension.
     */
    public static function fileKind(CapstoneDocument $document): string
    {
        $name = $document->isDiagram()
            ? (collect($document->diagram_files)->firstWhere('path', $document->file_path)['name'] ?? $document->file_path)
            : $document->file_path;

        return strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)) ?: 'pdf';
    }

    /** @return array{0: string, 1: string} The label and its tone. */
    private static function status(CapstoneDocument $document): array
    {
        $verdicts = $document->reviews->pluck('verdict')->filter();

        return match (true) {
            $verdicts->contains(CapstoneDocumentReview::NEEDS_REVISION) => ['Revision asked', 'revise'],
            $verdicts->isNotEmpty() && $verdicts->every(fn ($v) => $v === CapstoneDocumentReview::APPROVED) => ['Approved', 'ok'],
            $document->reviews->contains(fn ($r) => $r->review_status !== CapstoneDocumentReview::AWAITING) => ['Being reviewed', 'now'],
            $document->reviews->isNotEmpty() => ['Not opened yet', ''],
            default => ['Sent', ''],
        };
    }

    /** @return array{id: int, role: string, name: ?string, text: string, tone: string, done: bool} */
    private static function reviewState(CapstoneDocumentReview $review): array
    {
        $done = $review->review_status === CapstoneDocumentReview::REVIEWED;

        [$text, $tone] = match (true) {
            $done && $review->verdict === CapstoneDocumentReview::APPROVED => ['Approved', 'ok'],
            $done && $review->verdict === CapstoneDocumentReview::NEEDS_REVISION => ['Needs revision', 'revise'],
            $done => ['Reviewed', 'ok'],
            $review->review_status === CapstoneDocumentReview::IN_PROGRESS => ['Reviewing', 'now'],
            default => ['Not opened yet', ''],
        };

        return [
            'id' => $review->review_id,
            'role' => self::roleLabel($review->role),
            'name' => $review->expert?->name,
            'text' => $text,
            'tone' => $tone,
            'done' => $done,
        ];
    }

    /**
     * One upload opened: its other versions, its reviewers, and the one
     * reviewer's remarks being read - the one asked for, else the newest
     * turned in. An upload nobody has turned in yet opens as the file alone.
     *
     * @param  Collection<int, object>  $groups
     */
    private function reader(CapstoneDocument $document, Collection $groups, int $reviewId): object
    {
        $group = $groups->first(fn ($g) => $g->versions->contains(fn ($v) => $v->document->is($document)));
        $version = $group->versions->first(fn ($v) => $v->document->is($document));

        $turnedIn = $document->reviews->where('review_status', CapstoneDocumentReview::REVIEWED);
        $review = $turnedIn->firstWhere('review_id', $reviewId) ?? $turnedIn->first();

        return (object) [
            'group' => $group,
            'version' => $version,
            'document' => $document,
            'review' => $review,
            'status' => self::status($document),
            'reviewer' => $review?->expert?->name ?? ($review ? 'your reviewer' : 'your reviewers'),
            'general' => $review ? $this->generalRemarks($review) : [],
            'decision' => $review ? $this->decision($document, $review) : null,
            'pdfUrl' => $document->isDiagram() ? '' : ($review
                ? route('student.review.pdf', $review->review_id)
                : route('student.documents.file', $document->file_id)),
            'remarksUrl' => $review ? route('student.review.remarks', $review->review_id) : '',
            'diagramsUrl' => $document->diagram_files ? ($review
                ? route('student.review.diagrams', $review->review_id)
                : route('student.documents.diagrams', $document->file_id)) : '',
        ];
    }

    /** @return array<int, array{id: int, text: string, section: ?string}> */
    private function generalRemarks(CapstoneDocumentReview $review): array
    {
        return PdfRemark::where('review_id', $review->review_id)
            ->where('is_general', true)
            ->orderBy('created_at')
            ->get(['id', 'comment', 'section'])
            ->filter(fn ($r) => filled($r->comment))
            ->map(fn ($r) => ['id' => $r->id, 'text' => $r->comment, 'section' => $r->section])
            ->values()
            ->all();
    }

    /** What the reviewer decided, and what the team does next. */
    private function decision(CapstoneDocument $document, CapstoneDocumentReview $review): array
    {
        $item = $document->deliverable;

        return [
            'verdict' => $review->verdict,
            'reviewer' => $review->expert?->name ?? 'your reviewer',
            'role' => self::roleLabel($review->role),
            'date' => $review->reviewed_at?->format('M j'),
            'round' => $document->roundLabel(),
            'next' => $document->nextRoundLabel(),
            'due' => $item?->due_date?->format('M j'),
            'requirement' => $item?->title,
            // An upload for no line is revised as one for no line, not by picking a line.
            'itemId' => $item?->id ?? 'none',
            'fileId' => $document->file_id,
        ];
    }
}
