<?php

namespace App\Http\Controllers;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\Deliverable;
use App\Models\GoogleDriveToken;
use App\Models\Team;
use App\Services\GoogleDriveService;
use App\Services\Notifier;
use App\Services\RecipientResolver;
use App\Support\MilestoneProgress;
use App\Support\SectionScope;
use App\Support\StudentTeam;
use App\Support\TeamChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubmissionController extends Controller
{
    public function __construct(
        private GoogleDriveService $drive,
        private RecipientResolver $resolver,
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:50',
            'chapter' => 'nullable|string|max:50',
            'file' => 'required|file|max:25600', // 25 MB
            'recipients' => 'nullable|array',
            'recipients.*' => 'in:adviser,consultant,grammarian,subject_adviser',
            'subject_adviser_folder_id' => 'nullable|string',
            'deliverable_id' => 'nullable|integer',
        ]);

        $studentId = Auth::id(); // adjust guard if students use a named guard

        // Resolve the student's current team + program from student_s_y
        $studentYear = DB::table('student_s_y')
            ->where('student_id', $studentId)
            ->orderByDesc('school_year')
            ->first();

        if (! $studentYear || ! $studentYear->team_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not currently assigned to a team.',
            ], 422);
        }

        $team = DB::table('team')->where('team_id', $studentYear->team_id)->first();

        $file = $request->file('file');
        $originalExt = strtolower($file->getClientOriginalExtension());

        // The checklist line this file is for, if any. None means it was sent
        // for feedback only and moves nothing on the checklist.
        $item = null;

        if ($request->filled('deliverable_id')) {
            $checklist = ($teamModel = Team::find($studentYear->team_id)) ? app(TeamChecklist::class)->for($teamModel) : null;
            $item = Deliverable::active()->with('milestone')->find($request->integer('deliverable_id'));

            if (! $item || ! $checklist || (int) $item->milestone->checklist_id !== $checklist->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'That requirement is not on your team\'s checklist.',
                ], 422);
            }

            // The same gate the CaPSA's grid and the team's own checklist
            // read, asked here so nothing can be handed in past a milestone
            // that shows as locked on screen.
            $gate = MilestoneProgress::forTeam($checklist, (int) $teamModel->team_id)
                ->items->firstWhere('item.id', $item->id);

            if ($gate?->locked) {
                return response()->json([
                    'success' => false,
                    'message' => $item->title." can't be handed in yet: it waits for ".$gate->waits_for.'.',
                ], 422);
            }
        }

        // A signed paper - a form, or a CLF signed by hand - is checked by the
        // CaPSA, not reviewed, so it needs no recipient and a phone photo of
        // it is as good as a scan. Anything else goes to a reviewer's
        // annotator, which reads documents.
        $isPaper = $item && in_array($item->kind, Deliverable::CHECKED_BY_CAPSA, true);
        $allowed = $isPaper ? ['pdf', 'jpg', 'jpeg', 'png'] : ['pdf', 'doc', 'docx', 'ppt', 'pptx'];

        if ($item && ! in_array($originalExt, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => $isPaper ? 'Upload the signed paper as a PDF, JPG or PNG.' : 'Upload this requirement as a PDF.',
            ], 422);
        }

        if (! $isPaper && empty($request->input('recipients'))) {
            return response()->json([
                'success' => false,
                'message' => 'Pick at least one person to send this to.',
            ], 422);
        }
        $storedPath = $file->store('submissions', 'local'); // adjust disk as needed

        // No title/chapter form fields anymore - students just attach a file
        // and submit. Falls back to the uploaded file's own name so every
        // document still has something sensible to display everywhere else
        // this field is read (review lists, instructor doc-review, etc).
        $title = $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $chapter = $request->input('chapter') ?: $this->detectChapter($title);

        // Version only increments for resubmissions of the *same* document.
        // A student who names files "Thesis_2026-08-12.pdf" then
        // "Thesis_2026-08-13.pdf" still wants those to count as the same
        // document, so when a "Chapter N" reference is detectable in the
        // name, that's used as the grouping key instead of the literal
        // filename - it survives a changing date/suffix around it. Files
        // with no detectable chapter fall back to exact filename matching,
        // same as before.
        // Counted across the whole team, not the one account uploading. A
        // chapter is the team's, so two members each sending "Chapter 1" were
        // both told they were on Version 1 of it.
        // A file sent for a checklist line is a revision of that line whatever
        // it is called, so the line is the grouping key when there is one.
        $lastVersion = CapstoneDocument::whereIn('student_id', StudentTeam::memberIdsFor($studentId))
            ->when(
                $item,
                fn ($query) => $query->where('deliverable_id', $item->id),
                fn ($query) => $query->when(
                    $chapter,
                    fn ($query) => $query->where('chapter', $chapter),
                    fn ($query) => $query->where('file_name', $title)
                )
            )
            ->max('file_version') ?? 0;

        $document = CapstoneDocument::create([
            'student_id' => $studentId,
            'file_name' => $title,
            'chapter' => $chapter,
            'deliverable_id' => $item?->id,
            'file_path' => $storedPath,
            'file_version' => $lastVersion + 1,
            'document_status' => 'Pending',
        ]);

        // A paper goes into no reviewer's queue - it waits on the section's
        // CaPSA, who until now was told nothing and only found it by opening
        // the Milestones tab and picking the right section.
        if ($isPaper) {
            Notifier::toExpert(
                (int) $checklist->capsa_id,
                'paper_to_check',
                'Signed paper to check',
                '<strong>'.e($team->team_name ?? 'A team').'</strong> handed in <strong>'.e($item->title).'</strong>.',
                route('capsa.team_management', array_filter([
                    'tab' => 'milestones',
                    'section' => app(SectionScope::class)->sectionFor($teamModel),
                ])),
                ['team_id' => $studentYear->team_id, 'deliverable_id' => $item->id, 'file_id' => $document->file_id],
            );
        }

        $results = [];

        foreach ($request->input('recipients', []) as $role) {
            if ($role === 'subject_adviser' && ! $studentYear->program) {
                $results[] = [
                    'role' => $role,
                    'status' => 'unresolved',
                    'message' => 'Your program is not set, so a Subject Adviser could not be determined.',
                ];

                continue;
            }

            $expertId = $this->resolver->resolve($role, $studentYear->team_id, $studentYear->program ?? '');

            if (! $expertId) {
                $results[] = [
                    'role' => $role,
                    'status' => 'unresolved',
                    'message' => 'No expert currently assigned to this role.',
                ];

                continue;
            }

            $review = CapstoneDocumentReview::create([
                'file_id' => $document->file_id,
                'expert_id' => $expertId,
                'role' => $role,
                'review_status' => 'To Review',
                'version' => (string) $document->file_version,
                'drive_status' => 'pending',
            ]);

            // No email has ever gone out for a submission - the expert found
            // out by opening Document Review. This is the first time they are
            // actually told.
            Notifier::toExpert(
                (int) $expertId,
                'document_submitted',
                'Document to review',
                '<strong>'.e($team->team_name ?? 'A team').'</strong> sent you <strong>'
                    .e($document->file_name).'</strong> as their '.e(str_replace('_', ' ', $role)).'.',
                route('instructor.docreview'),
                ['team_id' => $studentYear->team_id, 'review_id' => $review->review_id],
            );

            $explicitFolderId = $role === 'subject_adviser' ? $request->input('subject_adviser_folder_id') : null;
            $this->deliverToDrive($review, $expertId, $studentYear->team_id, $team->team_name ?? 'Unknown Team', $file, $originalExt, $explicitFolderId);

            $results[] = [
                'role' => $role,
                'status' => $review->fresh()->drive_status,
            ];
        }

        return response()->json([
            'success' => true,
            'document' => $document,
            'recipients' => $results,
        ]);
    }

    // GET /student/submission/next-version?title=... — the version number
    // this student's next submission would get, using the same chapter
    // detection + grouping rules as store(), so the read-only Version (and
    // Detected Chapter) labels on the form always match what actually
    // happens on submit.
    public function nextVersionForTitle(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);

        $studentId = Auth::id();
        $title = $request->input('title');
        $chapter = $this->detectChapter($title);

        // Counted across the whole team, not the one account uploading. A
        // chapter is the team's, so two members each sending "Chapter 1" were
        // both told they were on Version 1 of it.
        $lastVersion = CapstoneDocument::whereIn('student_id', StudentTeam::memberIdsFor($studentId))
            ->when(
                $chapter,
                fn ($query) => $query->where('chapter', $chapter),
                fn ($query) => $query->where('file_name', $title)
            )
            ->max('file_version') ?? 0;

        return response()->json([
            'version' => $lastVersion + 1,
            'chapter' => $chapter,
        ]);
    }

    // Looks for an explicit "chapter"/"chap"/"ch" + number reference in a
    // file name (e.g. "Chapter1_2026-08-12", "Ch_2_Final") and normalizes it
    // to "Chapter N". Anchored on that keyword rather than guessing at date
    // formats - "ch"/"chapter" only ever means chapter, so it doesn't
    // false-positive on unrelated numeric suffixes the way stripping "any
    // trailing date-looking text" would.
    private function detectChapter(string $name): ?string
    {
        // (?!\d) instead of a trailing \b: underscore is a "word" character
        // to regex, so "Chapter1_2026-08-12" has no \b between the "1" and
        // the "_" that follows it, and a trailing \b would fail to match.
        if (! preg_match('/\b(?:chapter|chap|ch)[\s_-]*([0-9]{1,2})(?!\d)/i', $name, $matches)) {
            return null;
        }

        return 'Chapter '.(int) $matches[1];
    }

    // GET /api/student/submission/subject-adviser-folders — named folders
    // the resolved Subject Adviser has set up for students to pick from.
    public function subjectAdviserFolders()
    {
        $studentId = Auth::id();

        $studentYear = DB::table('student_s_y')
            ->where('student_id', $studentId)
            ->orderByDesc('school_year')
            ->first();

        if (! $studentYear || ! $studentYear->team_id) {
            return response()->json(['folders' => []]);
        }

        $expertId = $this->resolver->resolveSubjectAdviserAdmin($studentYear->team_id);

        if (! $expertId) {
            return response()->json(['folders' => []]);
        }

        return response()->json([
            'folders' => $this->drive->listNamedFolders($expertId),
        ]);
    }

    public function create(Request $request)
    {
        $studentId = Auth::id();

        $studentYear = DB::table('student_s_y')
            ->where('student_id', $studentId)
            ->orderByDesc('school_year')
            ->first();

        // The team's checklist lines still open, for "What is this for?".
        // Opened from a line on the Progress Tracker (?item=), that line
        // arrives already picked.
        $checklistItems = collect();

        $progress = $studentYear && $studentYear->team_id
            ? Team::find($studentYear->team_id)?->checklistProgress()
            : null;

        if ($progress) {
            // Each line carries its own lock: its milestone's, or the order
            // inside the milestone.
            $checklistItems = $progress->milestones
                ->flatMap(fn ($ms) => $ms->items
                    ->reject(fn ($row) => $row->item->kind === Deliverable::DEFENSE
                        || in_array($row->state, MilestoneProgress::COMPLETE_STATES, true))
                    ->map(fn ($row) => (object) [
                        'id' => $row->item->id,
                        'title' => $row->item->title,
                        'phase' => $ms->milestone->name,
                        'kind' => $row->item->kind,
                        'role' => $row->item->role,
                        'locked' => $row->locked,
                        'note' => match (true) {
                            $row->locked => 'waits for '.$row->waits_for,
                            (bool) $row->note => 'sent back: '.$row->note,
                            $row->changes_requested => self::ordinal($row->revision).' revision requested',
                            $row->waiting_on === MilestoneProgress::WAITING_ON_SIGNED_COPY => 'approved, upload the signed copy',
                            default => null,
                        },
                    ]))
                ->values();
        }

        $selectedItemId = (int) $request->query('item');

        $recipients = [
            'adviser' => ['expert_id' => null, 'name' => null],
            'consultant' => ['expert_id' => null, 'name' => null],
            'grammarian' => ['expert_id' => null, 'name' => null],
            'subject_adviser' => ['expert_id' => null, 'name' => null],
        ];

        if ($studentYear && $studentYear->team_id) {
            $recipients = $this->resolver->getAssignedNames(
                $studentYear->team_id,
                $studentYear->program ?? ''
            );
        }

        // Actual version depends on the file's name, which isn't known until
        // one is selected client-side; the form fetches the real number via
        // nextVersionForTitle() once a file is picked. This is just the
        // before-a-file-is-chosen placeholder.
        $nextVersion = 1;

        // A fragment, not a page: the drawer drops it straight into the panel.
        return view('student.partials.submission-form', compact('recipients', 'nextVersion', 'checklistItems', 'selectedItemId'));
    }

    private static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th');

        return $n.$suffix;
    }

    private function deliverToDrive(
        CapstoneDocumentReview $review,
        int $expertId,
        int $teamId,
        string $teamName,
        $file,
        string $ext,
        ?string $explicitFolderId = null
    ): void {
        $hasToken = GoogleDriveToken::where('user_id', $expertId)->exists();

        if (! $hasToken) {
            $review->update([
                'drive_status' => 'not_connected',
                'drive_error' => 'This recipient has not connected Google Drive yet.',
            ]);

            return;
        }

        $extensionMimeMap = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        $mimeType = $extensionMimeMap[$ext] ?? $file->getMimeType();

        try {
            // A student-chosen folder (currently only offered for the Subject
            // Adviser recipient) takes priority over the default per-team
            // auto-created one.
            $folderId = $explicitFolderId ?? $this->drive->getOrCreateSubmissionFolder($expertId, $teamId, $teamName);

            $uploaded = $this->drive->uploadFile(
                $expertId,
                $file->getRealPath(),
                $file->getClientOriginalName(),
                $mimeType,
                $folderId
            );

            $review->update([
                'drive_file_id' => $uploaded['id'],
                'drive_web_view_link' => $uploaded['web_view_link'],
                'drive_status' => 'uploaded',
                'drive_error' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Submission Drive delivery failed', [
                'review_id' => $review->review_id,
                'expert_id' => $expertId,
                'message' => $e->getMessage(),
            ]);

            $review->update([
                'drive_status' => 'failed',
                'drive_error' => $e->getMessage(),
            ]);
        }
    }
}
