<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AdminGradingRubricController;
use App\Models\CapstoneExpert;
use App\Models\GradingRubric;
use App\Services\OllamaSSHClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
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
                $table->string('category')->nullable();
                $table->decimal('category_max_points', 6, 2)->nullable();
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

    public function test_destroy_missing_rubric_returns_404(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->deleteJson('/api/admin/grading-rubrics/999999');

        $response->assertStatus(404)
            ->assertJsonStructure(['message']);
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
                        'score_levels' => json_encode([
                            ['score' => 60, 'desc' => 'Excellent'],
                            ['score' => 45, 'desc' => 'Good'],
                            ['score' => 30, 'desc' => 'Fair'],
                            ['score' => 15, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'New Criterion 2',
                        'score_levels' => json_encode([
                            ['score' => 40, 'desc' => 'Excellent'],
                            ['score' => 30, 'desc' => 'Good'],
                            ['score' => 20, 'desc' => 'Fair'],
                            ['score' => 10, 'desc' => 'Poor'],
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
                'max_points' => 100,
                'score_levels' => json_encode([
                    ['score' => 100, 'desc' => 'Excellent'],
                    ['score' => 75, 'desc' => 'Good'],
                    ['score' => 50, 'desc' => 'Fair'],
                    ['score' => 25, 'desc' => 'Poor'],
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
            'max_points' => 100,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}", [
                'criterion_name' => 'Updated',
                'max_points' => 100,
                'score_levels' => json_encode([
                    ['score' => 100, 'desc' => 'Excellent'],
                    ['score' => 75, 'desc' => 'Good'],
                    ['score' => 50, 'desc' => 'Fair'],
                    ['score' => 25, 'desc' => 'Poor'],
                ]),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Criterion updated successfully.',
                'data' => [
                    'criterion_name' => 'Updated',
                    'max_points' => 100,
                ],
            ]);

        $this->assertDatabaseHas('rubric_criteria', [
            'criterion_id' => $criterion->criterion_id,
            'criterion_name' => 'Updated',
            'max_points' => 100.00,
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

    public function test_store_criterion_persists_custom_score_level_labels(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Label Persist',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria", [
                'criterion_name' => 'Oral Presentation',
                'max_points' => 100,
                'score_levels' => json_encode([
                    ['label' => 'Outstanding', 'score' => 100, 'desc' => 'Exceeds all expectations'],
                    ['label' => 'Very Good', 'score' => 75, 'desc' => 'Meets expectations'],
                    ['label' => 'Needs Improvement', 'score' => 50, 'desc' => 'Below expectations'],
                    ['label' => 'Unsatisfactory', 'score' => 25, 'desc' => 'Fails to meet expectations'],
                ]),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'criterion_name' => 'Oral Presentation',
                    'score_levels' => [
                        ['label' => 'Outstanding', 'score' => 100, 'desc' => 'Exceeds all expectations'],
                        ['label' => 'Very Good', 'score' => 75, 'desc' => 'Meets expectations'],
                        ['label' => 'Needs Improvement', 'score' => 50, 'desc' => 'Below expectations'],
                        ['label' => 'Unsatisfactory', 'score' => 25, 'desc' => 'Fails to meet expectations'],
                    ],
                ],
            ]);

        $criterion = $rubric->criteria()->first();
        $this->assertNotNull($criterion);

        $levels = is_string($criterion->score_levels) ? json_decode($criterion->score_levels, true) : $criterion->score_levels;
        $this->assertSame('Outstanding', $levels[0]['label']);
        $this->assertSame('Very Good', $levels[1]['label']);
        $this->assertSame('Needs Improvement', $levels[2]['label']);
        $this->assertSame('Unsatisfactory', $levels[3]['label']);
    }

    public function test_update_criterion_updates_custom_score_level_labels(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Update Labels',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $criterion = $rubric->criteria()->create([
            'criterion_name' => 'Presentation',
            'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 100, 'desc' => 'A'],
                ['label' => 'Good', 'score' => 75, 'desc' => 'B'],
                ['label' => 'Fair', 'score' => 50, 'desc' => 'C'],
                ['label' => 'Poor', 'score' => 25, 'desc' => 'D'],
            ]),
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}", [
                'criterion_name' => 'Presentation',
                'max_points' => 100,
                'score_levels' => json_encode([
                    ['label' => 'Outstanding', 'score' => 100, 'desc' => 'A+'],
                    ['label' => 'Very Good', 'score' => 75, 'desc' => 'B+'],
                    ['label' => 'Needs Improvement', 'score' => 50, 'desc' => 'C+'],
                    ['label' => 'Unsatisfactory', 'score' => 25, 'desc' => 'D+'],
                ]),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'score_levels' => [
                        ['label' => 'Outstanding', 'score' => 100, 'desc' => 'A+'],
                        ['label' => 'Very Good', 'score' => 75, 'desc' => 'B+'],
                        ['label' => 'Needs Improvement', 'score' => 50, 'desc' => 'C+'],
                        ['label' => 'Unsatisfactory', 'score' => 25, 'desc' => 'D+'],
                    ],
                ],
            ]);

        $criterion->refresh();
        $levels = is_string($criterion->score_levels) ? json_decode($criterion->score_levels, true) : $criterion->score_levels;
        $this->assertSame('Outstanding', $levels[0]['label']);
        $this->assertSame('Unsatisfactory', $levels[3]['label']);
    }

    public function test_sync_criteria_persists_custom_score_level_labels(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Sync Labels',
            'rubric_type' => 'Pre Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'Oral Defense',
                        'score_levels' => json_encode([
                            ['label' => 'Outstanding', 'score' => 100, 'desc' => 'Exceptional'],
                            ['label' => 'Very Good', 'score' => 75, 'desc' => 'Above average'],
                            ['label' => 'Needs Improvement', 'score' => 50, 'desc' => 'Average'],
                            ['label' => 'Unsatisfactory', 'score' => 25, 'desc' => 'Below average'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'criteria' => [
                        [
                            'criterion_name' => 'Oral Defense',
                            'score_levels' => [
                                ['label' => 'Outstanding', 'score' => 100, 'desc' => 'Exceptional'],
                                ['label' => 'Very Good', 'score' => 75, 'desc' => 'Above average'],
                                ['label' => 'Needs Improvement', 'score' => 50, 'desc' => 'Average'],
                                ['label' => 'Unsatisfactory', 'score' => 25, 'desc' => 'Below average'],
                            ],
                        ],
                    ],
                ],
            ]);

        $criterion = $rubric->criteria()->first();
        $this->assertNotNull($criterion);

        $levels = is_string($criterion->score_levels) ? json_decode($criterion->score_levels, true) : $criterion->score_levels;
        $this->assertSame('Outstanding', $levels[0]['label']);
        $this->assertSame('Unsatisfactory', $levels[3]['label']);
    }

    public function test_store_criterion_rejects_overlong_score_level_label(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Label Validation',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria", [
                'criterion_name' => 'Oral Presentation',
                'max_points' => 25,
                'score_levels' => json_encode([
                    ['label' => str_repeat('A', 256), 'score' => 6.25, 'desc' => 'Too long'],
                    ['label' => 'Good', 'score' => 4.69, 'desc' => 'Valid performance description.'],
                    ['label' => 'Fair', 'score' => 3.13, 'desc' => 'Valid performance description.'],
                    ['label' => 'Poor', 'score' => 1.56, 'desc' => 'Valid performance description.'],
                ]),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['score_levels']);
    }

    public function test_sync_accepts_single_criterion_of_100_points(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Single Criterion',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'Criterion 1',
                        'score_levels' => json_encode([
                            ['score' => 100, 'desc' => 'Excellent'],
                            ['score' => 75, 'desc' => 'Good'],
                            ['score' => 50, 'desc' => 'Fair'],
                            ['score' => 25, 'desc' => 'Poor'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $rubric->criteria()->get());
    }

    public function test_sync_accepts_criteria_totaling_100_points(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Multi Criterion',
            'rubric_type' => 'Pre Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['score' => 25, 'desc' => 'Excellent'],
                            ['score' => 20, 'desc' => 'Good'],
                            ['score' => 12.5, 'desc' => 'Fair'],
                            ['score' => 5, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C2',
                        'score_levels' => json_encode([
                            ['score' => 25, 'desc' => 'Excellent'],
                            ['score' => 20, 'desc' => 'Good'],
                            ['score' => 12.5, 'desc' => 'Fair'],
                            ['score' => 5, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C3',
                        'score_levels' => json_encode([
                            ['score' => 25, 'desc' => 'Excellent'],
                            ['score' => 20, 'desc' => 'Good'],
                            ['score' => 12.5, 'desc' => 'Fair'],
                            ['score' => 5, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C4',
                        'score_levels' => json_encode([
                            ['score' => 25, 'desc' => 'Excellent'],
                            ['score' => 20, 'desc' => 'Good'],
                            ['score' => 12.5, 'desc' => 'Fair'],
                            ['score' => 5, 'desc' => 'Poor'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(4, $rubric->criteria()->get());
    }

    public function test_sync_accepts_decimal_criteria_totaling_100_points(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Decimal Criterion',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['score' => 33.33, 'desc' => 'Excellent'],
                            ['score' => 25, 'desc' => 'Good'],
                            ['score' => 16.67, 'desc' => 'Fair'],
                            ['score' => 8.33, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C2',
                        'score_levels' => json_encode([
                            ['score' => 33.33, 'desc' => 'Excellent'],
                            ['score' => 25, 'desc' => 'Good'],
                            ['score' => 16.67, 'desc' => 'Fair'],
                            ['score' => 8.33, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C3',
                        'score_levels' => json_encode([
                            ['score' => 33.34, 'desc' => 'Excellent'],
                            ['score' => 25, 'desc' => 'Good'],
                            ['score' => 16.67, 'desc' => 'Fair'],
                            ['score' => 8.33, 'desc' => 'Poor'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(3, $rubric->criteria()->get());
    }

    public function test_sync_rejects_criteria_total_below_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Below Total',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'max_points' => 60,
                        'score_levels' => json_encode([
                            ['score' => 30, 'desc' => 'Excellent'],
                            ['score' => 30, 'desc' => 'Good'],
                            ['score' => 30, 'desc' => 'Fair'],
                            ['score' => 9, 'desc' => 'Poor'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_criteria_total_above_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Above Total',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'max_points' => 40,
                        'score_levels' => json_encode([
                            ['score' => 40, 'desc' => 'Excellent'],
                            ['score' => 30, 'desc' => 'Good'],
                            ['score' => 20, 'desc' => 'Fair'],
                            ['score' => 11, 'desc' => 'Poor'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejected_total_does_not_delete_existing_criteria(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Rollback Test',
            'rubric_type' => 'Title Defense',
        ]);

        $rubric->criteria()->create([
            'criterion_name' => 'Existing Criterion',
            'max_points' => 100,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'New C1',
                        'max_points' => 50,
                        'score_levels' => json_encode([
                            ['score' => 30, 'desc' => 'Excellent'],
                            ['score' => 20, 'desc' => 'Good'],
                            ['score' => 10, 'desc' => 'Fair'],
                            ['score' => 5, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'New C2',
                        'max_points' => 40,
                        'score_levels' => json_encode([
                            ['score' => 20, 'desc' => 'Excellent'],
                            ['score' => 15, 'desc' => 'Good'],
                            ['score' => 10, 'desc' => 'Fair'],
                            ['score' => 5, 'desc' => 'Poor'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422);

        $this->assertCount(1, $rubric->criteria()->get());
        $this->assertDatabaseHas('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
            'criterion_name' => 'Existing Criterion',
            'max_points' => 100.00,
        ]);
    }

    public function test_store_rejects_rubric_total_not_100(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics', [
                'rubric_name' => 'Invalid Total Rubric',
                'rubric_type' => 'pre_proposal',
                'total_points' => 90,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_points']);

        $this->assertDatabaseMissing('grading_rubrics', [
            'rubric_name' => 'Invalid Total Rubric',
        ]);
    }

    public function test_update_rejects_rubric_total_not_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Update Total Rubric',
            'rubric_type' => 'Pre-Proposal Defense',
            'total_points' => 100,
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}", [
                'rubric_name' => 'Update Total Rubric',
                'rubric_type' => 'pre_proposal',
                'total_points' => 110,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_points']);

        $this->assertDatabaseHas('grading_rubrics', [
            'rubric_id' => $rubric->rubric_id,
            'total_points' => 100.00,
        ]);
    }

    public function test_sync_rejects_criterion_with_empty_name(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Empty Name Sync',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    ['criterion_name' => 'Knowledge', 'max_points' => 50],
                    ['criterion_name' => '', 'max_points' => 50],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.1.criterion_name']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_criterion_with_whitespace_only_name(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Whitespace Name Sync',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    ['criterion_name' => '   ', 'max_points' => 100],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.criterion_name']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_criterion_with_missing_name(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Missing Name Sync',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    ['max_points' => 100],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.criterion_name']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejected_name_does_not_delete_existing_criteria(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Name Rollback Test',
            'rubric_type' => 'Title Defense',
        ]);

        $rubric->criteria()->create([
            'criterion_name' => 'Existing Criterion',
            'max_points' => 100,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    ['criterion_name' => 'New C1', 'max_points' => 50],
                    ['criterion_name' => '', 'max_points' => 50],
                ],
            ]);

        $response->assertStatus(422);

        $this->assertCount(1, $rubric->criteria()->get());
        $this->assertDatabaseHas('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
            'criterion_name' => 'Existing Criterion',
            'max_points' => 100.00,
        ]);
    }

    public function test_sync_trims_criterion_names(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Trim Names',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => '  Knowledge  ',
                        'score_levels' => json_encode([
                            ['score' => 60, 'desc' => 'Excellent'],
                            ['score' => 45, 'desc' => 'Good'],
                            ['score' => 30, 'desc' => 'Fair'],
                            ['score' => 15, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => '  Skills  ',
                        'score_levels' => json_encode([
                            ['score' => 40, 'desc' => 'Excellent'],
                            ['score' => 30, 'desc' => 'Good'],
                            ['score' => 20, 'desc' => 'Fair'],
                            ['score' => 10, 'desc' => 'Poor'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
            'criterion_name' => 'Knowledge',
            'max_points' => 60.00,
        ]);
        $this->assertDatabaseHas('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
            'criterion_name' => 'Skills',
            'max_points' => 40.00,
        ]);
    }

    public function test_store_criterion_rejects_empty_name(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Empty Store Name',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria", [
                'criterion_name' => '',
                'max_points' => 25,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criterion_name']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_store_criterion_rejects_whitespace_only_name(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Whitespace Store Name',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria", [
                'criterion_name' => '   ',
                'max_points' => 25,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criterion_name']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_update_criterion_rejects_empty_name(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Update Empty Name',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $criterion = $rubric->criteria()->create([
            'criterion_name' => 'Original',
            'max_points' => 50,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}", [
                'criterion_name' => '   ',
                'max_points' => 50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criterion_name']);

        $this->assertDatabaseHas('rubric_criteria', [
            'criterion_id' => $criterion->criterion_id,
            'criterion_name' => 'Original',
            'max_points' => 50.00,
        ]);
    }

    public function test_sync_accepts_score_levels_totaling_exactly_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Score Levels 100',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 100, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 75, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 50, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 25, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $rubric->criteria()->get());
    }

    public function test_sync_accepts_any_lower_level_distribution_as_long_as_excellent_is_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Flexible Distribution',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 100, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 60, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 35, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 15, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_sync_rejects_score_levels_totaling_99(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Ninety Nine',
            'rubric_type' => 'Pre Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 40, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 25, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 25, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 9, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_score_levels_totaling_101(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'One Oh One',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 40, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 11, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_score_levels_totaling_95(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Ninety Five',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 50, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 5, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_accepts_equal_levels_across_all_four(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Equal Distribution',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 100, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 100, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 100, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 100, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_sync_accepts_50_20_20_10_style_distribution(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Fifty Distribution',
            'rubric_type' => 'Pre Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 100, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 40, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 40, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 20, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_sync_rejects_criterion_without_score_levels_when_total_not_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'No Levels',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    ['criterion_name' => 'C1', 'max_points' => 100],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_total_spans_all_criteria(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Spanning Criteria',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 60, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 45, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 15, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C2',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 40, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $rubric->criteria()->get());
    }

    public function test_sync_rejects_when_combined_criteria_total_under_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Combined Under',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 10, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 5, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C2',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 9, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 5, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 0, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_store_criterion_rejects_when_whole_rubric_total_not_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Store Criterion Total',
            'rubric_type' => 'Pre Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria", [
                'criterion_name' => 'C1',
                'score_levels' => json_encode([
                    ['label' => 'Excellent', 'score' => 40, 'desc' => 'Valid performance description.'],
                    ['label' => 'Good', 'score' => 30, 'desc' => 'Valid performance description.'],
                    ['label' => 'Fair', 'score' => 20, 'desc' => 'Valid performance description.'],
                    ['label' => 'Poor', 'score' => 9, 'desc' => 'Valid performance description.'],
                ]),
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_update_criterion_rejects_when_whole_rubric_total_not_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Update Criterion Total',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $criterion = $rubric->criteria()->create([
            'criterion_name' => 'C1',
            'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 30, 'desc' => 'Valid performance description.'],
                ['label' => 'Good', 'score' => 30, 'desc' => 'Valid performance description.'],
                ['label' => 'Fair', 'score' => 30, 'desc' => 'Valid performance description.'],
                ['label' => 'Poor', 'score' => 10, 'desc' => 'Valid performance description.'],
            ]),
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}", [
                'criterion_name' => 'C1',
                'score_levels' => json_encode([
                    ['label' => 'Excellent', 'score' => 40, 'desc' => 'Valid performance description.'],
                    ['label' => 'Good', 'score' => 30, 'desc' => 'Valid performance description.'],
                    ['label' => 'Fair', 'score' => 20, 'desc' => 'Valid performance description.'],
                    ['label' => 'Poor', 'score' => 11, 'desc' => 'Valid performance description.'],
                ]),
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ]);
    }

    public function test_update_criterion_accepts_when_whole_rubric_total_is_100(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Update Criterion Valid',
            'rubric_type' => 'Title Defense',
        ]);

        $criterion = $rubric->criteria()->create([
            'criterion_name' => 'C1',
            'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 30, 'desc' => 'Valid performance description.'],
                ['label' => 'Good', 'score' => 30, 'desc' => 'Valid performance description.'],
                ['label' => 'Fair', 'score' => 30, 'desc' => 'Valid performance description.'],
                ['label' => 'Poor', 'score' => 10, 'desc' => 'Valid performance description.'],
            ]),
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}", [
                'criterion_name' => 'C1',
                'score_levels' => json_encode([
                    ['label' => 'Excellent', 'score' => 100, 'desc' => 'Valid performance description.'],
                    ['label' => 'Good', 'score' => 75, 'desc' => 'Valid performance description.'],
                    ['label' => 'Fair', 'score' => 50, 'desc' => 'Valid performance description.'],
                    ['label' => 'Poor', 'score' => 25, 'desc' => 'Valid performance description.'],
                ]),
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_sync_accepts_all_four_descriptions_filled(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Filled Descriptions',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 100, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 75, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 50, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 25, 'desc' => 'Poor performance.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $rubric->criteria()->get());
    }

    public function test_sync_rejects_empty_excellent_description(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Empty Excellent',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => ''],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.score_levels']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_empty_good_description(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Empty Good',
            'rubric_type' => 'Pre Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => ''],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.score_levels']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_empty_fair_description(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Empty Fair',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => ''],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.score_levels']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_empty_poor_description(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Empty Poor',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => ''],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.score_levels']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_multiple_empty_descriptions(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Multiple Empty Descs',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => ''],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => ''],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.score_levels']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_whitespace_only_description(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Whitespace Desc',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => '   '],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.score_levels']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_rejects_missing_description_key(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Missing Desc Key',
            'rubric_type' => 'Pre Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 10],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['criteria.0.score_levels']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_store_criterion_rejects_empty_description(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Store Empty Desc',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria", [
                'criterion_name' => 'C1',
                'score_levels' => json_encode([
                    ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                    ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                    ['label' => 'Fair', 'score' => 30, 'desc' => ''],
                    ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
                ]),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['score_levels']);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_update_criterion_rejects_cleared_description(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Update Cleared Desc',
            'rubric_type' => 'Title Defense',
        ]);

        $criterion = $rubric->criteria()->create([
            'criterion_name' => 'C1',
            'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
            ]),
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}", [
                'criterion_name' => 'C1',
                'score_levels' => json_encode([
                    ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                    ['label' => 'Good', 'score' => 30, 'desc' => '   '],
                    ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                    ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
                ]),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['score_levels']);

        $criterion->refresh();
        $levels = is_string($criterion->score_levels) ? json_decode($criterion->score_levels, true) : $criterion->score_levels;
        $this->assertSame('Good performance.', $levels[1]['desc']);
    }

    public function test_sync_rejected_description_does_not_delete_existing_criteria(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Desc Rollback',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $rubric->criteria()->create([
            'criterion_name' => 'Existing Criterion',
            'max_points' => 100,
            'score_levels' => '[]',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'New C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => ''],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(422);

        $this->assertCount(1, $rubric->criteria()->get());
        $this->assertDatabaseHas('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
            'criterion_name' => 'Existing Criterion',
            'max_points' => 100.00,
        ]);
    }

    public function test_valid_descriptions_are_persisted(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Persist Descriptions',
            'rubric_type' => 'Final Oral Defense',
        ]);

        $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'C1',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 100, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 75, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 50, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 25, 'desc' => 'Poor performance.'],
                        ]),
                    ],
                ],
            ])
            ->assertStatus(200);

        $criterion = $rubric->criteria()->first();
        $this->assertNotNull($criterion);

        $levels = is_string($criterion->score_levels) ? json_decode($criterion->score_levels, true) : $criterion->score_levels;
        $this->assertSame('Excellent performance.', $levels[0]['desc']);
        $this->assertSame('Good performance.', $levels[1]['desc']);
        $this->assertSame('Fair performance.', $levels[2]['desc']);
        $this->assertSame('Poor performance.', $levels[3]['desc']);
    }

    public function test_ai_generate_requires_authentication(): void
    {
        // expert.auth redirects unauthenticated requests rather than
        // returning a JSON 401, same as every other endpoint in this group.
        $response = $this->postJson('/api/admin/grading-rubrics/ai-generate', [
            'rubric_type' => 'title_defense',
        ]);

        $response->assertStatus(302);
    }

    public function test_ai_generate_validates_rubric_type(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rubric_type']);
    }

    public function test_ai_generate_rejects_invalid_rubric_type(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'not_a_real_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rubric_type']);
    }

    public function test_ai_generate_returns_draft_with_scores_totaling_exactly_100(): void
    {
        $fakeResponse = json_encode([
            'rubric_name' => 'Title Defense Rubric',
            'description' => 'Evaluates project title clarity and feasibility.',
            'criteria' => [
                [
                    'criterion_name' => 'Title Clarity',
                    'description' => 'How clear the title is.',
                    'levels' => [
                        'excellent' => 'Very clear title.',
                        'good' => 'Clear title.',
                        'fair' => 'Somewhat clear title.',
                        'poor' => 'Unclear title.',
                    ],
                ],
                [
                    'criterion_name' => 'Feasibility',
                    'description' => 'How feasible the project is.',
                    'levels' => [
                        'excellent' => 'Very feasible.',
                        'good' => 'Feasible.',
                        'fair' => 'Somewhat feasible.',
                        'poor' => 'Not feasible.',
                    ],
                ],
                [
                    'criterion_name' => 'Originality',
                    'description' => 'How original the idea is.',
                    'levels' => [
                        'excellent' => 'Highly original.',
                        'good' => 'Original.',
                        'fair' => 'Somewhat original.',
                        'poor' => 'Not original.',
                    ],
                ],
            ],
        ]);

        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn("Here you go:\n```json\n{$fakeResponse}\n```");
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'title_defense',
                'criteria_count' => 3,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.rubric_name', 'Title Defense Rubric')
            ->assertJsonCount(3, 'data.criteria');

        $criteria = $response->json('data.criteria');
        $grandTotal = 0;

        foreach ($criteria as $criterion) {
            $this->assertCount(4, $criterion['levels']);
            // max_points is the top (Excellent) level's score alone - a
            // panel picks one level, the other three are lower alternatives
            // for the same criterion, not additional points.
            $this->assertSame((float) $criterion['max_points'], (float) $criterion['levels'][0]['score']);
            $grandTotal += $criterion['max_points'];
        }

        $this->assertSame(100, $grandTotal);
    }

    public function test_ai_generate_respects_requested_criteria_count(): void
    {
        $fakeResponse = json_encode([
            'rubric_name' => 'Final Oral Defense Rubric',
            'description' => 'Evaluates the final defense.',
            'criteria' => array_map(fn ($i) => [
                'criterion_name' => "Criterion {$i}",
                'description' => 'A criterion.',
                'levels' => [
                    'excellent' => 'Excellent.',
                    'good' => 'Good.',
                    'fair' => 'Fair.',
                    'poor' => 'Poor.',
                ],
            ], range(1, 6)),
        ]);

        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')->once()->andReturn($fakeResponse);
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'final_oral_defense',
                'criteria_count' => 6,
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(6, 'data.criteria');

        $grandTotal = array_sum(array_column($response->json('data.criteria'), 'max_points'));

        $this->assertSame(100, $grandTotal);
    }

    public function test_ai_generate_returns_502_when_ollama_throws(): void
    {
        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')->once()->andThrow(new \Exception('SSH connection refused'));
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'pre_proposal',
            ]);

        $response->assertStatus(502)
            ->assertJson(['success' => false]);
    }

    public function test_ai_generate_returns_502_when_response_is_unparseable(): void
    {
        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')->once()->andReturn('Sorry, I cannot help with that.');
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'pre_oral_defense',
            ]);

        $response->assertStatus(502)
            ->assertJson(['success' => false]);
    }

    public function test_ai_generate_defaults_rubric_name_when_ai_omits_it(): void
    {
        // Uses title_defense (not pre_proposal) - pre_proposal always goes
        // through the exact-reproduction path regardless of what's mocked,
        // so it wouldn't exercise this "AI omitted rubric_name" fallback.
        $fakeResponse = json_encode([
            'criteria' => [
                [
                    'criterion_name' => 'C1',
                    'description' => 'desc',
                    'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd'],
                ],
            ],
        ]);

        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')->once()->andReturn($fakeResponse);
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'title_defense',
                'criteria_count' => 3,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rubric_name', 'Title Defense Rubric');

        // Only one criterion came back even though 3 were requested - the
        // budget split still has to land on exactly 100 for what it got.
        $this->assertSame(100, $response->json('data.criteria.0.max_points'));
    }

    public function test_ai_generate_groups_criteria_by_ai_provided_categories(): void
    {
        // final_oral_defense (not pre_proposal) - pre_proposal always uses
        // the exact-reproduction path (name-matched against real data), so
        // it wouldn't exercise the generic AI-provided-categories engine
        // this test is actually about.
        $fakeResponse = json_encode([
            'rubric_name' => 'Final Oral Defense Rubric',
            'description' => 'Evaluates the final defense.',
            'categories' => [
                ['name' => 'Manuscript', 'weight' => 20],
                ['name' => 'Topic Quality and Proposal Merit', 'weight' => 30],
                ['name' => 'Proposal Content and Clarity', 'weight' => 50],
            ],
            'criteria' => [
                ['criterion_name' => 'Delivery', 'category' => 'Manuscript', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
                ['criterion_name' => 'Slides', 'category' => 'Manuscript', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
                ['criterion_name' => 'Feasibility', 'category' => 'Topic Quality and Proposal Merit', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
                ['criterion_name' => 'Problem Statement', 'category' => 'Proposal Content and Clarity', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
                ['criterion_name' => 'Solution Scope', 'category' => 'Proposal Content and Clarity', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
            ],
        ]);

        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')->once()->andReturn($fakeResponse);
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'final_oral_defense',
                'criteria_count' => 5,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $categories = $response->json('data.categories');
        $this->assertSame([
            ['name' => 'Manuscript', 'max_points' => 20],
            ['name' => 'Topic Quality and Proposal Merit', 'max_points' => 30],
            ['name' => 'Proposal Content and Clarity', 'max_points' => 50],
        ], $categories);

        $criteria = $response->json('data.criteria');

        $manuscriptTotal = collect($criteria)->where('category', 'Manuscript')->sum('max_points');
        $topicTotal = collect($criteria)->where('category', 'Topic Quality and Proposal Merit')->sum('max_points');
        $contentTotal = collect($criteria)->where('category', 'Proposal Content and Clarity')->sum('max_points');

        $this->assertSame(20, $manuscriptTotal);
        $this->assertSame(30, $topicTotal);
        $this->assertSame(50, $contentTotal);

        $grandTotal = array_sum(array_column($criteria, 'max_points'));
        $this->assertSame(100, $grandTotal);
    }

    public function test_ai_generate_renormalizes_when_a_category_is_left_empty(): void
    {
        // Model only tags criteria into 2 of 3 AI-provided categories - the
        // 20/30 weights for Manuscript/Topic Quality should renormalize to
        // fill the full 100 rather than leaving 50 points unaccounted for.
        $fakeResponse = json_encode([
            'rubric_name' => 'Final Oral Defense Rubric',
            'categories' => [
                ['name' => 'Manuscript', 'weight' => 20],
                ['name' => 'Topic Quality and Proposal Merit', 'weight' => 30],
                ['name' => 'Proposal Content and Clarity', 'weight' => 50],
            ],
            'criteria' => [
                ['criterion_name' => 'Delivery', 'category' => 'Manuscript', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
                ['criterion_name' => 'Feasibility', 'category' => 'Topic Quality and Proposal Merit', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
            ],
        ]);

        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')->once()->andReturn($fakeResponse);
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'final_oral_defense',
            ]);

        $response->assertStatus(200);

        $categories = $response->json('data.categories');
        $this->assertCount(2, $categories);
        $this->assertSame(100, array_sum(array_column($categories, 'max_points')));

        $criteria = $response->json('data.criteria');
        $grandTotal = array_sum(array_column($criteria, 'max_points'));
        $this->assertSame(100, $grandTotal);
    }

    public function test_ai_generate_pre_proposal_uses_exact_real_points_regardless_of_ai_echo(): void
    {
        // The AI is only ever trusted for wording. Even if its echo were to
        // drift (simulated here with a garbage "category" and no numbers at
        // all, since real requests never send scores to it), the returned
        // points must still be the school's exact real numbers, matched by
        // criterion name.
        $reference = (new \ReflectionClass(AdminGradingRubricController::class))
            ->getConstant('PRE_PROPOSAL_CRITERIA');

        $fakeResponse = json_encode([
            'rubric_name' => 'Pre-Proposal Defense Rubric',
            'criteria' => array_map(fn ($c) => [
                'criterion_name' => $c['criterion_name'],
                'category' => 'some-drifted-category-the-ai-made-up',
                'description' => $c['description'],
                'levels' => array_map(fn ($lv) => $lv['desc'], $c['levels']),
            ], $reference),
        ]);

        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')->once()->andReturn($fakeResponse);
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'pre_proposal',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $categories = $response->json('data.categories');
        $this->assertSame([
            ['name' => 'Oral Examination/Presentation', 'max_points' => 20],
            ['name' => 'Topic Quality and Proposal Merit', 'max_points' => 30],
            ['name' => 'Proposal Content and Clarity', 'max_points' => 50],
        ], $categories);

        $criteria = $response->json('data.criteria');
        $this->assertCount(12, $criteria);

        $oralDelivery = collect($criteria)->firstWhere('criterion_name', 'Oral Delivery');
        $this->assertSame('Oral Examination/Presentation', $oralDelivery['category']);
        $this->assertSame(5, $oralDelivery['max_points']);
        $this->assertSame([5, 3.75, 2.5, 1.25], array_column($oralDelivery['levels'], 'score'));

        $grandTotal = array_sum(array_column($criteria, 'max_points'));
        $this->assertSame(100, $grandTotal);
    }

    public function test_ai_generate_falls_back_to_flat_when_categories_missing_for_non_preproposal_type(): void
    {
        // No "categories" key at all in the response - should behave
        // exactly like before categories existed (single implicit group).
        $fakeResponse = json_encode([
            'rubric_name' => 'Title Defense Rubric',
            'criteria' => [
                ['criterion_name' => 'C1', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
                ['criterion_name' => 'C2', 'description' => 'd', 'levels' => ['excellent' => 'a', 'good' => 'b', 'fair' => 'c', 'poor' => 'd']],
            ],
        ]);

        $mock = Mockery::mock(OllamaSSHClient::class);
        $mock->shouldReceive('warmup')->zeroOrMoreTimes();
        $mock->shouldReceive('generate')->once()->andReturn($fakeResponse);
        $this->app->instance(OllamaSSHClient::class, $mock);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson('/api/admin/grading-rubrics/ai-generate', [
                'rubric_type' => 'title_defense',
            ]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['categories' => []]]);

        $this->assertNull($response->json('data.criteria.0.category'));
        $this->assertSame(50, $response->json('data.criteria.0.max_points'));
        $this->assertSame(50, $response->json('data.criteria.1.max_points'));
    }

    public function test_sync_criteria_persists_category_fields(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Category Persist',
            'rubric_type' => 'Pre-Proposal Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/sync", [
                'criteria' => [
                    [
                        'criterion_name' => 'Oral Delivery',
                        'category' => 'Manuscript',
                        'category_max_points' => 20,
                        'score_levels' => json_encode([
                            ['score' => 100, 'desc' => 'Valid performance description.'],
                            ['score' => 75, 'desc' => 'Valid performance description.'],
                            ['score' => 50, 'desc' => 'Valid performance description.'],
                            ['score' => 25, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'criteria' => [
                        ['criterion_name' => 'Oral Delivery', 'category' => 'Manuscript', 'category_max_points' => 20.0],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('rubric_criteria', [
            'rubric_id' => $rubric->rubric_id,
            'criterion_name' => 'Oral Delivery',
            'category' => 'Manuscript',
            'category_max_points' => 20.00,
        ]);
    }
}
