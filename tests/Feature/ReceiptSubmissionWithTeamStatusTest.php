<?php

namespace Tests\Feature;

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

    public function test_receipt_submission_is_rejected_when_team_status_is_not_complete(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create([
            'team_name' => 'Test Team',
            'project_title' => 'Test Project',
            'team_status' => 'on-going',
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

    public function test_receipt_submission_succeeds_when_team_status_is_complete(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create([
            'team_name' => 'Test Team',
            'project_title' => 'Test Project',
            'team_status' => 'complete',
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
}
