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
                        'max_points' => 60,
                        'score_levels' => json_encode([
                            ['score' => 24, 'desc' => 'Excellent'],
                            ['score' => 18, 'desc' => 'Good'],
                            ['score' => 12, 'desc' => 'Fair'],
                            ['score' => 6, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'New Criterion 2',
                        'max_points' => 40,
                        'score_levels' => json_encode([
                            ['score' => 16, 'desc' => 'Excellent'],
                            ['score' => 12, 'desc' => 'Good'],
                            ['score' => 8, 'desc' => 'Fair'],
                            ['score' => 4, 'desc' => 'Poor'],
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
                    ['score' => 40, 'desc' => 'Excellent'],
                    ['score' => 30, 'desc' => 'Good'],
                    ['score' => 20, 'desc' => 'Fair'],
                    ['score' => 10, 'desc' => 'Poor'],
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
                    ['score' => 40, 'desc' => 'Excellent'],
                    ['score' => 30, 'desc' => 'Good'],
                    ['score' => 20, 'desc' => 'Fair'],
                    ['score' => 10, 'desc' => 'Poor'],
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

    public function test_store_criterion_persists_custom_score_level_labels(): void
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Label Persist',
            'rubric_type' => 'Title Defense',
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria", [
                'criterion_name' => 'Oral Presentation',
                'max_points' => 25,
                'score_levels' => json_encode([
                    ['label' => 'Outstanding', 'score' => 40, 'desc' => 'Exceeds all expectations'],
                    ['label' => 'Very Good', 'score' => 30, 'desc' => 'Meets expectations'],
                    ['label' => 'Needs Improvement', 'score' => 20, 'desc' => 'Below expectations'],
                    ['label' => 'Unsatisfactory', 'score' => 10, 'desc' => 'Fails to meet expectations'],
                ]),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'criterion_name' => 'Oral Presentation',
                    'score_levels' => [
                        ['label' => 'Outstanding', 'score' => 40, 'desc' => 'Exceeds all expectations'],
                        ['label' => 'Very Good', 'score' => 30, 'desc' => 'Meets expectations'],
                        ['label' => 'Needs Improvement', 'score' => 20, 'desc' => 'Below expectations'],
                        ['label' => 'Unsatisfactory', 'score' => 10, 'desc' => 'Fails to meet expectations'],
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
            'max_points' => 50,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 12.5, 'desc' => 'A'],
                ['label' => 'Good', 'score' => 9.375, 'desc' => 'B'],
                ['label' => 'Fair', 'score' => 6.25, 'desc' => 'C'],
                ['label' => 'Poor', 'score' => 3.125, 'desc' => 'D'],
            ]),
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubric->rubric_id}/criteria/{$criterion->criterion_id}", [
                'criterion_name' => 'Presentation',
                'max_points' => 50,
                'score_levels' => json_encode([
                    ['label' => 'Outstanding', 'score' => 40, 'desc' => 'A+'],
                    ['label' => 'Very Good', 'score' => 30, 'desc' => 'B+'],
                    ['label' => 'Needs Improvement', 'score' => 20, 'desc' => 'C+'],
                    ['label' => 'Unsatisfactory', 'score' => 10, 'desc' => 'D+'],
                ]),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'score_levels' => [
                        ['label' => 'Outstanding', 'score' => 40, 'desc' => 'A+'],
                        ['label' => 'Very Good', 'score' => 30, 'desc' => 'B+'],
                        ['label' => 'Needs Improvement', 'score' => 20, 'desc' => 'C+'],
                        ['label' => 'Unsatisfactory', 'score' => 10, 'desc' => 'D+'],
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
                        'max_points' => 100,
                        'score_levels' => json_encode([
                            ['label' => 'Outstanding', 'score' => 40, 'desc' => 'Exceptional'],
                            ['label' => 'Very Good', 'score' => 30, 'desc' => 'Above average'],
                            ['label' => 'Needs Improvement', 'score' => 20, 'desc' => 'Average'],
                            ['label' => 'Unsatisfactory', 'score' => 10, 'desc' => 'Below average'],
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
                                ['label' => 'Outstanding', 'score' => 40, 'desc' => 'Exceptional'],
                                ['label' => 'Very Good', 'score' => 30, 'desc' => 'Above average'],
                                ['label' => 'Needs Improvement', 'score' => 20, 'desc' => 'Average'],
                                ['label' => 'Unsatisfactory', 'score' => 10, 'desc' => 'Below average'],
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
                        'max_points' => 100,
                        'score_levels' => json_encode([
                            ['score' => 30, 'desc' => 'Excellent'],
                            ['score' => 30, 'desc' => 'Good'],
                            ['score' => 30, 'desc' => 'Fair'],
                            ['score' => 10, 'desc' => 'Poor'],
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
                        'max_points' => 25,
                        'score_levels' => json_encode([
                            ['score' => 10, 'desc' => 'Excellent'],
                            ['score' => 8, 'desc' => 'Good'],
                            ['score' => 5, 'desc' => 'Fair'],
                            ['score' => 2, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C2',
                        'max_points' => 25,
                        'score_levels' => json_encode([
                            ['score' => 10, 'desc' => 'Excellent'],
                            ['score' => 8, 'desc' => 'Good'],
                            ['score' => 5, 'desc' => 'Fair'],
                            ['score' => 2, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C3',
                        'max_points' => 25,
                        'score_levels' => json_encode([
                            ['score' => 10, 'desc' => 'Excellent'],
                            ['score' => 8, 'desc' => 'Good'],
                            ['score' => 5, 'desc' => 'Fair'],
                            ['score' => 2, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C4',
                        'max_points' => 25,
                        'score_levels' => json_encode([
                            ['score' => 10, 'desc' => 'Excellent'],
                            ['score' => 8, 'desc' => 'Good'],
                            ['score' => 5, 'desc' => 'Fair'],
                            ['score' => 2, 'desc' => 'Poor'],
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
                        'max_points' => 33.33,
                        'score_levels' => json_encode([
                            ['score' => 10, 'desc' => 'Excellent'],
                            ['score' => 10, 'desc' => 'Good'],
                            ['score' => 10, 'desc' => 'Fair'],
                            ['score' => 3.33, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C2',
                        'max_points' => 33.33,
                        'score_levels' => json_encode([
                            ['score' => 10, 'desc' => 'Excellent'],
                            ['score' => 10, 'desc' => 'Good'],
                            ['score' => 10, 'desc' => 'Fair'],
                            ['score' => 3.33, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C3',
                        'max_points' => 33.34,
                        'score_levels' => json_encode([
                            ['score' => 10, 'desc' => 'Excellent'],
                            ['score' => 10, 'desc' => 'Good'],
                            ['score' => 10, 'desc' => 'Fair'],
                            ['score' => 3.34, 'desc' => 'Poor'],
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
                'message' => 'Total score-level points must equal exactly 100 points.',
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
                'message' => 'Total score-level points must equal exactly 100 points.',
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
                        'max_points' => 60,
                        'score_levels' => json_encode([
                            ['score' => 30, 'desc' => 'Excellent'],
                            ['score' => 20, 'desc' => 'Good'],
                            ['score' => 10, 'desc' => 'Fair'],
                            ['score' => 5, 'desc' => 'Poor'],
                        ]),
                    ],
                    [
                        'criterion_name' => '  Skills  ',
                        'max_points' => 40,
                        'score_levels' => json_encode([
                            ['score' => 20, 'desc' => 'Excellent'],
                            ['score' => 10, 'desc' => 'Good'],
                            ['score' => 5, 'desc' => 'Fair'],
                            ['score' => 0, 'desc' => 'Poor'],
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
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $rubric->criteria()->get());
    }

    public function test_sync_accepts_any_distribution_that_totals_100(): void
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
                            ['label' => 'Excellent', 'score' => 10, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 40, 'desc' => 'Valid performance description.'],
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
                'message' => 'Total score-level points must equal exactly 100 points.',
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
                'message' => 'Total score-level points must equal exactly 100 points.',
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
                'message' => 'Total score-level points must equal exactly 100 points.',
            ]);

        $this->assertCount(0, $rubric->criteria()->get());
    }

    public function test_sync_accepts_equal_25_per_level(): void
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
                            ['label' => 'Excellent', 'score' => 25, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 25, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 25, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 25, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_sync_accepts_50_20_20_10_distribution(): void
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
                            ['label' => 'Excellent', 'score' => 50, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Valid performance description.'],
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
                'message' => 'Total score-level points must equal exactly 100 points.',
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
                            ['label' => 'Excellent', 'score' => 40, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 20, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Valid performance description.'],
                        ]),
                    ],
                    [
                        'criterion_name' => 'C2',
                        'score_levels' => json_encode([
                            ['label' => 'Excellent', 'score' => 0, 'desc' => 'Valid performance description.'],
                            ['label' => 'Good', 'score' => 0, 'desc' => 'Valid performance description.'],
                            ['label' => 'Fair', 'score' => 0, 'desc' => 'Valid performance description.'],
                            ['label' => 'Poor', 'score' => 0, 'desc' => 'Valid performance description.'],
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
                'message' => 'Total score-level points must equal exactly 100 points.',
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
                'message' => 'Total score-level points must equal exactly 100 points.',
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
                'message' => 'Total score-level points must equal exactly 100 points.',
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
                    ['label' => 'Excellent', 'score' => 10, 'desc' => 'Valid performance description.'],
                    ['label' => 'Good', 'score' => 20, 'desc' => 'Valid performance description.'],
                    ['label' => 'Fair', 'score' => 30, 'desc' => 'Valid performance description.'],
                    ['label' => 'Poor', 'score' => 40, 'desc' => 'Valid performance description.'],
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
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
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
                            ['label' => 'Excellent', 'score' => 30, 'desc' => 'Excellent performance.'],
                            ['label' => 'Good', 'score' => 30, 'desc' => 'Good performance.'],
                            ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair performance.'],
                            ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor performance.'],
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
}
