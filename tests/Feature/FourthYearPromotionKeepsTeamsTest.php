<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use App\Support\SectionScope;
use App\Support\TeamChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * 3rd year ends and the class moves up to 4th - often before the dean's new
 * section list exists. The team has to come through it whole: promoted
 * without a list, the members move up together; when the list arrives and
 * splits them across 4A and 4B, they are still one team with one CaPSA,
 * checklist and section (the leader's); and a student whose section does not
 * change is simply left as they are.
 */
class FourthYearPromotionKeepsTeamsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Team $team;

    private Student $leader;

    private Student $member;

    protected function setUp(): void
    {
        parent::setUp();

        // A term is running, as on the live site: enrolment rows are per term.
        Term::create(['school_year' => '2026-2027', 'semester' => 2, 'capstone_stage' => 1, 'is_active' => true])->activate();

        $this->capsa = $this->capsaFor(['BSIT-3A']);
        $this->departmentChecklist($this->capsa);

        $it = 'Bachelor of Science in Information Technology';
        $this->leader = Student::factory()->withCompleteProfile()->create(['email' => 'student1@my.cspc.edu.ph', 'course' => $it, 'year_section' => '3rd · A']);
        $this->member = Student::factory()->withCompleteProfile()->create(['email' => 'student2@my.cspc.edu.ph', 'course' => $it, 'year_section' => '3rd · A']);
        $this->team = Team::create(['team_name' => 'Team1', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $this->leader->student_id, 'member_count' => 5]);
        StudentSchoolYear::create(['student_id' => $this->leader->student_id, 'team_id' => $this->team->team_id]);
        StudentSchoolYear::create(['student_id' => $this->member->student_id, 'team_id' => $this->team->team_id]);
    }

    protected function tearDown(): void
    {
        Term::forgetActive();
        parent::tearDown();
    }

    private function promoteWithoutAList(): void
    {
        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('capsa.students.promotion.store'), ['sections' => ['BSIT-3A'], 'stay_on' => ['BSIT-4A']])
            ->assertOk()->assertJsonPath('promoted', 2);
        $this->capsa = $this->capsa->fresh();
    }

    /** @param array<int, array{0: string, 1: string}> $people email and 4th-year section letter */
    private function uploadDeansList(array $people): TestResponse
    {
        $rows = array_map(fn ($p) => [$p[0], 'Doe', 'Ann', '', '', 'BSIT', '4', $p[1]], $people);

        return $this->actingAs($this->capsa, 'expert')
            ->post(route('capsa.rosters.students.store'), ['file' => $this->sheet($rows), 'stay_on' => ['BSIT-4A']])
            ->assertOk();
    }

    private function assertTeamIsWhole(string $section): void
    {
        $this->team = $this->team->fresh();
        $this->assertSame($this->leader->student_id, (int) $this->team->leader_id, 'same leader');
        $this->assertEqualsCanonicalizing(
            [$this->leader->student_id, $this->member->student_id],
            StudentSchoolYear::where('team_id', $this->team->team_id)->pluck('student_id')->map(fn ($id) => (int) $id)->all(),
            'same members, still on the team'
        );
        $this->assertSame($section, app(SectionScope::class)->sectionFor($this->team));
        $this->assertNotNull(app(TeamChecklist::class)->for($this->team), 'still following a checklist');

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.team_management'))->assertOk()->assertSee('Team1');
        foreach ([$this->leader, $this->member] as $student) {
            $this->actingAs($student->fresh(), 'web')->get(route('student.progress'))->assertOk()
                ->assertSee('Team1')
                ->assertDontSee('Your CaPSA has not set up a checklist for your team yet.');
        }
    }

    public function test_promoting_without_a_list_moves_the_whole_team_up_together(): void
    {
        $this->promoteWithoutAList();

        $this->assertSame('4th · A', $this->leader->fresh()->year_section);
        $this->assertSame('4th · A', $this->member->fresh()->year_section);
        $this->assertContains('BSIT-4A', $this->capsa->sections);
        $this->assertTeamIsWhole('BSIT-4A');
    }

    public function test_the_deans_list_later_splits_the_team_without_breaking_it(): void
    {
        $this->promoteWithoutAList();
        $otherCapsa = $this->capsaFor(['BSIT-4B']);

        $this->uploadDeansList([['student1@my.cspc.edu.ph', 'A'], ['student2@my.cspc.edu.ph', 'B']]);

        $this->assertSame('4th · A', $this->leader->fresh()->year_section, 'unchanged: the list agrees');
        $this->assertSame('4th · B', $this->member->fresh()->year_section, 'moved to where the list puts them');
        $this->assertTeamIsWhole('BSIT-4A');

        // The other section's CaPSA does not take the team over, and sees why their student is on it.
        $this->actingAs($otherCapsa, 'expert')->get(route('capsa.team_management'))->assertOk()->assertDontSee('Team1');
        $this->actingAs($otherCapsa, 'expert')->get(route('capsa.sections'))->assertOk()->assertSee('Team in BSIT-4A');
    }

    public function test_a_list_that_keeps_everyone_where_they_are_changes_nothing(): void
    {
        $this->promoteWithoutAList();

        $this->uploadDeansList([['student1@my.cspc.edu.ph', 'A'], ['student2@my.cspc.edu.ph', 'A']])
            ->assertJsonPath('promoted', 0)
            ->assertJsonPath('matched', 2);

        $this->assertSame('4th · A', $this->leader->fresh()->year_section);
        $this->assertSame('4th · A', $this->member->fresh()->year_section);
        $this->assertTeamIsWhole('BSIT-4A');
    }

    public function test_the_deans_list_can_come_first_and_still_keep_the_team(): void
    {
        $this->uploadDeansList([['student1@my.cspc.edu.ph', 'A'], ['student2@my.cspc.edu.ph', 'B']])
            ->assertJsonPath('promoted', 2)
            ->assertJsonPath('kept_sections', ['BSIT-4A']);
        $this->capsa = $this->capsa->fresh();

        $this->assertSame('4th · A', $this->leader->fresh()->year_section);
        $this->assertSame('4th · B', $this->member->fresh()->year_section);
        $this->assertTeamIsWhole('BSIT-4A');
    }

    /**
     * The dean's list as a CSV - the upload takes those too, and writing one
     * costs a test nothing, where an .xlsx a time ran it out of memory.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function sheet(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'roster').'.csv';
        $handle = fopen($path, 'w');
        foreach ([['Email', 'Last Name', 'First Name', 'Middle Name', 'Student No.', 'Course/Program', 'Year', 'Section'], ...$rows] as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, 'students.csv', 'text/csv', null, true);
    }
}
