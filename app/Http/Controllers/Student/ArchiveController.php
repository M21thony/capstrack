<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ArchivedDocTag;
use App\Models\ArchivedDocument;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use App\Support\DefenseProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    /**
     * The stage that ends the journey.
     *
     * Passing this is what makes a team eligible to hand in its manuscript -
     * read from the recorded outcome rather than a stored flag, the same way
     * Team::isTeamLocked() reads the Title Defense pass.
     */
    private const LAST_STAGE = 'Final Oral Defense';

    /** How big a manuscript may be. The same ceiling staff uploads use. */
    private const MAX_UPLOAD_MB = 20;

    public function index(): View
    {
        $student = Auth::user();
        $team = $this->teamFor($student);

        // Only what the archive actually holds: staff uploads and submissions
        // the admin has published. A team's own waiting manuscript is shown in
        // the submission panel instead, so it cannot be mistaken for a record
        // that is already in.
        $archives = ArchivedDocument::with('tags')
            ->published()
            ->orderByDesc('academic_year')
            ->get();

        $tags = ArchivedDocTag::orderBy('tag_name')->get();

        return view('student.archive', [
            'archives' => $archives,
            'tags' => $tags,
            'submission' => $this->submissionState($student, $team),
        ]);
    }

    /**
     * A team that has finished its capstone handing in the manuscript.
     *
     * The row lands in archived_documents as pending, because an archived
     * capstone is the public record and similarity checks are read against it.
     * An unreviewed PDF that appears there on its own would be in that record
     * before anybody had looked at it.
     */
    public function submit(Request $request): RedirectResponse
    {
        $student = Auth::user();
        $team = $this->teamFor($student);

        if (! $team) {
            return back()->with('error', 'You need a team before you can submit a manuscript.');
        }

        if ($team->leader_id !== $student->student_id) {
            return back()->with('error', 'Only the team leader can submit the Capstone Manuscript.');
        }

        if (! app(DefenseProgress::class)->hasPassed($team, self::LAST_STAGE)) {
            return back()->with('error', 'Your manuscript can be submitted once your team has passed the Final Oral Defense.');
        }

        if ($this->submissionFor($team->team_id)) {
            return back()->with('error', 'Your team has already submitted its Capstone Manuscript.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['required', 'string'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.(self::MAX_UPLOAD_MB * 1024)],
        ], [
            'file.mimes' => 'The manuscript has to be a PDF.',
            'file.max' => 'The manuscript may not be larger than '.self::MAX_UPLOAD_MB.' MB.',
        ]);

        $file = $request->file('file');
        $path = $file->store('archived-manuscripts', 'public');

        ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => $data['title'],
            'abstract' => $data['abstract'],
            'authors' => $this->authorsFor($team),
            'academic_year' => $this->academicYearFor($team),
            'program' => $this->programFor($team),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'submitted_by' => $student->student_id,
            'submitted_at' => now(),
            'submission_status' => ArchivedDocument::PENDING,
        ]);

        return back()->with('success', 'Manuscript submitted. It goes into the archive once your CaPSA or the admin has reviewed it.');
    }

    private function teamFor(Student $student): ?Team
    {
        return StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->first()?->team;
    }

    /** What the team has already handed in, if anything. */
    private function submissionFor(int $teamId): ?ArchivedDocument
    {
        return ArchivedDocument::where('team_id', $teamId)
            ->whereNotNull('submitted_by')
            ->orderByDesc('archived_id')
            ->first();
    }

    /**
     * What the archive page shows about submitting, decided here rather than
     * in the view so the page and the action behind it cannot disagree.
     *
     * @return array{team: Team|null, isLeader: bool, isFinished: bool, existing: ArchivedDocument|null, defaultTitle: string, authors: string, year: int, program: string}
     */
    private function submissionState(Student $student, ?Team $team): array
    {
        return [
            'team' => $team,
            'isLeader' => $team !== null && $team->leader_id === $student->student_id,
            'isFinished' => $team !== null && app(DefenseProgress::class)->hasPassed($team, self::LAST_STAGE),
            'existing' => $team ? $this->submissionFor($team->team_id) : null,
            'defaultTitle' => $team ? $this->titleFor($team) : '',
            'authors' => $team ? $this->authorsFor($team) : '',
            'year' => $team ? $this->academicYearFor($team) : (int) now()->year,
            'program' => $team ? $this->programFor($team) : '',
        ];
    }

    /**
     * The title the team is defending, used to prefill the form.
     *
     * The one the adviser approved is the settled title; a team whose
     * approved proposal was retired by a new-title redefense has none, so
     * their current proposal is the best answer available.
     */
    private function titleFor(Team $team): string
    {
        $proposals = $team->preProposals()->orderBy('slot')->get();

        return (string) ($proposals->firstWhere('approved_status', true)?->title
            ?? $proposals->first()?->title
            ?? '');
    }

    /**
     * Every member of the team, as the archive records an author.
     *
     * "Dela Cruz, J., Santos, M." - the shape the admin's own Add Capstone
     * form asks for, so a team submission and a handwritten one read the same.
     */
    private function authorsFor(Team $team): string
    {
        return StudentSchoolYear::where('team_id', $team->team_id)
            ->with('student')
            ->get()
            ->map(fn ($sy) => $sy->student)
            ->filter()
            ->map(function (Student $member) {
                $last = trim((string) $member->l_name);
                $initial = Str::substr(trim((string) $member->f_name), 0, 1);

                return $last !== '' ? $last.', '.($initial !== '' ? $initial.'.' : '') : $member->email;
            })
            ->implode(', ');
    }

    /** The academic year the team is enrolled in, as the archive's four digits. */
    private function academicYearFor(Team $team): int
    {
        $schoolYear = StudentSchoolYear::where('team_id', $team->team_id)->value('school_year')
            ?? Term::currentSchoolYear();

        // The column is a YEAR, and an enrolment row with an empty or
        // unrecognisable school year would otherwise write 0 and be refused.
        $year = (int) Str::before((string) $schoolYear, '-');

        return $year >= 1901 && $year <= 2155 ? $year : (int) now()->year;
    }

    /**
     * The program code (BSIT, BSCS, ...) the archive groups by.
     *
     * Read off the members rather than the enrolment row's program column,
     * which is often empty - the same source SectionScope builds a section
     * label from.
     */
    private function programFor(Team $team): string
    {
        $courses = StudentSchoolYear::where('team_id', $team->team_id)
            ->with('student')
            ->get()
            ->map(fn ($sy) => $sy->student?->course)
            ->filter();

        foreach ($courses as $course) {
            if ($program = Student::mapCourseToProgram($course)) {
                return $program;
            }
        }

        return '';
    }
}
