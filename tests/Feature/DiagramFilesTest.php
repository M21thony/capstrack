<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A team may send the draw.io files of its diagrams with a document, so the
 * reviewer comparing two versions is shown exactly what changed in them.
 */
class DiagramFilesTest extends TestCase
{
    use RefreshDatabase;

    private const DRAWIO = '<mxfile host="app.diagrams.net"><diagram id="a" name="Page-1"><mxGraphModel><root><mxCell id="0"/></root></mxGraphModel></diagram></mxfile>';

    private Team $team;

    private Student $student;

    /** A Visio drawing: a zip holding visio/document.xml, or a zip without one. */
    private function visio(string $name, bool $real = true): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vsdx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString($real ? 'visio/document.xml' : 'word/document.xml', '<VisioDocument/>');
        $zip->close();

        return new UploadedFile($path, $name, 'application/vnd.ms-visio.drawing', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->team = Team::create(['team_name' => 'Team Alpha', 'team_status' => 'Active']);
        $this->student = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $this->team->team_id, 'program' => 'BSIT']);
    }

    /** An approved adviser of the team, for uploads to go to. */
    private function adviser(): CapstoneExpert
    {
        $roleId = DB::table('roles')->insertGetId(['role_name' => 'adviser', 'created_at' => now(), 'updated_at' => now()]);
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        DB::table('expert_roles')->insert(['expert_id' => $expert->expert_id, 'role_id' => $roleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $this->team->team_id, 'expert_id' => $expert->expert_id,
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $expert->expert_id)->value('expert_role_id'),
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $expert;
    }

    private function send(array $diagrams): TestResponse
    {
        return $this->actingAs($this->student)->post(route('student.submission.store'), [
            'file' => UploadedFile::fake()->create('Chapter 3.pdf', 100, 'application/pdf'),
            'recipients' => ['adviser'],
            'diagrams' => $diagrams,
        ]);
    }

    /** A version of Chapter 3 with these diagram files, and its review. */
    private function version(int $number, ?array $diagrams, CapstoneExpert $expert, ?Student $by = null): CapstoneDocumentReview
    {
        $document = CapstoneDocument::create([
            'student_id' => ($by ?? $this->student)->student_id, 'file_name' => 'Chapter 3', 'chapter' => 'Chapter 3',
            'file_path' => "submissions/ch3-v{$number}.pdf", 'file_version' => $number, 'document_status' => 'Pending',
            'diagram_files' => $diagrams,
        ]);

        foreach ($diagrams ?? [] as $file) {
            Storage::disk('local')->put($file['path'], self::DRAWIO);
        }

        return CapstoneDocumentReview::create([
            'file_id' => $document->file_id, 'expert_id' => $expert->expert_id, 'role' => 'adviser',
            'review_status' => CapstoneDocumentReview::AWAITING, 'version' => (string) $number,
        ]);
    }

    public function test_drawio_files_sent_with_a_document_are_kept_with_it(): void
    {
        $this->adviser();

        $this->send([
            UploadedFile::fake()->createWithContent('dfd-level-1.drawio', self::DRAWIO),
            UploadedFile::fake()->createWithContent('erd.drawio', self::DRAWIO),
        ])->assertOk()->assertJson(['success' => true]);

        $files = CapstoneDocument::first()->diagram_files;
        $this->assertSame(['dfd-level-1.drawio', 'erd.drawio'], array_column($files, 'name'));
        foreach ($files as $file) {
            Storage::disk('local')->assertExists($file['path']);
        }
    }

    public function test_visio_files_are_taken_and_given_to_the_viewer_as_base64(): void
    {
        $this->adviser();

        $this->send([$this->visio('dfd.vsdx'), UploadedFile::fake()->createWithContent('erd.drawio', self::DRAWIO)])->assertOk();

        $document = CapstoneDocument::first();
        $this->assertSame(['dfd.vsdx', 'erd.drawio'], array_column($document->diagram_files, 'name'));
        $visio = Storage::disk('local')->get($document->diagram_files[0]['path']);
        $this->assertSame([['name' => 'dfd.vsdx', 'vsdx' => base64_encode($visio)], ['name' => 'erd.drawio', 'xml' => self::DRAWIO]], $document->diagrams());
    }

    public function test_a_file_named_vsdx_that_is_not_a_visio_drawing_is_refused(): void
    {
        $this->adviser();

        // A zip of something else, and not a zip at all.
        $this->send([$this->visio('dfd.vsdx', real: false)])->assertSessionHasErrors('diagrams.0');
        $this->send([UploadedFile::fake()->createWithContent('dfd.vsdx', self::DRAWIO)])->assertSessionHasErrors('diagrams.0');

        $this->assertSame(0, CapstoneDocument::count());
    }

    public function test_a_document_without_diagrams_keeps_none(): void
    {
        $this->adviser();

        $this->send([])->assertOk();

        $this->assertNull(CapstoneDocument::first()->diagram_files);
    }

    public function test_only_drawio_files_are_taken(): void
    {
        $this->adviser();

        // Another extension, and a file named .drawio that is not one.
        $this->send([UploadedFile::fake()->createWithContent('dfd.xml', self::DRAWIO)])->assertSessionHasErrors('diagrams.0');
        $this->send([UploadedFile::fake()->createWithContent('dfd.drawio', 'not a diagram')])->assertSessionHasErrors('diagrams.0');

        $this->assertSame(0, CapstoneDocument::count());
    }

    public function test_no_more_than_twenty_and_none_over_five_megabytes(): void
    {
        $this->adviser();

        $this->send(array_map(fn (int $i) => UploadedFile::fake()->createWithContent("d{$i}.drawio", self::DRAWIO), range(1, 21)))
            ->assertSessionHasErrors('diagrams');
        $this->send([UploadedFile::fake()->create('big.drawio', 5121)])->assertSessionHasErrors('diagrams.0');

        $this->assertSame(0, CapstoneDocument::count());
    }

    public function test_the_reviewer_gets_the_diagrams_of_this_version_and_the_earlier_one(): void
    {
        $expert = $this->adviser();
        $first = $this->version(1, [['name' => 'dfd.drawio', 'path' => 'submissions/diagrams/v1.drawio']], $expert);
        $current = $this->version(2, [['name' => 'dfd.drawio', 'path' => 'submissions/diagrams/v2.drawio']], $expert);

        $this->actingAs($expert, 'expert')->getJson(route('reviews.diagrams', $current->review_id))
            ->assertOk()->assertExactJson([['name' => 'dfd.drawio', 'xml' => self::DRAWIO]]);
        $this->actingAs($expert, 'expert')->getJson(route('reviews.versions.diagrams', [$current->review_id, $first->file_id]))
            ->assertOk()->assertExactJson([['name' => 'dfd.drawio', 'xml' => self::DRAWIO]]);

        // The annotator is told where to find them.
        $this->actingAs($expert, 'expert')->get(route('instructor.docreview.annotate', $current->review_id))
            ->assertOk()
            ->assertViewHas('context', fn (array $context) => $context['diagrams'] === route('reviews.diagrams', $current->review_id)
                && $context['versions'][0]['diagrams'] === route('reviews.versions.diagrams', [$current->review_id, $first->file_id]));
    }

    public function test_a_version_without_diagrams_gives_the_annotator_no_link(): void
    {
        $expert = $this->adviser();
        $this->version(1, null, $expert);
        $current = $this->version(2, null, $expert);

        $this->actingAs($expert, 'expert')->get(route('instructor.docreview.annotate', $current->review_id))
            ->assertOk()
            ->assertViewHas('context', fn (array $context) => $context['diagrams'] === null && $context['versions'][0]['diagrams'] === null);
        $this->actingAs($expert, 'expert')->getJson(route('reviews.diagrams', $current->review_id))
            ->assertOk()->assertExactJson([]);
    }

    public function test_another_reviewer_and_another_teams_files_are_kept_out(): void
    {
        $expert = $this->adviser();
        $current = $this->version(2, [['name' => 'dfd.drawio', 'path' => 'submissions/diagrams/v2.drawio']], $expert);

        $stranger = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->actingAs($stranger, 'expert')->getJson(route('reviews.diagrams', $current->review_id))->assertForbidden();

        $outsider = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $outsider->student_id, 'team_id' => Team::create(['team_name' => 'Bravo', 'team_status' => 'Active'])->team_id]);
        $elsewhere = $this->version(1, [['name' => 'theirs.drawio', 'path' => 'submissions/diagrams/theirs.drawio']], $stranger, $outsider);

        $this->actingAs($expert, 'expert')
            ->getJson(route('reviews.versions.diagrams', [$current->review_id, $elsewhere->file_id]))->assertNotFound();
    }

    public function test_the_team_gets_the_diagrams_of_its_reviewed_document_and_no_other_team_does(): void
    {
        $review = $this->version(1, [['name' => 'dfd.drawio', 'path' => 'submissions/diagrams/v1.drawio']], $this->adviser());
        $review->update(['review_status' => CapstoneDocumentReview::REVIEWED, 'reviewed_at' => now()]);

        $this->actingAs($this->student, 'web')->getJson(route('student.review.diagrams', $review->review_id))
            ->assertOk()->assertExactJson([['name' => 'dfd.drawio', 'xml' => self::DRAWIO]]);

        // The review page tells the viewer where to find them.
        $this->actingAs($this->student, 'web')->followingRedirects()->get(route('student.review', ['review_id' => $review->review_id]))
            ->assertOk()->assertSee('data-diagrams-url="'.route('student.review.diagrams', $review->review_id).'"', false);

        $outsider = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $outsider->student_id, 'team_id' => Team::create(['team_name' => 'Bravo', 'team_status' => 'Active'])->team_id]);
        $this->actingAs($outsider, 'web')->getJson(route('student.review.diagrams', $review->review_id))->assertForbidden();
    }

    public function test_a_reviewed_document_without_diagrams_gives_the_student_viewer_no_link(): void
    {
        $review = $this->version(1, null, $this->adviser());
        $review->update(['review_status' => CapstoneDocumentReview::REVIEWED, 'reviewed_at' => now()]);

        $this->actingAs($this->student, 'web')->followingRedirects()->get(route('student.review', ['review_id' => $review->review_id]))
            ->assertOk()->assertSee('data-diagrams-url=""', false);
    }

    public function test_a_diagram_is_sent_as_the_document_itself_with_no_pdf(): void
    {
        $this->adviser();

        foreach ([UploadedFile::fake()->createWithContent('dfd-level-1.drawio', self::DRAWIO), $this->visio('erd.vsdx')] as $diagram) {
            $this->actingAs($this->student)->post(route('student.submission.store'), ['file' => $diagram, 'recipients' => ['adviser']])
                ->assertOk()->assertJson(['success' => true]);
        }

        [$drawio, $visio] = CapstoneDocument::orderBy('file_id')->get()->all();
        $this->assertTrue($drawio->isDiagram());
        $this->assertSame([['name' => 'dfd-level-1.drawio', 'path' => $drawio->file_path]], $drawio->diagram_files);
        $this->assertSame([['name' => 'dfd-level-1.drawio', 'xml' => self::DRAWIO]], $drawio->diagrams());
        $this->assertTrue($visio->isDiagram());
        $this->assertSame('erd.vsdx', $visio->diagrams()[0]['name']);
        $this->assertArrayHasKey('vsdx', $visio->diagrams()[0]);
    }

    public function test_a_file_named_as_a_diagram_that_is_not_one_is_refused_as_the_document(): void
    {
        $this->adviser();

        $this->actingAs($this->student)->post(route('student.submission.store'), [
            'file' => UploadedFile::fake()->createWithContent('dfd.drawio', 'not a diagram'),
            'recipients' => ['adviser'],
        ])->assertStatus(422)->assertJson(['success' => false]);
        $this->actingAs($this->student)->post(route('student.submission.store'), [
            'file' => $this->visio('erd.vsdx', real: false),
            'recipients' => ['adviser'],
        ])->assertStatus(422);

        $this->assertSame(0, CapstoneDocument::count());
    }

    public function test_a_pdf_with_diagrams_is_not_read_as_a_diagram(): void
    {
        $review = $this->version(1, [['name' => 'dfd.drawio', 'path' => 'submissions/diagrams/v1.drawio']], $this->adviser());

        $this->assertFalse($review->document->isDiagram());
    }

    public function test_the_annotator_reads_a_diagram_sent_as_itself_with_no_pdf(): void
    {
        $expert = $this->adviser();
        $first = $this->version(1, [['name' => 'dfd.drawio', 'path' => 'submissions/ch3-v1.pdf']], $expert);
        $current = $this->version(2, [['name' => 'dfd.drawio', 'path' => 'submissions/ch3-v2.pdf']], $expert);

        $this->actingAs($expert, 'expert')->get(route('instructor.docreview.annotate', $current->review_id))
            ->assertOk()
            ->assertViewHas('context', fn (array $context) => $context['diagramOnly'] === true
                && $context['diagrams'] === route('reviews.diagrams', $current->review_id)
                && $context['versions'][0]['file'] === null
                && $context['versions'][0]['diagrams'] === route('reviews.versions.diagrams', [$current->review_id, $first->file_id]));
        $this->actingAs($expert, 'expert')->getJson(route('reviews.diagrams', $current->review_id))
            ->assertOk()->assertExactJson([['name' => 'dfd.drawio', 'xml' => self::DRAWIO]]);
    }

    public function test_the_annotator_reads_a_pdf_as_a_pdf(): void
    {
        $expert = $this->adviser();
        $this->version(1, null, $expert);
        $current = $this->version(2, [['name' => 'dfd.drawio', 'path' => 'submissions/diagrams/v2.drawio']], $expert);

        $this->actingAs($expert, 'expert')->get(route('instructor.docreview.annotate', $current->review_id))
            ->assertOk()
            ->assertViewHas('context', fn (array $context) => $context['diagramOnly'] === false && $context['versions'][0]['file'] !== null);
    }

    public function test_the_team_reads_its_reviewed_diagram_with_no_pdf(): void
    {
        $review = $this->version(1, [['name' => 'dfd.drawio', 'path' => 'submissions/ch3-v1.pdf']], $this->adviser());
        $review->update(['review_status' => CapstoneDocumentReview::REVIEWED, 'reviewed_at' => now()]);

        $this->actingAs($this->student, 'web')->followingRedirects()->get(route('student.review', ['review_id' => $review->review_id]))
            ->assertOk()
            ->assertSee('data-pdf-url=""', false)
            ->assertSee('data-diagrams-url="'.route('student.review.diagrams', $review->review_id).'"', false);
    }

    public function test_a_diagrams_drive_copy_is_never_written_over_with_a_pdf(): void
    {
        $review = $this->version(1, [['name' => 'dfd.drawio', 'path' => 'submissions/ch3-v1.pdf']], $expert = $this->adviser());
        $review->update(['drive_file_id' => 'drive-file-1']);

        $this->actingAs($expert, 'expert')
            ->call('PUT', route('reviews.sync-drive', $review->review_id), [], [], [], ['CONTENT_TYPE' => 'application/pdf', 'HTTP_ACCEPT' => 'application/json'], '%PDF-1.4')
            ->assertStatus(422)->assertJson(['message' => 'A diagram\'s Google Drive copy is the team\'s own file and is left as it is.']);
    }

    public function test_taking_an_upload_back_removes_its_diagrams_too(): void
    {
        $review = $this->version(1, [['name' => 'dfd.drawio', 'path' => 'submissions/diagrams/v1.drawio']], $this->adviser());

        $this->actingAs($this->student, 'web')->from(route('student.submission'))
            ->delete(route('student.submission.unsubmit', $review->file_id));

        $this->assertNull(CapstoneDocument::find($review->file_id));
        Storage::disk('local')->assertMissing('submissions/diagrams/v1.drawio');
    }
}
