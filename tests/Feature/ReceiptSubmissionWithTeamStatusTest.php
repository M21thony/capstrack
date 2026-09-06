<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReceiptSubmissionWithTeamStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_submission_is_rejected_before_the_team_submits_its_pre_proposals(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create([
            'team_name' => 'Test Team',
            'project_title' => 'Test Project',
            'team_status' => 'Active',
        ]);
        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
        ]);
        $expensePost = ExpensePost::create([
            'title' => 'Test Expense',
            'amount' => 100.00,
            'description' => 'Test description',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');

        $this->actingAs($student)
            ->post(route('student.financial.submit'), [
                'expense_post_id' => $expensePost->id,
                'receipt_image' => $file,
                'receipt_number' => 'RCPT-001',
            ])
            ->assertSessionHasErrors('team_status');

        $this->assertCount(0, FinancialRecord::all());
    }

    public function test_receipt_submission_succeeds_once_the_team_has_submitted_its_pre_proposals(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create([
            'team_name' => 'Test Team',
            'project_title' => 'Test Project',
            'team_status' => 'Active',
        ]);
        $sy = StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
        ]);
        $this->submitPreProposalsFor($team, $sy);
        $expensePost = ExpensePost::create([
            'title' => 'Test Expense',
            'amount' => 100.00,
            'description' => 'Test description',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');

        $this->actingAs($student)
            ->post(route('student.financial.submit'), [
                'expense_post_id' => $expensePost->id,
                'receipt_image' => $file,
                'receipt_number' => 'RCPT-001',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertCount(1, FinancialRecord::all());
    }

    public function test_receipt_submission_is_rejected_when_student_has_no_team(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $expensePost = ExpensePost::create([
            'title' => 'Test Expense',
            'amount' => 100.00,
            'description' => 'Test description',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');

        $this->actingAs($student)
            ->post(route('student.financial.submit'), [
                'expense_post_id' => $expensePost->id,
                'receipt_image' => $file,
                'receipt_number' => 'RCPT-001',
            ])
            ->assertSessionHasErrors('team_status');

        $this->assertCount(0, FinancialRecord::all());
    }

    /** Turns this team's pre-proposals in, which is what unlocks receipts. */
    private function submitPreProposalsFor(Team $team, StudentSchoolYear $sy): void
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'duplication-checks/receipt-test.pdf',
            'concepts' => ['title' => 'Test'],
            'top_score' => 20,
            'verdict' => 'Passed',
            'matches' => [],
            'instructors_suggested' => [],
        ]);

        CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => 'A Perfectly Reasonable Capstone Title For This Particular Team',
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile',
            'duplication_status' => 'free',
            'approved_status' => false,
            'submitted_at' => now(),
        ]);
    }
}
