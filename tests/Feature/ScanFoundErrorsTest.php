<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pages the OWASP ZAP scan found answering with a server error (500). Each
 * now answers properly.
 */
class ScanFoundErrorsTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true, 'sections' => ['BSIT-4A']]));
        $this->student = Student::factory()->withCompleteProfile()->withTourCompleted()->create();
        $team = Team::create(['team_name' => 'Bulbasaur', 'leader_id' => $this->student->student_id]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $team->team_id]);
        $post = ExpensePost::create(['admin_id' => $this->capsa->expert_id, 'title' => 'Title Defense Fee', 'course' => 'BSIT', 'amount' => 300]);
        FinancialRecord::create([
            'student_id' => $this->student->student_id, 'expense_post_id' => $post->id, 'receipt_image' => 'receipts/r.jpg',
            'receipt_number' => 'OR 001', 'f_record_status' => 'verified', 'payment_for' => $post->title, 'amount' => 300, 'uploaded_at' => now(),
        ]);
    }

    public function test_the_students_receipts_list(): void
    {
        $this->actingAs($this->student, 'web')->getJson('/api/student/financial')
            ->assertOk()->assertJsonPath('data.submissions.0.receipt_number', 'OR 001');

        $this->actingAs($this->student, 'web')->getJson('/api/student/financial/submissions?status=verified&sort=oldest')
            ->assertOk()->assertJsonPath('data.0.receipt_number', 'OR 001');
    }

    public function test_the_capsas_fee_totals_by_team(): void
    {
        $this->actingAs($this->capsa, 'expert')->getJson('/api/admin/financial')->assertOk();
        $this->actingAs($this->capsa, 'expert')->getJson('/api/admin/financial/teams')->assertOk();
    }

    public function test_leftover_pages_send_people_where_they_belong(): void
    {
        $this->actingAs($this->student, 'web')->get('/duplication-legacy')->assertRedirect('/student/duplication');
        $this->actingAs($this->capsa, 'expert')->get('/instructor/rejected')->assertRedirect('/instructor/dashboard');
    }

    public function test_the_drive_callback_without_a_code_is_a_cancelled_connection(): void
    {
        $this->actingAs($this->capsa, 'expert')->get('/admin/gdrive/callback')
            ->assertRedirect()->assertSessionHas('error', 'Google Drive connection was cancelled.');
    }

    public function test_a_profile_name_too_long_for_the_column_is_a_message_not_an_error(): void
    {
        $this->actingAs($this->capsa, 'expert')->from(route('capsa.profile'))
            ->put(route('capsa.profile.update'), ['first_name' => str_repeat('A', 51), 'last_name' => 'Garcia'])
            ->assertRedirect(route('capsa.profile'))
            ->assertSessionHasErrors('first_name');

        $this->actingAs($this->capsa, 'expert')
            ->put(route('capsa.profile.update'), ['first_name' => '', 'last_name' => 'Garcia'])
            ->assertSessionHasErrors('first_name');

        $this->actingAs($this->capsa, 'expert')
            ->put(route('capsa.profile.update'), ['first_name' => 'Roberto', 'last_name' => 'Garcia', 'suffix' => 'Jr.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Roberto', $this->capsa->fresh()->first_name);
    }
}
