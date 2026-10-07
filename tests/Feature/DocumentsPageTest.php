<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documents: Document Submission and Review Viewer as one page. The team's
 * files, one group per line with every version under it - who sent it, when,
 * where each reviewer is - and each opening on the same page with its
 * reviewer's remarks.
 */
class DocumentsPageTest extends TestCase
{
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
        $response->assertSeeText('Adviser · Reviewing');
        $response->assertSeeText('Adviser · Needs revision');
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
        $response->assertSee(route('student.documents', ['file' => $document->file_id, 'review' => $adviser->review_id]), false);
        $response->assertSee('Submit 1st revision');
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
}
