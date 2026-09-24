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

    /** A signed form is a scan the CaPSA checks, not a draft that is revised. */
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

        $this->actingAs($this->student, 'web')->get(route('student.review', ['review_id' => $review->review_id]))
            ->assertOk()
            ->assertSee("window.openSubmissionDrawer('{$line->id}')", false)
            ->assertSee('Submit 1st revision')
            ->assertSee('window.confirmSheet', false);

        // and what that drawer sends is checked like any other.
        $this->assertSame(['heading', 'unrelated'], $this->types($this->check($this->pdf('Chapter 3', self::CHAPTER_3), $line)));
    }

    public function test_the_drawer_asks_for_the_check_and_can_show_the_dialog(): void
    {
        $this->actingAs($this->student, 'web')
            ->get(route('student.submission'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('data-check-url="'.route('student.submission.warnings').'"', false);

        $this->actingAs($this->student, 'web')->get('/student/progress')->assertOk()->assertSee('window.confirmSheet', false);
    }
}
