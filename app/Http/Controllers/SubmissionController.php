<?php

namespace App\Http\Controllers;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Deliverable;
use App\Models\Team;
use App\Services\Notifier;
use App\Services\RecipientResolver;
use App\Support\MilestoneProgress;
use App\Support\RevisionCheck;
use App\Support\RevisionRemark;
use App\Support\SectionScope;
use App\Support\StudentTeam;
use App\Support\SubmissionRecipients;
use App\Support\TeamChecklist;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    /** The diagram files a team can send as the document itself. */
    private const DIAGRAM_TYPES = ['drawio', 'vsdx'];

    public function __construct(
        private RecipientResolver $resolver,
        private SubmissionRecipients $recipients,
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:50',
            'chapter' => 'nullable|string|max:50',
            'file' => 'required|file|max:25600', // 25 MB
            'recipients' => 'nullable|array',
            'recipients.*' => 'in:'.implode(',', SubmissionRecipients::ROLES),
            'deliverable_id' => 'nullable|integer',
            // The upload a revision of a file for no line is the next version of.
            'revises' => 'nullable|integer',
            // The draw.io or Visio files of its diagrams, so a reviewer can be shown exactly what changed in them.
            'diagrams' => 'nullable|array|max:20',
            'diagrams.*' => ['file', 'max:5120', function (string $attribute, mixed $value, Closure $fail) {
                if (! $this->isDiagramFile($value)) {
                    $fail('Diagrams must be draw.io (.drawio) or Visio (.vsdx) files.');
                }
            }],
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
        $allowed = $isPaper ? ['pdf', 'jpg', 'jpeg', 'png'] : ['pdf', 'doc', 'docx', 'ppt', 'pptx', ...self::DIAGRAM_TYPES];

        if ($item && ! in_array($originalExt, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => $isPaper ? 'Upload the signed paper as a PDF, JPG or PNG.' : 'Upload this requirement as a PDF, or a draw.io or Visio diagram.',
            ], 422);
        }

        // A diagram is sent as itself, the file the team drew, and read in
        // the annotator as its pages: no PDF of it to lose the drawing in.
        $isDiagram = ! $isPaper && in_array($originalExt, self::DIAGRAM_TYPES, true);

        if ($isDiagram && ! $this->isDiagramFile($file)) {
            return response()->json([
                'success' => false,
                'message' => 'That diagram won\'t open. Save it again from draw.io or Visio and add it here.',
            ], 422);
        }

        // Who it goes to is the line's rule, not whatever boxes were ticked: a
        // revision goes back to exactly the people who had the last version.
        $revises = ! $item && $request->filled('revises')
            ? CapstoneDocument::ofTeam((int) $studentYear->team_id)->find($request->integer('revises'))
            : null;
        $targets = $isPaper ? [] : $this->recipients->resolve($item, (int) $studentYear->team_id, (array) $request->input('recipients', []), $revises);

        if (! $isPaper && $targets === []) {
            return response()->json([
                'success' => false,
                'message' => 'Pick at least one person to send this to.',
            ], 422);
        }

        if ($item && ($same = $this->sameAsLast($file->getRealPath(), $item, (int) $studentYear->team_id))) {
            return response()->json([
                'success' => false,
                'message' => "This is the same file as version {$same}. Pick your edited copy.",
            ], 422);
        }
        $storedPath = $file->store('submissions', 'local'); // adjust disk as needed

        // No title/chapter form fields anymore - students just attach a file
        // and submit. Falls back to the uploaded file's own name so every
        // document still has something sensible to display everywhere else
        // this field is read (review lists, instructor doc-review, etc).
        $title = $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $chapter = $request->input('chapter') ?: $this->detectChapter($title);

        // A revision of a file for no line is the next version of that file
        // whatever the new copy is called: it takes the file's chapter, or
        // its name when it has none, which is what versions are grouped by.
        if ($revises) {
            $chapter = $revises->chapter ?: null;
            $title = $chapter ? $title : $revises->file_name;
        }

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
        $lastVersion = CapstoneDocument::ofTeam((int) $studentYear->team_id)
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
            'team_id' => $studentYear->team_id,
            'file_name' => $title,
            'chapter' => $chapter,
            'deliverable_id' => $item?->id,
            'file_path' => $storedPath,
            'diagram_files' => collect($isDiagram ? [] : $request->file('diagrams', []))
                ->map(fn ($diagram) => ['name' => $diagram->getClientOriginalName(), 'path' => $diagram->store('submissions/diagrams', 'local')])
                ->values()->all() ?: ($isDiagram ? [['name' => $file->getClientOriginalName(), 'path' => $storedPath]] : null),
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

        foreach ($targets as $role => $expertIds) {
            foreach ($expertIds as $expertId) {
                $review = CapstoneDocumentReview::create([
                    'file_id' => $document->file_id,
                    'expert_id' => $expertId,
                    'role' => $role,
                    'review_status' => 'To Review',
                    'version' => (string) $document->file_version,
                ]);

                // No email has ever gone out for a submission - the expert found
                // out by opening Document Review. This is the first time they are
                // actually told.
                Notifier::toExpert(
                    (int) $expertId,
                    'document_submitted',
                    'Document to review',
                    '<strong>'.e($team->team_name ?? 'A team').'</strong> sent you <strong>'
                        .e($document->file_name).'</strong> as their '.e(SubmissionRecipients::LABELS[$role]).'.',
                    route('instructor.docreview'),
                    ['team_id' => $studentYear->team_id, 'review_id' => $review->review_id],
                );

                $results[] = [
                    'role' => $role,
                    'label' => SubmissionRecipients::LABELS[$role],
                    'name' => $review->expert?->name,
                ];
            }
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
    /**
     * Warnings about a draft before it is sent: its first page names another
     * chapter, or it shares almost nothing with the team's last upload for
     * the same line - Chapter 3 sent as the next Chapter 1. Only warnings; the
     * student may send it anyway. See RevisionCheck.
     */
    public function warnings(Request $request): JsonResponse
    {
        // revises: the upload a "Submit revision" in the Review Viewer is the
        // next version of, for one that was never sent for a checklist line.
        $request->validate([
            'file' => 'required|file|max:25600',
            'deliverable_id' => 'required_without:revises|nullable|integer',
            'revises' => 'required_without:deliverable_id|nullable|integer',
        ]);

        $file = $request->file('file');
        $item = $request->filled('deliverable_id') ? Deliverable::active()->find($request->integer('deliverable_id')) : null;
        $teamId = StudentTeam::idFor(Auth::id());

        if ($teamId === null) {
            return response()->json(['warnings' => [], 'same' => null]);
        }

        if (! $item) {
            $document = $request->filled('revises')
                ? CapstoneDocument::ofTeam($teamId)->find($request->integer('revises'))
                : null;

            if (! $document) {
                return response()->json(['warnings' => [], 'same' => null]);
            }

            $same = $this->sameAsAny($file->getRealPath(), $document->earlierVersions()->prepend($document));

            return response()->json([
                'warnings' => ! $same && strtolower($file->getClientOriginalExtension()) === 'pdf'
                    ? RevisionCheck::warningsForRevisionOf($file->getRealPath(), $file->getClientOriginalName(), $document)
                    : [],
                'same' => $same,
            ]);
        }

        // The very file that was sent back: never worth sending again.
        $same = $this->sameAsLast($file->getRealPath(), $item, $teamId);

        return response()->json([
            'warnings' => strtolower($file->getClientOriginalExtension()) === 'pdf'
                ? RevisionCheck::warnings($file->getRealPath(), $file->getClientOriginalName(), $item, $teamId)
                : [],
            'same' => $same,
        ]);
    }

    /**
     * Everything the upload pop-up needs for one line: what it is, which
     * version this would be, who it goes to, and - for a revision - what the
     * reviewer asked for. With no line, only the lines the team may pick.
     */
    public function plan(Request $request): JsonResponse
    {
        $studentYear = DB::table('student_s_y')
            ->where('student_id', Auth::id())
            ->orderByDesc('school_year')
            ->first();
        $teamId = $studentYear?->team_id ? (int) $studentYear->team_id : null;

        if (! $teamId) {
            return response()->json(['message' => 'You are not currently assigned to a team.'], 422);
        }

        $lines = $this->openLines($teamId);
        $item = $request->filled('item') ? Deliverable::active()->find($request->integer('item')) : null;

        if ($item && ! $lines->contains('id', $item->id)) {
            $item = null;
        }

        $line = $item ? $lines->firstWhere('id', $item->id) : null;

        if ($line?->locked) {
            return response()->json(['message' => $item->title." can't be handed in yet: it waits for ".$line->waits_for.'.'], 422);
        }

        $isPaper = $item && in_array($item->kind, Deliverable::CHECKED_BY_CAPSA, true);
        $last = $item ? CapstoneDocument::ofTeam($teamId)->where('deliverable_id', $item->id)->orderByDesc('file_version')->first() : null;
        $version = ($last?->file_version ?? 0) + 1;
        // A revision of a file for no line: the upload it revises.
        $revises = ! $item && $request->filled('revises')
            ? CapstoneDocument::ofTeam($teamId)->find($request->integer('revises'))
            : null;

        // A revision of it goes up as the next version of that paper: counted
        // the way store() counts it, by its chapter, else its name.
        if ($revises) {
            $version = (CapstoneDocument::ofTeam($teamId)
                ->when(
                    $revises->chapter,
                    fn ($query) => $query->where('chapter', $revises->chapter),
                    fn ($query) => $query->where('file_name', $revises->file_name)
                )
                ->max('file_version') ?? 0) + 1;
        }

        $plan = $isPaper ? ['locked' => true, 'people' => []] : $this->recipients->plan($item, $teamId, $revises);

        if ($isPaper) {
            $capsaId = $this->resolver->resolveSubjectAdviserAdmin($teamId);
            $capsa = $capsaId ? CapstoneExpert::find($capsaId) : null;
            $plan['people'][] = [
                'role' => 'subject_adviser', 'label' => 'CaPSA', 'name' => $capsa?->name,
                'note' => null, 'on' => true, 'available' => true,
            ];
        }

        return response()->json([
            'item' => $item ? [
                'id' => $item->id,
                'title' => $item->title,
                'phase' => $line?->phase,
                'chapter' => SubmissionRecipients::chapterKey($item),
                'due' => $item->due_date?->format('M j, Y'),
                'paper' => $isPaper,
                'instructions' => $item->instructions,
            ] : null,
            'version' => $item || $revises ? $version : null,
            // The paper a revision of a file for no line is the next version of.
            'paper' => $revises ? ($revises->chapter ?: $revises->file_name) : null,
            'round' => $version > 1 ? self::ordinal($version - 1).' revision' : null,
            'accept' => $isPaper ? ['pdf', 'jpg', 'jpeg', 'png'] : ['pdf', ...self::DIAGRAM_TYPES],
            'locked' => $plan['locked'],
            'people' => array_map(fn ($p) => array_diff_key($p, ['expert_ids' => true]), $plan['people']),
            'remark' => ($last ?? $revises) && ! $isPaper ? RevisionRemark::on($last ?? $revises, 160) : null,
            'lines' => $item || $revises ? [] : $lines->reject(fn ($l) => $l->locked)->values()->map(fn ($l) => [
                'id' => $l->id, 'title' => $l->title, 'phase' => $l->phase, 'note' => $l->note,
            ]),
        ]);
    }

    /**
     * The version of this line the file is identical to, if any.
     */
    private function sameAsLast(string $path, Deliverable $item, int $teamId): ?int
    {
        return $this->sameAsAny($path, CapstoneDocument::ofTeam($teamId)
            ->where('deliverable_id', $item->id)
            ->orderByDesc('file_version')
            ->get(['file_path', 'file_version']));
    }

    /**
     * The version among $earlier the file is identical to, if any.
     *
     * @param  iterable<int, CapstoneDocument>  $earlier
     */
    private function sameAsAny(string $path, iterable $earlier): ?int
    {
        $hash = @sha1_file($path);

        if (! $hash) {
            return null;
        }

        foreach ($earlier as $document) {
            if ($document->file_path && Storage::disk('local')->exists($document->file_path)
                && sha1_file(Storage::disk('local')->path($document->file_path)) === $hash) {
                return (int) $document->file_version;
            }
        }

        return null;
    }

    public function nextVersionForTitle(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);

        $studentId = Auth::id();
        $title = $request->input('title');
        $chapter = $this->detectChapter($title);

        // Counted across the whole team, not the one account uploading. A
        // chapter is the team's, so two members each sending "Chapter 1" were
        // both told they were on Version 1 of it.
        $lastVersion = CapstoneDocument::forTeamOf($studentId)
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

    /**
     * Document Submission is part of Documents now. Opened with ?item=, the
     * pop-up still opens on that line straight away.
     */
    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('student.documents', array_filter(['item' => (int) $request->query('item') ?: null]));
    }

    /**
     * Takes an upload back, as in Google Classroom: gone as if never sent, so
     * the next upload of the line gets the same version number. Silent - no
     * one is told, and copies already in a reviewer's Drive stay.
     */
    public function unsubmit(CapstoneDocument $document): RedirectResponse
    {
        abort_unless(CapstoneDocument::forTeamOf(Auth::id())->whereKey($document->file_id)->exists(), 404);

        if (! $document->canBeUnsubmitted()) {
            return back()->with('error', 'A reviewer has already started on this, so it can no longer be unsubmitted.');
        }

        // No transactions on these tables: reviews first, so a failure part
        // way leaves at worst an upload nobody is asked to review.
        $document->reviews()->delete();
        $document->delete();
        Storage::disk('local')->delete([$document->file_path, ...array_column($document->diagram_files ?? [], 'path')]);

        return back()->with('status', $document->file_name.' was unsubmitted.');
    }

    /** A Visio drawing is a zip holding visio/document.xml. */
    /** Whether this is a draw.io or Visio file that opens, by what is in it rather than its name. */
    private function isDiagramFile(UploadedFile $file): bool
    {
        return match (strtolower($file->getClientOriginalExtension())) {
            'drawio' => (bool) preg_match('/<(mxfile|mxGraphModel)\b/', (string) file_get_contents($file->getRealPath(), false, null, 0, 4096)),
            'vsdx' => $this->isVisioFile($file->getRealPath()),
            default => false,
        };
    }

    private function isVisioFile(string $path): bool
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return false;
        }

        $hasDocument = $zip->locateName('visio/document.xml') !== false;
        $zip->close();

        return $hasDocument;
    }

    /**
     * The team's checklist lines still open, each with its own lock: its
     * milestone's, or the order inside the milestone. Defenses and the title
     * form are never uploaded.
     *
     * @return Collection<int, object>
     */
    private function openLines(int $teamId): Collection
    {
        $progress = Team::find($teamId)?->checklistProgress();

        if (! $progress) {
            return collect();
        }

        return $progress->milestones
            ->flatMap(fn ($ms) => $ms->items
                ->reject(fn ($row) => $row->item->kind === Deliverable::DEFENSE
                    || $row->item->closesOnTitleRecommendation()
                    || $row->item->closesOnArchiveEntry()
                    || in_array($row->state, MilestoneProgress::COMPLETE_STATES, true))
                ->map(fn ($row) => (object) [
                    'id' => $row->item->id,
                    'title' => $row->item->title,
                    'phase' => $ms->milestone->name,
                    'locked' => $row->locked,
                    'waits_for' => $row->waits_for,
                    'note' => match (true) {
                        (bool) $row->note => 'sent back: '.$row->note,
                        $row->changes_requested => self::ordinal($row->revision).' revision requested',
                        $row->waiting_on === MilestoneProgress::WAITING_ON_SIGNED_COPY => 'approved, upload the signed copy',
                        default => null,
                    },
                ]))
            ->values();
    }

    private static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th');

        return $n.$suffix;
    }
}
