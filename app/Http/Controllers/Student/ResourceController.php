<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\Resource;
use App\Models\Student;
use App\Support\GradingSheet;
use App\Support\GradingSheetWorkbook;
use App\Support\StudentResources;
use App\Support\StudentTeam;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceController extends Controller
{
    /** The categories a CaPSA files under, as the page names them. */
    public const CATEGORIES = [
        'pre-proposal' => 'Pre-proposal',
        'ch1-3' => 'Chapters 1–3',
        'ch4-5' => 'Chapters 4–5',
        'final' => 'Final manuscript',
        'presentation' => 'Presentation',
        'lesson' => 'Lessons',
        'forms' => 'Forms',
        'other' => 'Other',
    ];

    public function __construct(private StudentResources $resources) {}

    public function index(): View
    {
        /** @var Student $student */
        $student = auth()->user();
        $program = $this->resources->program($student);
        $section = $this->resources->section($student);

        $files = $this->resources->files($student)->get()->map(fn (Resource $r) => [
            'key' => 'f'.$r->resource_id,
            'kind' => 'file',
            'title' => $r->title ?: ($r->file_name ?: 'Untitled'),
            'category' => self::categoryKey($r->category),
            'categoryLabel' => self::categoryLabel($r->category),
            'type' => self::typeLabel($r),
            'size' => self::size($r->file_size),
            'date' => $r->uploaded_at?->format('M j'),
            'sort' => $r->uploaded_at?->timestamp ?? 0,
            'isNew' => $r->uploaded_at !== null && $r->uploaded_at->gt(now()->subWeek()),
            'description' => $r->description,
            'downloadUrl' => $r->is_downloadable !== false && ($r->drive_file_id || $r->external_url)
                ? route('student.resources.download', $r)
                : null,
            'previewUrl' => $r->drive_file_id ? 'https://drive.google.com/file/d/'.$r->drive_file_id.'/preview' : null,
            'openUrl' => $r->drive_web_view_link ?: $r->external_url,
        ]);

        $rubrics = $this->resources->rubrics($program)->map(fn (array $row) => [
            'key' => 'r'.$row['rubric']->rubric_id,
            'kind' => 'rubric',
            'title' => $row['stage'].' Rubric',
            'category' => 'rubrics',
            'categoryLabel' => 'Rubrics',
            'type' => 'PDF',
            'size' => null,
            'date' => $row['rubric']->updated_at?->format('M j'),
            'sort' => $row['rubric']->updated_at?->timestamp ?? 0,
            'isNew' => false,
            'description' => 'The same sheet your panel scores you with at the '.$row['stage'].'. Read it before you defend.',
            'forProgram' => $row['rubric']->program ? $row['rubric']->program.' version' : 'For all programs',
            'forWho' => $row['rubric']->program ?: 'All programs',
            'points' => GradingSheet::points((float) $row['rubric']->total_points),
            'downloadUrl' => route('student.resources.rubric', $row['slug']),
            'previewUrl' => route('student.resources.rubric', ['stage' => $row['slug'], 'view' => 1]),
            'openUrl' => null,
        ]);

        $items = $files->concat($rubrics)->sortByDesc('sort')->values();

        // Only the categories something is filed under, in the page's order.
        $present = $items->pluck('category')->unique();
        $categories = collect(self::CATEGORIES + ['rubrics' => 'Rubrics'])
            ->filter(fn ($label, $key) => $present->contains($key))
            ->merge($items->whereNotIn('category', array_keys(self::CATEGORIES + ['rubrics' => '']))->pluck('categoryLabel', 'category'));

        return view('student.resources', [
            'items' => $items,
            'categories' => $categories,
            'program' => $program,
            'section' => $section,
        ]);
    }

    /**
     * Hand over a file the student may see, and count it.
     *
     * The file lives in the CaPSA's Google Drive; this sends the browser to
     * Drive's own download. A file the student cannot see is not found, the
     * same answer a file that does not exist gets.
     */
    public function download(Resource $resource): RedirectResponse
    {
        /** @var Student $student */
        $student = auth()->user();

        abort_unless($this->resources->files($student)->whereKey($resource->getKey())->exists(), 404);
        abort_if($resource->is_downloadable === false, 404);

        $url = $resource->drive_file_id
            ? 'https://drive.google.com/uc?export=download&id='.urlencode($resource->drive_file_id)
            : $resource->external_url;

        abort_unless($url, 404);

        $resource->increment('downloads');

        return redirect()->away($url);
    }

    /**
     * The blank rubric for a defense stage, as the department's own form.
     *
     * The one for the student's program, else the stage's rubric for every
     * program - whichever their panel grades with. ?view=1 shows it in the
     * page instead of saving it.
     */
    public function rubric(Request $request, string $stage, GradingSheet $sheets): Response
    {
        /** @var Student $student */
        $student = auth()->user();
        $row = $this->resources->rubric($stage, $this->resources->program($student));

        abort_unless($row, 404);

        $disposition = $request->boolean('view') ? 'inline' : 'attachment';

        return response(self::pdf($sheets->blank($row['rubric'], $row['stage'])), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$row['stage'].' Rubric.pdf"',
        ]);
    }

    /**
     * The team's rating sheet for one of its defenses, filled in - title,
     * date, team and the panel's names - for the team to print, one for each
     * panelist, and bring to the defense. If CapsTrack cannot be reached
     * there, the panel grades on paper and enters the scores later.
     */
    public function sheet(DefenseSchedule $defense, string $format, GradingSheet $sheets): Response|StreamedResponse
    {
        /** @var Student $student */
        $student = auth()->user();

        $teamId = StudentTeam::idFor($student);

        abort_unless($teamId && (int) $defense->team_id === $teamId, 404);

        $rubric = GradingRubric::forStage($defense->defense_type, $defense->team?->program());

        abort_unless($rubric, 404);

        $sheet = $sheets->for($defense, $rubric);

        if ($format === 'xlsx') {
            $book = (new GradingSheetWorkbook)->build($sheet);

            return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), GradingSheet::fileName($sheet, 'xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response(self::pdf($sheet), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.GradingSheet::fileName($sheet, 'pdf').'"',
        ]);
    }

    /** The department's rubric form as a PDF: letter, landscape, "Page 1 of 2" centred at the foot. */
    private static function pdf(array $sheet): string
    {
        $dompdf = Pdf::loadView('instructor.pdf.grading-sheet', ['sheet' => $sheet])
            ->setPaper('letter', 'landscape')
            ->getDomPDF();
        $dompdf->render();

        // CSS cannot count the pages in DomPDF, so they are written on once they exist.
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() / 2 - 24, $canvas->get_height() - 36, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('Times-Roman'), 10);

        return $dompdf->output();
    }

    /** The category's key: the CaPSA's choice, or an older free-text one made into one. */
    private static function categoryKey(?string $category): string
    {
        $key = strtolower(trim((string) $category));

        return $key === '' ? 'other' : $key;
    }

    private static function categoryLabel(?string $category): string
    {
        $key = self::categoryKey($category);

        return self::CATEGORIES[$key] ?? ucwords(str_replace(['-', '_'], ' ', $key));
    }

    /** "PDF", "DOCX" - from the extension, or the file name when there is none. */
    private static function typeLabel(Resource $resource): string
    {
        $ext = $resource->file_type ?: pathinfo((string) $resource->file_name, PATHINFO_EXTENSION);

        return match (strtolower((string) $ext)) {
            'doc', 'docx', 'gdoc' => 'DOCX',
            'ppt', 'pptx', 'gslides' => 'PPTX',
            'pdf' => 'PDF',
            '' => $resource->external_url ? 'LINK' : 'FILE',
            default => strtoupper((string) $ext),
        };
    }

    private static function size(?int $bytes): ?string
    {
        if (! $bytes) {
            return null;
        }

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, round($bytes / 1024)).' KB';
    }
}
