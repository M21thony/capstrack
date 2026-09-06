<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinancialTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeCapsa(CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
        ]));
    }

    /**
     * Laravel's actingAs($user, 'expert') also calls Auth::shouldUse('expert'),
     * which changes the DEFAULT guard - masking bugs where a controller calls
     * $request->user() (default guard) instead of auth('expert')->user().
     * In production, EnsureExpertIsAuthenticated only authenticates the
     * 'expert' guard and never touches the default guard, so this helper
     * reproduces that precisely instead of using actingAs().
     */
    private function actingAsExpertOnly(): static
    {
        $this->app['auth']->guard('expert')->setUser($this->admin);

        return $this;
    }

    public function test_admin_can_create_an_expense_post(): void
    {
        $response = $this->actingAsExpertOnly()->postJson(
            route('api.admin.financial.expense-posts.store'),
            [
                'title' => 'Binding Fee',
                'course' => 'BSIT',
                'year_level' => '4th',
                'amount' => 500,
                'description' => 'Final manuscript binding',
            ]
        );

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('expense_posts', [
            'title' => 'Binding Fee',
            'admin_id' => $this->admin->expert_id,
        ]);
    }

    private function makeExpensePost(): ExpensePost
    {
        return ExpensePost::create([
            'admin_id' => $this->admin->expert_id,
            'title' => 'Binding Fee',
            'course' => 'BSIT',
            'year_level' => '4th',
            'amount' => 500,
        ]);
    }

    public function test_admin_can_approve_a_financial_record(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $post = $this->makeExpensePost();
        $record = FinancialRecord::create([
            'student_id' => $student->student_id,
            'expense_post_id' => $post->id,
            'receipt_image' => 'receipts/or-001.jpg',
            'receipt_number' => 'OR-001',
            'f_record_status' => 'pending',
            'payment_for' => $post->title,
            'amount' => $post->amount,
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAsExpertOnly()->patchJson(
            route('api.admin.financial.update', $record->id),
            ['status' => 'approved']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('financial_records', [
            'id' => $record->id,
            'f_record_status' => 'verified',
            'verified_by' => $this->admin->name,
        ]);
    }

    public function test_admin_can_reject_a_financial_record(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $post = $this->makeExpensePost();
        $record = FinancialRecord::create([
            'student_id' => $student->student_id,
            'expense_post_id' => $post->id,
            'receipt_image' => 'receipts/or-002.jpg',
            'receipt_number' => 'OR-002',
            'f_record_status' => 'pending',
            'payment_for' => $post->title,
            'amount' => $post->amount,
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAsExpertOnly()->patchJson(
            route('api.admin.financial.update', $record->id),
            ['status' => 'rejected', 'rejection_reason' => 'Illegible receipt']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('financial_records', [
            'id' => $record->id,
            'f_record_status' => 'rejected',
            'verified_by' => $this->admin->name,
        ]);
    }
}
