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
 * The search box and the two dropdowns above the document table were
 * markup only - no ids, no handlers, nothing reading them. Typing a team
 * name filtered nothing. The filtering happens in the browser, so what is
 * checked here is that every row carries what the filter reads off it.
 */
class DocReviewFilterTest extends TestCase
{
    use RefreshDatabase;

    private function reviewFor(CapstoneExpert $expert, string $team, string $file, string $status): void
    {
        $student = Student::create([
            'email' => strtolower($file).'@my.cspc.edu.ph',
            'f_name' => 'A', 'l_name' => 'B', 'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);

        $teamRow = Team::create([
            'team_name' => $team, 'leader_id' => $student->student_id, 'team_status' => 'Active',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id, 'team_id' => $teamRow->team_id,
        ]);

        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $student->student_id,
            'file_name' => $file,
            'file_path' => 'docs/'.$file,
            'file_version' => 1,
            'document_status' => 'Submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('capstone_document_review')->insert([
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => 'adviser',
            'review_status' => $status,
            'version' => '1.0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_every_row_carries_what_the_filter_reads(): void
    {
        $expert = CapstoneExpert::create([
            'email' => 'reviewer@test.com', 'first_name' => 'R', 'last_name' => 'V',
            'approved_status' => true,
        ]);

        $this->reviewFor($expert, 'Bulbasaur', 'Chapter1.pdf', 'To Review');
        $this->reviewFor($expert, 'Charmander', 'Chapter2.pdf', 'Reviewed');

        $page = $this->actingAs($expert, 'expert')->get('/instructor/docreview');
        $page->assertStatus(200);

        // The controls the filter hangs off.
        foreach (['id="dr-search"', 'id="dr-team"', 'id="dr-status"', 'id="dr-count"'] as $control) {
            $page->assertSee($control, false);
        }

        // The dropdown's option value has to be the same string the row
        // carries, or picking a team matches nothing.
        $page->assertSee('<option value="Bulbasaur">', false);
        $page->assertSee('data-team="Bulbasaur"', false);
        $page->assertSee('data-team="Charmander"', false);

        $page->assertSee('data-status="pending"', false);
        $page->assertSee('data-status="reviewed"', false);

        $page->assertSee('data-search="chapter1.pdf bulbasaur adviser"', false);
    }
}
