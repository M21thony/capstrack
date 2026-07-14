<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\GradingRubric;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminGradingRubricTest extends TestCase
{
    private CapstoneExpert $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSchema();

        $this->admin = CapstoneExpert::create([
            'email' => 'admin.rubric@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Rubric',
            'is_admin' => true,
            'approved_status' => true,
        ]);
    }

    protected function ensureSchema(): void
    {
        if (! Schema::hasTable('capstone_experts')) {
            Schema::create('capstone_experts', function (Blueprint $table): void {
                $table->id('expert_id');
                $table->string('email')->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('suffix')->nullable();
                $table->string('credentials')->nullable();
                $table->string('profile_picture')->nullable();
                $table->boolean('is_admin')->default(false);
                $table->boolean('approved_status')->default(false);
                $table->unsignedInteger('max_teams')->nullable();
                $table->string('department')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedInteger('reject_attempts')->default(0);
                $table->timestamp('reject_cooldown_until')->nullable();
                $table->string('google_token')->nullable();
                $table->timestamp('google_token_expires_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('grading_rubrics')) {
            Schema::create('grading_rubrics', function (Blueprint $table): void {
                $table->id('rubric_id');
                $table->string('rubric_name');
                $table->string('rubric_type');
                $table->text('description')->nullable();
                $table->decimal('total_points', 6, 2)->default(100.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rubric_criteria')) {
            Schema::create('rubric_criteria', function (Blueprint $table): void {
                $table->id('criterion_id');
                $table->unsignedBigInteger('rubric_id');
                $table->string('criterion_name');
                $table->text('description')->nullable();
                $table->decimal('max_points', 6, 2);
                $table->text('score_levels');
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function test_index_returns_all_rubrics(): void
    {
        GradingRubric::create([
            'rubric_name' => 'Test Rubric',
            'rubric_type' => 'Pre-Proposal Defense',
            'description' => 'A test rubric',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson('/api/admin/grading-rubrics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rubrics retrieved successfully.',
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creates_rubric(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics', [
                'rubric_name' => 'New Defense Rubric',
                'rubric_type' => 'pre_proposal',
                'description' => 'Description here',
                'total_points' => 100,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Rubric created successfully.',
            ]);

        $this->assertDatabaseHas('grading_rubrics', [
            'rubric_name' => 'New Defense Rubric',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rubric_name', 'rubric_type']);
    }

    public function test_show_returns_rubric_with_criteria(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Test Show',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $rubric->criteria()->create([
            'criterion_name' => 'Presentation',
            'max_points' => 50,
            'score_levels' => json_encode([
                ['score' => 12.5, 'desc' => 'Excellent'],
                ['score' => 9.375, 'desc' => 'Good'],
                ['score' => 6.25, 'desc' => 'Fair'],
                ['score' => 3.125, 'desc' => 'Poor'],
            ]),
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson("/api/admin/grading-rubrics/{$rubric->rubric_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'rubric_name' => 'Test Show',
                    'rubric_type' => 'final_oral_defense',
                ],
            ]);

        $this->assertCount(1, $response->json('data.criteria'));
    }

    public function test_update_modifies_rubric(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Original Name',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}", [
                'rubric_name' => 'Updated Name',
                'rubric_type' => 'pre_oral_defense',
                'description' => 'Updated description',
                'total_points' => 100,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rubric updated successfully.',
                'data' => [
                    'rubric_name' => 'Updated Name',
                    'rubric_type' => 'pre_oral_defense',
                ],
            ]);

        $this->assertDatabaseHas('grading_rubrics', [
            'rubric_id' => $rubric->rubric_id,
            'rubric_name' => 'Updated Name',
        ]);
    }

    public function test_destroy_deletes_rubric_and_criteria(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'To Delete',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $rubric->criteria()->create([
            'criterion_name' => 'Criterion 1',
            'max_points' => 100,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->deleteJson("/api/admin/grading-rubrics/{$rubric->rubric_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rubric deleted successfully.',
            ]);

        $this->assertDatabaseMissing('grading_rubrics', [
            'rubric_id' => $rubric->rubric_id,
        ]);

        $this->assertDatabaseMissing('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
        ]);
    }

    public function test_sync_criteria_replaces_all_criteria(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Sync Test',
            'rubric_type' => 'Pre Oral Defense',
        ]);

        $rubric->criteria()->create([
            'criterion_name' => 'Old Criterion',
            'max_points' => 100,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'New Criterion 1',
                        'max_points' => 60,
                        'score_levels' => json_encode([
                            ['score' => 15, 'desc' => 'Excellent'],
                            ['score' => 11.25, 'desc' => 'Good'],
                            ['score' => 7.5, 'desc' => 'Fair'],
                            ['score' => 3.75, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'New Criterion 2',
                        'max_points' => 40,
                        'score_levels' => json_encode([
                            ['score' => 10, 'desc' => 'Excellent'],
                            ['score' => 7.5, 'desc' => 'Good'],
                            ['score' => 5, 'desc' => 'Fair'],
                            ['score' => 2.5, 'desc' => 'Poor'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Criteria synced successfully.',
            ]);

        $this->assertCount(2, $response->json('data.criteria'));
        $this->assertDatabaseMissing('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
            'criterion_name' => 'Old Criterion',
        ]);
    }

    public function test_store_criterion_creates_criterion(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Criterion Test',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria", [
                'criterion_name' => 'Oral Presentation',
                'max_points' => 25,
                'score_levels' => json_encode([
                    ['score' => 6.25, 'desc' => 'Excellent'],
                    ['score' => 4.69, 'desc' => 'Good'],
                    ['score' => 3.13, 'desc' => 'Fair'],
                    ['score' => 1.56, 'desc' => 'Poor'],
                ]),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Criterion created successfully.',
            ]);

        $this->assertDatabaseHas('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
            'criterion_name' => 'Oral Presentation',
        ]);
    }

    public function test_update_criterion_modifies_criterion(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Update Crit',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $criterion = $rubric->criteria()->create([
            'criterion_name' => 'Original',
            'max_points' => 50,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}", [
                'criterion_name' => 'Updated',
                'max_points' => 75,
                'score_levels' => json_encode([
                    ['score' => 18.75, 'desc' => 'Excellent'],
                    ['score' => 14.06, 'desc' => 'Good'],
                    ['score' => 9.38, 'desc' => 'Fair'],
                    ['score' => 4.69, 'desc' => 'Poor'],
                ]),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Criterion updated successfully.',
                'data' => [
                    'criterion_name' => 'Updated',
                    'max_points' => 75,
                ],
            ]);

        $this->assertDatabaseHas('rubric_criteria', [
            'criterion_id' => $criterion->criterion_id,
            'criterion_name' => 'Updated',
            'max_points' => 75.00,
        ]);
    }

    public function test_destroy_criterion_deletes_criterion(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Delete Crit',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $criterion = $rubric->criteria()->create([
            'criterion_name' => 'To Delete',
            'max_points' => 100,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->deleteJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Criterion deleted successfully.',
            ]);

        $this->assertDatabaseMissing('rubric_criteria', [
            'criterion_id' => $criterion->criterion_id,
        ]);
    }

    public function test_web_route_returns_view(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.rubric_management'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.rubric_management');
    }

    public function test_index_empty_when_no_rubrics(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->getJson('/api/admin/grading-rubrics');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_store_validates_rubric_type_enum(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics', [
                'rubric_name' => 'Invalid Type',
                'rubric_type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rubric_type']);
    }

    public function test_criterion_belongs_to_correct_rubric(): void
    {
        $rubric1 = GradingRubric::create([
            'rubric_name' => 'Rubric One',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $rubric2 = GradingRubric::create([
            'rubric_name' => 'Rubric Two',
            'rubric_type' => 'Title Defense',
        ]);

        $criterion = $rubric1->criteria()->create([
            'criterion_name' => 'Criterion for One',
            'max_points' => 100,
            'score_levels' => '[]',
        ]);

        // Try to delete criterion from wrong rubric
        $response = $this->actingAs($this->admin, 'expert')
            ->deleteJson("/api/admin/grading-rubrics/{$rubric2->rubric_id}/criteria/{$criterion->criterion_id}");

        $response->assertStatus(404);

        // Criterion should still exist
        $this->assertDatabaseHas('rubric_criteria', [
            'criterion_id' => $criterion->criterion_id,
        ]);
    }
}
