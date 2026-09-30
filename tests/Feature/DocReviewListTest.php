<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An expert on several teams saw every version as its own row, newest first,
 * under whatever team the uploader was on today. The list is one row per
 * document, under the team it was sent for, with what is waiting on top.
 */
class DocReviewListTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $expert;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expert = CapstoneExpert::create([
            'email' => 'reviewer@test.com', 'first_name' => 'R', 'last_name' => 'V',
            'approved_status' => true,
        ]);
    }

    private function member(string $team): Student
    {
        $student = Student::create([
            'email' => strtolower($team).'@my.cspc.edu.ph',
            'f_name' => 'A', 'l_name' => 'B', 'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);

        $teamRow = Team::create([
            'team_name' => $team, 'leader_id' => $student->student_id, 'team_status' => 'Active',
        ]);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $teamRow->team_id]);

        return $student;
    }

    private function sent(Student $student, ?int $teamId, string $file, int $version, string $status, int $daysAgo, ?string $chapter = null): void
    {
        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $student->student_id,
            'team_id' => $teamId,
            'file_name' => $file,
            'file_path' => 'docs/'.$file,
            'file_version' => $version,
            'chapter' => $chapter,
            'document_status' => 'Submitted',
            'created_at' => now()->subDays($daysAgo),
            'updated_at' => now()->subDays($daysAgo),
        ]);

        DB::table('capstone_document_review')->insert([
            'file_id' => $fileId,
            'expert_id' => $this->expert->expert_id,
            'role' => 'adviser',
            'review_status' => $status,
            'version' => $version.'.0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_one_row_per_document_under_its_team_with_waiting_on_top(): void
    {
        $mover = $this->member('Alpha');
        $alphaId = StudentSchoolYear::where('student_id', $mover->student_id)->value('team_id');
        $bravo = $this->member('Bravo');
        $bravoId = StudentSchoolYear::where('student_id', $bravo->student_id)->value('team_id');
        $charlie = $this->member('Charlie');

        // Sent for Alpha, then the uploader moved to Bravo: it stays Alpha's.
        $this->sent($mover, $alphaId, 'AlphaProposal.pdf', 1, 'To Review', 8);
        StudentSchoolYear::where('student_id', $mover->student_id)->update(['team_id' => $bravoId]);

        // Two versions of one chapter: only the latest is a row.
        $this->sent($bravo, $bravoId, 'Chapter1-first.pdf', 1, 'Reviewed', 10, 'Chapter 1');
        $this->sent($bravo, $bravoId, 'Chapter1-second.pdf', 2, 'To Review', 2, 'Chapter 1');
        $this->sent($bravo, $bravoId, 'BravoDone.pdf', 1, 'Reviewed', 1);

        // Nothing waiting: last. A file from before teams were recorded
        // falls back to the uploader's team.
        $this->sent($charlie, null, 'CharlieDone.pdf', 1, 'Reviewed', 3);

        $page = $this->actingAs($this->expert, 'expert')->get('/instructor/docreview')->assertOk();

        $page->assertDontSee('Chapter1-first.pdf');

        // Alpha has waited longest, then Bravo with its waiting file above
        // its reviewed one, then Charlie with nothing waiting.
        $page->assertSeeInOrder([
            'class="dr-group" data-team="Alpha"', 'AlphaProposal.pdf',
            'class="dr-group" data-team="Bravo"', 'Chapter1-second.pdf', 'BravoDone.pdf',
            'class="dr-group" data-team="Charlie"', 'CharlieDone.pdf',
        ], false);

        // The team filter lists each team once, by the team the files were sent for.
        $page->assertSee('<option value="Alpha">', false);
        $this->assertSame(1, substr_count($page->getContent(), '<option value="Bravo">'));
    }
}
