<?php

namespace Tests\Feature;

use App\Http\Controllers\Capsa\YearPromotionController;
use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Year Promotion, both ways: a CaPSA/TSA picks sections and moves them up a
 * year, or uploads the new school year's list and the year comes from it.
 * Accounts and teams stay; the CaPSA/TSA chooses whether to stay on.
 */
class YearPromotionTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections = ['BSIT-3A']): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => $sections]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function student(string $email, string $yearSection, ?Team $team = null): Student
    {
        $s = Student::factory()->withCompleteProfile()->create([
            'email' => $email,
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => $yearSection,
        ]);
        StudentSchoolYear::create(['student_id' => $s->student_id, 'team_id' => $team?->team_id]);

        return $s;
    }

    public function test_the_next_year_keeps_the_section_letter(): void
    {
        $this->assertSame('4th · A', YearPromotionController::nextYearSection('3rd · A'));
        $this->assertSame('3rd · B', YearPromotionController::nextYearSection('2nd · B'));
        $this->assertNull(YearPromotionController::nextYearSection('4th · A'));
        $this->assertNull(YearPromotionController::nextYearSection('A'));
    }

    public function test_the_preview_lists_only_her_sections_below_fourth_year(): void
    {
        $capsa = $this->capsa(['BSIT-3A', 'BSIT-4A']);
        $this->student('a@my.cspc.edu.ph', '3rd · A');
        $this->student('senior@my.cspc.edu.ph', '4th · A');
        $this->student('other@my.cspc.edu.ph', '3rd · B');

        $this->actingAs($capsa, 'expert')
            ->getJson(route('capsa.students.promotion'))
            ->assertOk()
            ->assertJsonCount(1, 'sections')
            ->assertJsonPath('sections.0.from', 'BSIT-3A')
            ->assertJsonPath('sections.0.to', 'BSIT-4A')
            ->assertJsonPath('sections.0.already_mine', true)
            ->assertJsonCount(1, 'sections.0.students');
    }

    public function test_moving_a_section_up_keeps_the_team_and_can_keep_the_capsa(): void
    {
        $capsa = $this->capsa(['BSIT-3A']);
        $team = Team::create(['team_name' => 'Alpha', 'team_status' => 'Active', 'leader_id' => 1]);
        $a = $this->student('a@my.cspc.edu.ph', '3rd · A', $team);
        $repeater = $this->student('b@my.cspc.edu.ph', '3rd · A', $team);
        $elsewhere = $this->student('c@my.cspc.edu.ph', '3rd · B');

        $this->actingAs($capsa, 'expert')
            ->postJson(route('capsa.students.promotion.store'), [
                'sections' => ['BSIT-3A', 'BSIT-3B'],
                'exclude' => [$repeater->student_id],
                'stay_on' => ['BSIT-4A'],
            ])
            ->assertOk()
            ->assertJsonPath('promoted', 1)
            ->assertJsonPath('kept_sections', ['BSIT-4A']);

        $this->assertSame('4th · A', $a->fresh()->year_section);
        $this->assertSame('3rd · A', $repeater->fresh()->year_section);
        // Not her section: named in the request, left alone.
        $this->assertSame('3rd · B', $elsewhere->fresh()->year_section);
        $this->assertSame($team->team_id, StudentSchoolYear::where('student_id', $a->student_id)->value('team_id'));
        $this->assertSame(['BSIT-3A', 'BSIT-4A'], $capsa->fresh()->sections);
    }

    public function test_a_section_another_capsa_covers_is_not_taken(): void
    {
        $capsa = $this->capsa(['BSIT-3A']);
        $this->capsa(['BSIT-4A']);
        $a = $this->student('a@my.cspc.edu.ph', '3rd · A');

        $this->actingAs($capsa, 'expert')
            ->postJson(route('capsa.students.promotion.store'), ['sections' => ['BSIT-3A'], 'stay_on' => ['BSIT-4A']])
            ->assertOk()
            ->assertJsonPath('kept_sections', []);

        $this->assertSame('4th · A', $a->fresh()->year_section);
        $this->assertSame(['BSIT-3A'], $capsa->fresh()->sections);
    }

    public function test_the_class_list_upload_reads_the_year_from_the_list(): void
    {
        $capsa = $this->capsa(['BSIT-3A']);
        $team = Team::create(['team_name' => 'Alpha', 'team_status' => 'Active', 'leader_id' => 1]);
        $a = $this->student('a@my.cspc.edu.ph', '3rd · A', $team);
        $this->actingAs($capsa, 'expert');

        $row = ['a@my.cspc.edu.ph', 'Doe', 'Ann', '', '', 'BSIT', '4', 'A'];

        $this->post(route('capsa.rosters.students.preview'), ['file' => $this->sheet([$row])])
            ->assertOk()
            ->assertJsonPath('promotions.0.from', 'BSIT-3A')
            ->assertJsonPath('promotions.0.to', 'BSIT-4A')
            ->assertJsonPath('promotions.0.students', 1)
            ->assertJsonPath('rows.0.promotion.to', 'BSIT-4A');

        $this->post(route('capsa.rosters.students.store'), ['file' => $this->sheet([$row]), 'stay_on' => ['BSIT-4A']])
            ->assertOk()
            ->assertJsonPath('promoted', 1)
            ->assertJsonPath('kept_sections', ['BSIT-4A']);

        $this->assertSame('4th · A', $a->fresh()->year_section);
        $this->assertSame($team->team_id, StudentSchoolYear::where('student_id', $a->student_id)->value('team_id'));
        $this->assertSame(['BSIT-3A', 'BSIT-4A'], $capsa->fresh()->sections);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function sheet(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray(
            [['Email', 'Last Name', 'First Name', 'Middle Name', 'Student No.', 'Course/Program', 'Year', 'Section'], ...$rows],
            null,
            'A1'
        );

        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'students.xlsx', null, null, true);
    }
}
