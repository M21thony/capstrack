<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\PdfRemark;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A team can take back an upload, as in Google Classroom, until a reviewer
 * starts on it: it is gone as if never sent, so the next upload gets the
 * same version number.
 */
class UnsubmitTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->student = $this->memberOfNewTeam('mark@my.cspc.edu.ph');
    }

    private function memberOfNewTeam(string $email): Student
    {
        $student = Student::create([
            'email' => $email, 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        $team = Team::create(['team_name' => 'Team '.$email, 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return $student;
    }

    /** Chapter 1 sent to one reviewer, who has not opened it. */
    private function sent(int $version = 1, ?Student $by = null): CapstoneDocumentReview
    {
        $path = "submissions/chapter1-v{$version}.pdf";
        Storage::disk('local')->put($path, 'pdf');

        $document = CapstoneDocument::create([
            'student_id' => ($by ?? $this->student)->student_id,
            'file_name' => 'Chapter1',
            'file_path' => $path,
            'file_version' => $version,
            'document_status' => 'Pending',
            'chapter' => 'Chapter 1',
        ]);

        return CapstoneDocumentReview::create([
            'file_id' => $document->file_id,
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => 'adviser',
            'review_status' => CapstoneDocumentReview::AWAITING,
            'version' => (string) $version,
        ]);
    }

    private function unsubmit(int $fileId): TestResponse
    {
        return $this->actingAs($this->student, 'web')
            ->from(route('student.submission'))
            ->delete(route('student.submission.unsubmit', $fileId));
    }

    public function test_an_untouched_upload_is_taken_back_entirely(): void
    {
        $review = $this->sent();
        $document = $review->document;

        $this->unsubmit($document->file_id)
            ->assertRedirect(route('student.submission'))
            ->assertSessionHas('status');

        $this->assertNull(CapstoneDocument::find($document->file_id));
        $this->assertNull(CapstoneDocumentReview::find($review->review_id));
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_the_next_upload_gets_the_same_version_number_again(): void
    {
        $this->sent(1)->update(['review_status' => CapstoneDocumentReview::REVIEWED]);
        $this->unsubmit($this->sent(2)->file_id)->assertSessionHas('status');

        $this->actingAs($this->student, 'web')
            ->getJson(route('student.submission.next-version', ['title' => 'Chapter1']))
            ->assertJson(['version' => 2]);
    }

    public function test_it_stays_once_a_reviewer_has_started(): void
    {
        $started = [
            'reviewing' => fn (CapstoneDocumentReview $r) => $r->update(['review_status' => CapstoneDocumentReview::IN_PROGRESS]),
            'reviewed' => fn (CapstoneDocumentReview $r) => $r->update(['review_status' => CapstoneDocumentReview::REVIEWED]),
            'verdict' => fn (CapstoneDocumentReview $r) => $r->update(['verdict' => CapstoneDocumentReview::NEEDS_REVISION]),
            'remark' => fn (CapstoneDocumentReview $r) => PdfRemark::create(['review_id' => $r->review_id, 'page_number' => 1, 'comment' => 'Fix this', 'is_general' => true]),
        ];

        foreach ($started as $case => $start) {
            $review = $this->sent();
            $start($review);

            $this->unsubmit($review->file_id)->assertSessionHas('error');
            $this->assertNotNull(CapstoneDocument::find($review->file_id), $case);

            CapstoneDocument::whereKey($review->file_id)->delete();
        }
    }

    public function test_an_older_version_cannot_be_taken_back(): void
    {
        $first = $this->sent(1);
        $this->sent(2);

        $this->unsubmit($first->file_id)->assertSessionHas('error');

        $this->assertNotNull(CapstoneDocument::find($first->file_id));
    }

    public function test_another_teams_upload_is_not_found(): void
    {
        $theirs = $this->sent(1, $this->memberOfNewTeam('other@my.cspc.edu.ph'));

        $this->unsubmit($theirs->file_id)->assertNotFound();

        $this->assertNotNull(CapstoneDocument::find($theirs->file_id));
    }

    public function test_the_button_shows_only_where_it_works(): void
    {
        $this->sent(1)->update(['review_status' => CapstoneDocumentReview::REVIEWED]);
        $open = $this->sent(2);

        $html = $this->actingAs($this->student, 'web')->get(route('student.submission'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '>Unsubmit</button>'));
        $this->assertStringContainsString(route('student.submission.unsubmit', $open->file_id), $html);
        // It asks first in the site's own dialog: an inline onsubmit is blocked by the page's security rules.
        $this->assertStringNotContainsString('onsubmit=', $html);
        $this->assertStringContainsString("title: 'Unsubmit this file?'", $html);
        $this->assertStringContainsString('id="ad-names"', $html);
    }
}
