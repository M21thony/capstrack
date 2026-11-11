<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\Student;
use App\Models\Team;
use App\Support\RevisionCheck;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Chapter 3 sent as the next version of Chapter 1 is caught before it goes
 * up: by the chapter its first page names, and by how little it shares with
 * the team's last upload for that line.
 */
class SubmissionRevisionCheckTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private const CHAPTER_1 = 'This study presents CapsTrack, a web based monitoring system for capstone teams of the College of Computing Studies. '
        .'Advisers currently track the progress of each team through printed forms and scattered messages, which delays feedback and loses records. '
        .'The general objective is to design and develop a system that records submissions, reviews and defenses in one place. '
        .'Specifically it aims to let students upload drafts, let advisers annotate them, and let the coordinator see which teams are behind. '
        .'The scope covers third and fourth year students taking the capstone subject during one school year.';

    private const CHAPTER_3 = 'The researchers used the agile development methodology with two week sprints and a product backlog kept by the team leader. '
        .'Data were gathered through interviews with five capstone advisers and a survey answered by one hundred twenty students. '
        .'The instrument was validated by three experts and its reliability was measured with Cronbach alpha before the survey was fielded. '
        .'Responses were tallied and interpreted with the weighted mean on a four point Likert scale described in the tables below. '
        .'Usability was evaluated with the ISO 25010 quality characteristics of functional suitability, performance efficiency and usability.';

    private Student $student;

    private Checklist $checklist;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        [$team, $this->student] = $this->teamInSection('Bulbasaur');
        $this->checklist = $this->departmentChecklist($this->capsaFor());
    }

    private function pdf(string $heading, string $body, string $name = 'draft.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'draft').'.pdf';
        file_put_contents($path, Pdf::loadHTML('<h1>'.$heading.'</h1><p>'.$body.'</p>')->output());

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    /** The team's earlier upload for a line, stored the way SubmissionController stores one. */
    private function earlier(Deliverable $line, UploadedFile $file): void
    {
        $stored = $file->store('submissions', 'local');
        \DB::table('capstone_documents')->insert([
            'student_id' => $this->student->student_id, 'file_name' => 'Chapter 1', 'file_path' => $stored,
            'deliverable_id' => $line->id, 'file_version' => 1, 'document_status' => 'Pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function check(UploadedFile $file, Deliverable $line)
    {
        return $this->actingAs($this->student, 'web')
            ->postJson(route('student.submission.warnings'), ['file' => $file, 'deliverable_id' => $line->id])
            ->assertOk();
    }

    private function types($response): array
    {
        return array_column($response->json('warnings'), 'type');
    }

    public function test_chapter_3_sent_as_chapter_1_is_caught_by_its_heading(): void
    {
        $response = $this->check($this->pdf('CHAPTER III', self::CHAPTER_3, 'final_final2.pdf'), $this->line($this->checklist, 'chapter_1_draft'));

        $this->assertSame(['heading'], $this->types($response));
        $this->assertSame('The first page of final_final2.pdf says Chapter 3, but you are sending it as “Chapter 1 Draft”.', $response->json('warnings.0.message'));
    }

    public function test_the_right_chapter_passes(): void
    {
        $this->assertSame([], $this->types($this->check($this->pdf('Chapter 1', self::CHAPTER_1), $this->line($this->checklist, 'chapter_1_draft'))));
    }

    /** A table of contents names every chapter, which says nothing about which one this is. */
    public function test_a_table_of_contents_is_not_read_as_a_heading(): void
    {
        $toc = 'Table of Contents: Chapter 1 Introduction, Chapter 2 Review of Related Literature, Chapter 3 Methodology';

        $this->assertNull(RevisionCheck::chapterHeading([$toc]));
        $this->assertSame(3, RevisionCheck::chapterHeading(['CHAPTER THREE Methodology']));
    }

    public function test_a_file_sharing_almost_nothing_with_the_last_upload_is_caught(): void
    {
        $line = $this->line($this->checklist, 'chapter_1_draft');
        $this->earlier($line, $this->pdf('Chapter 1', self::CHAPTER_1));

        // No heading to go by: only how little it shares with version 1 gives it away.
        $response = $this->check($this->pdf('Methodology', self::CHAPTER_3, 'upload.pdf'), $line);

        $this->assertSame(['unrelated'], $this->types($response));
        $this->assertStringContainsString('shares almost no text with your last “Chapter 1 Draft” (version 1)', $response->json('warnings.0.message'));
    }

    /** A real revision - a sentence reworded, one added - keeps most of its phrases. */
    public function test_a_real_revision_passes(): void
    {
        $line = $this->line($this->checklist, 'chapter_1_draft');
        $this->earlier($line, $this->pdf('Chapter 1', self::CHAPTER_1));

        $revised = str_replace('which delays feedback and loses records', 'which slows feedback and misplaces records', self::CHAPTER_1)
            .' The significance of the study lies in giving advisers one record of every team.';

        $this->assertSame([], $this->types($this->check($this->pdf('Chapter 1', $revised), $line)));
    }

    public function test_both_warnings_together(): void
    {
        $line = $this->line($this->checklist, 'chapter_1_draft');
        $this->earlier($line, $this->pdf('Chapter 1', self::CHAPTER_1));

        $this->assertSame(['heading', 'unrelated'], $this->types($this->check($this->pdf('Chapter 3', self::CHAPTER_3), $line)));
    }

    /** A signed form is a scan the CaPSA/TSA checks, not a draft that is revised. */
    public function test_a_signed_paper_is_not_checked(): void
    {
        $this->assertSame([], $this->types($this->check($this->pdf('Chapter 3', self::CHAPTER_3), $this->line($this->checklist, 'team_assignment_form'))));
    }

    /** The Review Viewer's "Submit 2nd revision" opens the same drawer, on the same line, so it is checked too. */
    public function test_submit_revision_in_the_review_viewer_goes_through_the_check(): void
    {
        $line = $this->line($this->checklist, 'chapter_1_draft');
        $this->earlier($line, $this->pdf('Chapter 1', self::CHAPTER_1));
        $review = CapstoneDocumentReview::create([
            'file_id' => \DB::table('capstone_documents')->value('file_id'),
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => 'adviser', 'review_status' => CapstoneDocumentReview::REVIEWED,
            'verdict' => CapstoneDocumentReview::NEEDS_REVISION, 'reviewed_at' => now(), 'verdict_at' => now(),
        ]);

        $fileId = \DB::table('capstone_documents')->value('file_id');

        $this->actingAs($this->student, 'web')->followingRedirects()->get(route('student.review', ['review_id' => $review->review_id]))
            ->assertOk()
            ->assertSee("window.openSubmission('{$line->id}', this, {$fileId})", false)
            ->assertSee('Submit 1st revision')
            ->assertSee('window.confirmSheet', false);

        // and what that drawer sends is checked like any other.
        $this->assertSame(['heading', 'unrelated'], $this->types($this->check($this->pdf('Chapter 3', self::CHAPTER_3), $line)));
    }

    /** An upload sent with no checklist line, the way "feedback only" stores one. */
    private function looseUpload(UploadedFile $file, string $name = 'Chapter 1 Bulbasaur', ?int $teamId = null): int
    {
        return \DB::table('capstone_documents')->insertGetId([
            'student_id' => $this->student->student_id, 'team_id' => $teamId, 'file_name' => $name, 'chapter' => 'Chapter 1',
            'file_path' => $file->store('submissions', 'local'), 'deliverable_id' => null, 'file_version' => 3,
            'document_status' => 'Reviewed', 'created_at' => now(), 'updated_at' => now(),
        ], 'file_id');
    }

    private function checkRevisionOf(UploadedFile $file, int $fileId)
    {
        return $this->actingAs($this->student, 'web')
            ->postJson(route('student.submission.warnings'), ['file' => $file, 'revises' => $fileId])
            ->assertOk();
    }

    /** "Submit revision" on an upload with no line still says which upload the file revises. */
    public function test_submit_revision_on_an_upload_with_no_line_names_that_upload(): void
    {
        $fileId = $this->looseUpload($this->pdf('Chapter 1', self::CHAPTER_1));
        $review = CapstoneDocumentReview::create([
            'file_id' => $fileId,
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => 'adviser', 'review_status' => CapstoneDocumentReview::REVIEWED,
            'verdict' => CapstoneDocumentReview::NEEDS_REVISION, 'reviewed_at' => now(), 'verdict_at' => now(),
        ]);

        $this->actingAs($this->student, 'web')->followingRedirects()->get(route('student.review', ['review_id' => $review->review_id]))
            ->assertOk()
            ->assertSee("window.openSubmission('none', this, {$fileId})", false);
    }

    public function test_chapter_3_sent_as_a_revision_of_an_upload_with_no_line_is_caught(): void
    {
        $fileId = $this->looseUpload($this->pdf('Chapter 1', self::CHAPTER_1));

        $response = $this->checkRevisionOf($this->pdf('Chapter 3', self::CHAPTER_3), $fileId);

        $this->assertSame(['heading', 'unrelated'], $this->types($response));
        $this->assertStringContainsString('you are sending it as “Chapter 1 Bulbasaur”', $response->json('warnings.0.message'));
        $this->assertStringContainsString('(version 3)', $response->json('warnings.1.message'));
    }

    /** No chapter heading on its first page: the file name and the text still give it away. */
    public function test_a_revision_whose_name_says_another_chapter_is_caught(): void
    {
        $fileId = $this->looseUpload($this->pdf('Chapter 1', self::CHAPTER_1));

        $response = $this->checkRevisionOf($this->pdf('Methodology', self::CHAPTER_3, 'Chapter_3_Bulbasaur_Revised.pdf'), $fileId);

        $this->assertSame(['name', 'unrelated'], $this->types($response));
        $this->assertStringContainsString('says Chapter 3', $response->json('warnings.0.message'));
    }

    public function test_a_real_revision_of_an_upload_with_no_line_passes(): void
    {
        $fileId = $this->looseUpload($this->pdf('Chapter 1', self::CHAPTER_1));
        $revised = str_replace('which delays feedback and loses records', 'which slows feedback and misplaces records', self::CHAPTER_1);

        $this->assertSame([], $this->types($this->checkRevisionOf($this->pdf('Chapter 1', $revised, 'Chapter_1_Bulbasaur_Revised.pdf'), $fileId)));
    }

    public function test_the_same_file_again_is_caught_on_an_upload_with_no_line(): void
    {
        $file = $this->pdf('Chapter 1', self::CHAPTER_1);
        $fileId = $this->looseUpload($file);

        $this->checkRevisionOf($file, $fileId)->assertJsonPath('same', 3);
    }

    /** Another team's upload is nothing to compare with - and not a way to read it. */
    public function test_another_teams_upload_is_not_compared(): void
    {
        $fileId = $this->looseUpload($this->pdf('Chapter 1', self::CHAPTER_1), 'Their Chapter 1', 999999);

        $this->checkRevisionOf($this->pdf('Chapter 3', self::CHAPTER_3), $fileId)
            ->assertExactJson(['warnings' => [], 'same' => null]);
    }

    public function test_the_check_needs_a_line_or_an_upload_to_go_by(): void
    {
        $this->actingAs($this->student, 'web')
            ->postJson(route('student.submission.warnings'), ['file' => $this->pdf('Chapter 3', self::CHAPTER_3)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deliverable_id', 'revises']);
    }

    /** Opened from a line, the pop-up does not ask which line it is for; opened from the menu it does. */
    public function test_the_form_opened_from_a_line_skips_what_is_this_for(): void
    {
        $line = $this->line($this->checklist, 'team_assignment_form');
        $this->actingAs($this->student, 'web');

        $this->getJson(route('student.submission.plan', ['item' => $line->id]))->assertOk()
            ->assertJsonPath('item.id', $line->id)
            ->assertJsonPath('lines', []);

        $lines = collect($this->getJson(route('student.submission.plan'))->assertOk()->json('lines'));
        $this->assertTrue($lines->contains('id', $line->id));
    }

    public function test_the_drawer_asks_for_the_check_and_can_show_the_dialog(): void
    {
        $this->actingAs($this->student, 'web')->get('/student/progress')->assertOk()
            ->assertSee('id="upload-config"', false)
            ->assertSee(json_encode(route('student.submission.warnings')), false)
            ->assertSee('window.confirmSheet', false);
    }
}
