<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\StudentTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A capstone document belongs to the team, not to whoever clicked Upload.
 *
 * Everything about a submission was scoped to one account: the list of
 * reviews, the permission check behind the file and the remarks, and the
 * version number. So a teammate opening Review saw an empty page, could not
 * open the reviewed copy of work their own team had turned in, and starting
 * a second "Chapter 1" told them they were on Version 1 of it.
 */
class TeamSeesItsOwnSubmissionsTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Student $uploader;

    private Student $teammate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploader = $this->student('Mark', 'uploader@my.cspc.edu.ph');
        $this->teammate = $this->student('John', 'teammate@my.cspc.edu.ph');

        $this->team = Team::create([
            'team_name' => 'Bulbasaurus',
            'leader_id' => $this->uploader->student_id,
        ]);

        foreach ([$this->uploader, $this->teammate] as $member) {
            StudentSchoolYear::create([
                'student_id' => $member->student_id,
                'team_id' => $this->team->team_id,
            ]);
        }
    }

    private function student(string $first, string $email): Student
    {
        return Student::create([
            'email' => $email, 'f_name' => $first, 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
    }

    private function reviewedDocumentBy(Student $student): CapstoneDocumentReview
    {
        $document = CapstoneDocument::create([
            'student_id' => $student->student_id,
            'file_name' => 'Chapter1_2026-09-07.pdf',
            'file_path' => 'documents/chapter1.pdf',
            'file_version' => 1,
            'document_status' => 'Submitted',
            'chapter' => 'Chapter 1',
        ]);

        return CapstoneDocumentReview::create([
            'file_id' => $document->file_id,
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => 'adviser',
            'review_status' => 'Reviewed',
            'reviewed_at' => now(),
            'version' => 1,
        ]);
    }

    // ----------------------------------------------------------------

    /** The helper the whole fix rests on. */
    public function test_the_team_is_everybody_on_it(): void
    {
        $ids = StudentTeam::memberIdsFor($this->teammate->student_id);

        sort($ids);
        $expected = [(int) $this->uploader->student_id, (int) $this->teammate->student_id];
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    /** A student in no team is a team of one, so callers keep one shape. */
    public function test_a_student_with_no_team_is_a_team_of_one(): void
    {
        $loner = $this->student('Ana', 'loner@my.cspc.edu.ph');
        StudentSchoolYear::create(['student_id' => $loner->student_id]);

        $this->assertSame([(int) $loner->student_id], StudentTeam::memberIdsFor($loner->student_id));
    }

    public function test_a_teammate_sees_a_document_somebody_else_submitted(): void
    {
        $this->reviewedDocumentBy($this->uploader);

        $html = $this->actingAs($this->teammate, 'web')
            ->get(route('student.documents'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Chapter1_2026-09-07.pdf', $html);
        // Who sent it is on the row, for the rest of the team to see.
        $this->assertStringContainsString(trim($this->uploader->f_name.' '.$this->uploader->l_name), $html);
    }

    public function test_a_teammate_can_open_the_remarks(): void
    {
        $review = $this->reviewedDocumentBy($this->uploader);

        $this->actingAs($this->teammate, 'web')
            ->get(route('student.review.remarks', $review))
            ->assertOk();
    }

    public function test_a_teammate_can_open_the_reviewed_file(): void
    {
        Storage::fake('local');
        Storage::put('documents/chapter1.pdf', '%PDF-1.4 pretend');

        $review = $this->reviewedDocumentBy($this->uploader);

        $this->actingAs($this->teammate, 'web')
            ->get(route('student.review.pdf', $review))
            ->assertOk();
    }

    /** Somebody else's team is still somebody else's. */
    public function test_another_teams_document_is_still_refused(): void
    {
        $review = $this->reviewedDocumentBy($this->uploader);

        $outsider = $this->student('Rosa', 'outsider@my.cspc.edu.ph');
        $otherTeam = Team::create(['team_name' => 'Others', 'leader_id' => $outsider->student_id]);
        StudentSchoolYear::create([
            'student_id' => $outsider->student_id, 'team_id' => $otherTeam->team_id,
        ]);

        $this->actingAs($outsider, 'web')
            ->get(route('student.review.remarks', $review))
            ->assertForbidden();

        $this->actingAs($outsider, 'web')
            ->get(route('student.documents'))
            ->assertOk()
            ->assertDontSee('Chapter1_2026-09-07.pdf');
    }

    /**
     * The Consultation Log Form names every member of the team as a
     * proponent, and only the one who uploaded could download it. Everybody
     * else got a 403 on a form with their own name printed on it.
     */
    public function test_a_teammate_can_download_the_consultation_log(): void
    {
        $review = $this->reviewedDocumentBy($this->uploader);

        $this->actingAs($this->teammate, 'web')
            ->get(route('student.review.clf', $review->review_id))
            ->assertOk();
    }

    /** Still not somebody else's log, though. */
    public function test_another_team_cannot_download_the_consultation_log(): void
    {
        $review = $this->reviewedDocumentBy($this->uploader);

        $outsider = $this->student('Rosa', 'clf-outsider@my.cspc.edu.ph');
        $otherTeam = Team::create(['team_name' => 'Others', 'leader_id' => $outsider->student_id]);
        StudentSchoolYear::create([
            'student_id' => $outsider->student_id, 'team_id' => $otherTeam->team_id,
        ]);

        $this->actingAs($outsider, 'web')
            ->get(route('student.review.clf', $review->review_id))
            ->assertForbidden();
    }

    /** An approved paper's form says APPROVED and nothing after it - not the date it was approved. */
    public function test_an_approved_consultation_log_carries_no_approval_date(): void
    {
        $approvedAt = now()->subDays(3);

        $html = view('instructor.pdf.consultation-log', [
            'projectTitle' => 'A Capstone Project', 'alias' => 'Team', 'proponents' => collect(['Mark Cruz']),
            'chapter' => '1', 'consultationDate' => now(), 'deadline' => null, 'approvedAt' => $approvedAt,
            'remarksBySection' => [], 'remarks' => [], 'generalRemarks' => [],
        ])->render();

        $this->assertStringContainsString('APPROVED', $html);
        $this->assertStringNotContainsString($approvedAt->format('F j, Y'), $html);
    }

    /**
     * A chapter is the team's, so its version count is too. Both members
     * sending "Chapter 1" used to be told they were on Version 1 of it.
     */
    public function test_the_version_continues_across_the_team(): void
    {
        $this->reviewedDocumentBy($this->uploader);

        $this->actingAs($this->teammate, 'web')
            ->getJson(route('student.submission.next-version', ['title' => 'Chapter1_revised.pdf']))
            ->assertOk()
            ->assertJsonFragment(['version' => 2]);
    }
}
