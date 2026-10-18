<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Deliverable;
use App\Models\PdfRemark;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Documents: Document Submission and Review Viewer as one page. The team's
 * files, one group per line with every version under it - who sent it, when,
 * where each reviewer is - and each opening on the same page with its
 * reviewer's remarks.
 */
class DocumentsPageTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $me;

    private Student $teammate;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->me = $this->student('Mark', 'Reyes', 'me@my.cspc.edu.ph');
        $this->teammate = $this->student('Jolina', 'Ramos', 'jolina@my.cspc.edu.ph');
        $this->team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $this->me->student_id]);

        foreach ([$this->me, $this->teammate] as $member) {
            StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $this->team->team_id]);
        }
    }

    private function student(string $first, string $last, string $email): Student
    {
        return Student::create([
            'email' => $email, 'f_name' => $first, 'l_name' => $last,
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
    }

    private function upload(Student $by, string $name, int $version, string $chapter = 'Chapter 3'): CapstoneDocument
    {
        Storage::disk('local')->put("documents/{$name}.pdf", '%PDF-1.4 pretend');

        return CapstoneDocument::create([
            'student_id' => $by->student_id,
            'file_name' => $name,
            'file_path' => "documents/{$name}.pdf",
            'file_version' => $version,
            'document_status' => 'Submitted',
            'chapter' => $chapter,
        ]);
    }

    private function reviewOf(CapstoneDocument $document, string $role, string $status, ?string $verdict = null): CapstoneDocumentReview
    {
        return CapstoneDocumentReview::create([
            'file_id' => $document->file_id,
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => $role,
            'review_status' => $status,
            'verdict' => $verdict,
            'reviewed_at' => $status === CapstoneDocumentReview::REVIEWED ? now() : null,
            'version' => $document->file_version,
        ]);
    }

    /** A signed paper is handed in and checked on Progress Tracker, not listed with the papers. */
    public function test_a_signed_paper_is_not_listed_with_the_teams_papers(): void
    {
        $checklist = $this->emptyChecklist($this->capsaFor());
        $form = $this->addDeliverable($checklist, ['title' => 'Signed Hearing Form', 'kind' => Deliverable::FORM]);
        $clf = $this->addDeliverable($checklist, ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser']);
        $draft = $this->addDeliverable($checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT]);

        $this->upload($this->me, 'Hearing_Form_signed', 1)->update(['deliverable_id' => $form->id]);
        $this->upload($this->me, 'CLF_Chapter_1', 1)->update(['deliverable_id' => $clf->id]);
        $this->upload($this->me, 'Chapter1_Draft', 1)->update(['deliverable_id' => $draft->id]);
        $this->upload($this->me, 'Gantt_Chart', 1, '');

        $response = $this->actingAs($this->me, 'web')->get(route('student.documents'))->assertOk();

        $this->assertEqualsCanonicalizing(['Chapter 1 Draft', 'Gantt_Chart'], $response->viewData('groups')->pluck('title')->all());
        $response->assertDontSee('Hearing_Form_signed')->assertDontSee('CLF_Chapter_1');
    }

    /** A signed paper sent as a phone photo opens as the photo, not as a broken PDF. */
    public function test_a_signed_paper_photo_opens_as_a_photo(): void
    {
        Storage::disk('local')->put('submissions/hearing-form.jpg', 'pretend jpeg');
        $photo = CapstoneDocument::create([
            'student_id' => $this->me->student_id,
            'file_name' => 'Hearing_Form_signed',
            'file_path' => 'submissions/hearing-form.jpg',
            'file_version' => 1,
            'document_status' => 'Submitted',
        ]);

        $this->actingAs($this->me, 'web')->get(route('student.documents.file', $photo->file_id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->me, 'web')->get(route('student.documents.file', $this->upload($this->me, 'Ch3_Methodology_v1', 1)->file_id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** What a reviewer asked for is on the card that needs it, and that card comes first. */
    public function test_a_paper_sent_back_comes_first_with_what_was_asked(): void
    {
        $sentBack = $this->upload($this->teammate, 'Ch2_RRL_v1', 1, 'Chapter 2');
        $review = $this->reviewOf($sentBack, 'consultant', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::NEEDS_REVISION);
        PdfRemark::create(['review_id' => $review->review_id, 'page_number' => 4, 'comment' => 'Use a study from the last five years.']);
        PdfRemark::create(['review_id' => $review->review_id, 'page_number' => 1, 'comment' => 'Add five studies from 2022 onward.', 'is_general' => true]);
        $approved = $this->upload($this->me, 'Ch1_Intro_v3', 3, 'Chapter 1');
        $this->reviewOf($approved, 'adviser', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::APPROVED);
        $this->upload($this->me, 'Ch3_Methodology_v1', 1);

        $response = $this->actingAs($this->me, 'web')->get(route('student.documents'))->assertOk();

        $first = $response->viewData('groups')->first();
        $this->assertSame('Chapter 2', $first->title);
        $this->assertSame('revise', $first->filter);
        $this->assertSame('Add five studies from 2022 onward.', $first->remark['first']);
        $this->assertSame(2, $first->remark['count']);
        $this->assertSame('Consultant', $first->remark['role']);
        $this->assertSame(['all' => 3, 'revise' => 1, 'review' => 1, 'ok' => 1], $response->viewData('counts'));
        $response->assertSeeText('Add five studies from 2022 onward.');
    }

    /** Opened without a reviewer picked, a paper sent back opens on the one who asked for changes. */
    public function test_a_paper_sent_back_opens_on_the_reviewer_who_asked_for_changes(): void
    {
        $document = $this->upload($this->me, 'Ch2_RRL_v1', 1, 'Chapter 2');
        $adviser = $this->reviewOf($document, 'adviser', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::APPROVED);
        $consultant = $this->reviewOf($document, 'consultant', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::NEEDS_REVISION);
        $adviser->update(['reviewed_at' => now()->addMinute()]);

        $response = $this->actingAs($this->me, 'web')->get(route('student.documents', ['file' => $document->file_id]))->assertOk();
        $this->assertSame($consultant->review_id, $response->viewData('reader')->review->review_id);

        // The approving reviewer's remarks still offer the revision the other one asked for.
        $this->actingAs($this->me, 'web')
            ->get(route('student.documents', ['file' => $document->file_id, 'review' => $adviser->review_id]))
            ->assertOk()
            ->assertSee('Submit 1st revision');
    }

    /** The menu counts the papers waiting on a revision, on every page, until it is sent. */
    public function test_the_menu_counts_the_papers_to_revise(): void
    {
        $sentBack = $this->upload($this->teammate, 'Ch2_RRL_v1', 1, 'Chapter 2');
        $this->reviewOf($sentBack, 'consultant', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::NEEDS_REVISION);
        $this->reviewOf($this->upload($this->me, 'Ch1_Intro_v1', 1, 'Chapter 1'), 'adviser', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::APPROVED);

        $this->actingAs($this->me, 'web')->get(route('student.documents'))
            ->assertOk()
            ->assertSee('title="1 paper to revise"', false);

        // The revision is in: nothing left to revise.
        $this->upload($this->me, 'Ch2_RRL_v2', 2, 'Chapter 2');
        $this->actingAs($this->me, 'web')->get(route('student.documents'))
            ->assertOk()
            ->assertDontSee('paper to revise', false);
    }

    public function test_the_page_needs_a_sign_in(): void
    {
        $this->get(route('student.documents'))->assertRedirect(route('login'));
    }

    public function test_each_line_lists_every_version_with_who_sent_it(): void
    {
        $first = $this->upload($this->teammate, 'Ch3_Methodology_v1', 1);
        $this->reviewOf($first, 'adviser', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::NEEDS_REVISION);
        $second = $this->upload($this->me, 'Ch3_Methodology_v2', 2);
        $this->reviewOf($second, 'adviser', CapstoneDocumentReview::IN_PROGRESS);
        $this->upload($this->teammate, 'Survey_Questionnaire', 1, 'Survey');

        $response = $this->actingAs($this->me, 'web')->get(route('student.documents'))->assertOk();

        $groups = $response->viewData('groups');
        $this->assertCount(2, $groups);
        $chapter3 = $groups->firstWhere('title', 'Chapter 3');
        $this->assertSame([2, 1], $chapter3->versions->map(fn ($v) => (int) $v->document->file_version)->all());

        $response->assertSeeInOrder(['Chapter 3', 'Ch3_Methodology_v2', 'Mark Reyes', '(you)', 'Ch3_Methodology_v1', 'Jolina Ramos'], false);
        $response->assertSeeText('CaPA · Reviewing');
        $response->assertSeeText('CaPA · Needs revision');
        $response->assertSee(route('student.documents', ['file' => $second->file_id]), false);
    }

    public function test_an_upload_nobody_has_turned_in_opens_as_the_file_alone(): void
    {
        $document = $this->upload($this->teammate, 'Survey_Questionnaire', 1, 'Survey');
        $this->reviewOf($document, 'consultant', CapstoneDocumentReview::AWAITING);

        $response = $this->actingAs($this->me, 'web')->get(route('student.documents', ['file' => $document->file_id]))->assertOk();

        $this->assertNull($response->viewData('reader')->review);
        $response->assertSee('data-pdf-url="'.route('student.documents.file', $document->file_id).'"', false);
        $response->assertSee('data-remarks-url=""', false);
        $response->assertSeeText('Consultant · not opened yet');
        $response->assertDontSee('Download CLF');
    }

    public function test_a_review_turned_in_opens_with_that_reviewers_remarks(): void
    {
        $document = $this->upload($this->me, 'Ch3_Methodology_v1', 1);
        $adviser = $this->reviewOf($document, 'adviser', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::APPROVED);
        $consultant = $this->reviewOf($document, 'consultant', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::NEEDS_REVISION);

        $response = $this->actingAs($this->me, 'web')
            ->get(route('student.documents', ['file' => $document->file_id, 'review' => $consultant->review_id]))
            ->assertOk();

        $this->assertSame($consultant->review_id, $response->viewData('reader')->review->review_id);
        $response->assertSee(route('student.review.remarks', $consultant->review_id), false);
        $response->assertSee(route('student.review.clf', $consultant->review_id), false);
        // The other reviewer's remarks are one click away.
        $response->assertSee(route('student.documents', ['file' => $document->file_id, 'review' => $adviser->review_id]));
        $response->assertSee('Submit 1st revision');
    }

    /** A diagram is kept on disk under another extension; the list says what was sent. */
    /** A revision of an upload for no line opens on the file box, not on "What is this for?". */
    public function test_a_revision_of_an_upload_for_no_line_opens_straight_on_the_file(): void
    {
        $document = $this->upload($this->me, 'Ch3_Methodology_v1', 1);
        $review = $this->reviewOf($document, 'adviser', CapstoneDocumentReview::REVIEWED, CapstoneDocumentReview::NEEDS_REVISION);
        $opens = "window.openSubmission('none', this, {$document->file_id})";

        $this->actingAs($this->me, 'web')->get(route('student.documents'))
            ->assertOk()
            ->assertSee($opens, false);

        $this->actingAs($this->me, 'web')
            ->get(route('student.documents', ['file' => $document->file_id, 'review' => $review->review_id]))
            ->assertOk()
            ->assertSee($opens, false);
    }

    public function test_a_diagram_is_shown_as_the_kind_of_file_it_was_sent_as(): void
    {
        Storage::disk('local')->put('documents/dfd.txt', '<mxfile></mxfile>');
        CapstoneDocument::create([
            'student_id' => $this->me->student_id,
            'file_name' => '04-dfd-level-1',
            'file_path' => 'documents/dfd.txt',
            'file_version' => 1,
            'document_status' => 'Submitted',
            'diagram_files' => [['name' => '04-dfd-level-1.drawio', 'path' => 'documents/dfd.txt']],
        ]);

        $this->actingAs($this->me, 'web')->get(route('student.documents'))
            ->assertOk()
            ->assertSee('<span class="dc-ic drawio" aria-hidden="true">DRAWIO</span>', false)
            ->assertDontSee('>TXT<', false);
    }

    public function test_the_file_and_its_diagrams_are_the_teams_only(): void
    {
        $document = $this->upload($this->teammate, 'Ch3_Methodology_v1', 1);

        $this->actingAs($this->me, 'web')->get(route('student.documents.file', $document->file_id))->assertOk();
        $this->actingAs($this->me, 'web')->getJson(route('student.documents.diagrams', $document->file_id))->assertOk()->assertExactJson([]);

        $outsider = $this->student('Rosa', 'Cruz', 'rosa@my.cspc.edu.ph');
        StudentSchoolYear::create([
            'student_id' => $outsider->student_id,
            'team_id' => Team::create(['team_name' => 'Others', 'leader_id' => $outsider->student_id])->team_id,
        ]);

        $this->actingAs($outsider, 'web')->get(route('student.documents.file', $document->file_id))->assertNotFound();
        $this->actingAs($outsider, 'web')->get(route('student.documents.diagrams', $document->file_id))->assertNotFound();
        $this->actingAs($outsider, 'web')->get(route('student.documents'))->assertOk()->assertDontSee('Ch3_Methodology_v1');
    }

    public function test_the_sidebar_has_one_documents_link_for_both_old_pages(): void
    {
        $html = $this->actingAs($this->me, 'web')->get(route('student.documents'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-tour-target="student.documents"'));
        $this->assertStringNotContainsString('data-tour-target="student.submission"', $html);
        $this->assertStringNotContainsString('data-tour-target="student.review"', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('student.documents'), '/').'"[^>]*class="nav-item active"/', $html);
    }

    /**
     * A card is laid out on the list's own width, not the window's, so the
     * buttons drop under the paper while it is too narrow for them beside it
     * rather than squeezing the title or running past the card's edge.
     */
    public function test_the_cards_reflow_on_the_lists_own_width(): void
    {
        $html = $this->actingAs($this->me, 'web')->get(route('student.documents'))->assertOk()->getContent();

        $this->assertStringContainsString('container: dl / inline-size;', $html);
        $this->assertStringContainsString('@container dl (max-width: 720px)', $html);
    }
}
