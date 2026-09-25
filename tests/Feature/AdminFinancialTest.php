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
                'course' => 'BSIT',
                'defense_type' => 'Pre Oral Defense',
                'amount' => 750,
                'description' => "Cashier's Office",
            ]
        );

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('expense_posts', [
            'title' => 'Pre Oral Defense Fee',
            'defense_type' => 'Pre Oral Defense',
            'admin_id' => $this->admin->expert_id,
        ]);
    }

    public function test_pre_proposal_defense_cannot_have_a_fee_and_a_defense_gets_one_fee_per_program(): void
    {
        $this->actingAsExpertOnly()
            ->postJson(route('api.admin.financial.expense-posts.store'), ['course' => 'BSIT', 'defense_type' => 'Pre-proposal Defense', 'amount' => 100])
            ->assertStatus(422);

        $fee = ['course' => 'BSIT', 'defense_type' => 'Title Defense', 'amount' => 500];
        $this->actingAsExpertOnly()->postJson(route('api.admin.financial.expense-posts.store'), $fee)->assertStatus(201);
        $this->actingAsExpertOnly()->postJson(route('api.admin.financial.expense-posts.store'), $fee)->assertStatus(422);
    }

    public function test_several_waiting_receipts_are_verified_at_once(): void
    {
        $post = $this->makeExpensePost();
        $records = collect(['pending', 'pending', 'rejected'])->map(fn ($status) => FinancialRecord::create([
            'student_id' => Student::factory()->withCompleteProfile()->create()->student_id,
            'expense_post_id' => $post->id, 'receipt_image' => 'receipts/x.jpg',
            'f_record_status' => $status, 'payment_for' => $post->title, 'amount' => $post->amount, 'uploaded_at' => now(),
        ]));

        $this->actingAsExpertOnly()
            ->patchJson(route('api.admin.financial.update-many'), ['ids' => $records->pluck('id')->all(), 'status' => 'approved'])
            ->assertOk()
            ->assertJsonCount(2, 'data.verified');

        // A receipt that was sent back is not verified by a bulk action.
        $this->assertSame(['verified', 'verified', 'rejected'], $records->map(fn ($r) => $r->fresh()->f_record_status)->all());
    }

    /** One with an OR number is checked against its photo, never verified unopened. */
    public function test_bulk_verify_leaves_receipts_with_an_or_number_waiting(): void
    {
        $post = $this->makeExpensePost();
        $records = collect([null, 'OR-7788'])->map(fn ($or) => FinancialRecord::create([
            'student_id' => Student::factory()->withCompleteProfile()->create()->student_id,
            'expense_post_id' => $post->id, 'receipt_image' => 'receipts/x.jpg', 'receipt_number' => $or,
            'f_record_status' => 'pending', 'payment_for' => $post->title, 'amount' => $post->amount, 'uploaded_at' => now(),
        ]));

        $this->actingAsExpertOnly()
            ->patchJson(route('api.admin.financial.update-many'), ['ids' => $records->pluck('id')->all(), 'status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.verified', [$records[0]->id])
            ->assertJsonPath('data.skipped', [$records[1]->id]);

        $this->assertSame(['verified', 'pending'], $records->map(fn ($r) => $r->fresh()->f_record_status)->all());
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

        // A receipt with an OR number is only verified once the number on the photo is confirmed.
        $this->actingAsExpertOnly()->patchJson(route('api.admin.financial.update', $record->id), ['status' => 'approved'])
            ->assertStatus(422);
        $this->assertSame('pending', $record->fresh()->f_record_status);

        $response = $this->actingAsExpertOnly()->patchJson(
            route('api.admin.financial.update', $record->id),
            ['status' => 'approved', 'or_confirmed' => true]
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
