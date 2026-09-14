<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\GradingRubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating a rubric exactly as the Rubric Management page does it: save the
 * rubric, then send every criterion in one request.
 */
class RubricManagementPageFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_capsa_creates_a_rubric_with_its_criteria_the_way_the_page_sends_it(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true]));
        $levels = fn (float $max, string $what) => json_encode([
            ['label' => 'Excellent', 'score' => $max, 'desc' => "Clear {$what}."],
            ['label' => 'Good', 'score' => $max * 0.75, 'desc' => "Mostly clear {$what}."],
            ['label' => 'Fair', 'score' => $max * 0.5, 'desc' => "Partly clear {$what}."],
            ['label' => 'Poor', 'score' => $max * 0.25, 'desc' => "Unclear {$what}."],
        ]);

        $rubricId = $this->actingAs($capsa, 'expert')
            ->postJson('/api/admin/grading-rubrics', [
                'rubric_name' => 'Title Defense Rubric', 'rubric_type' => 'title_defense',
                'description' => 'Sample', 'total_points' => 100,
            ])
            ->assertSuccessful()
            ->json('data.rubric_id');

        $this->actingAs($capsa, 'expert')
            ->putJson("/api/admin/grading-rubrics/{$rubricId}/criteria", ['criteria' => [
                ['criterion_name' => 'Problem statement', 'category' => 'Chapter 1', 'category_max_points' => 60, 'max_points' => 60, 'score_levels' => $levels(60, 'problem'), 'sort_order' => 0],
                ['criterion_name' => 'Delivery', 'category' => 'Oral', 'category_max_points' => 40, 'max_points' => 40, 'score_levels' => $levels(40, 'delivery'), 'sort_order' => 0],
            ]])
            ->assertSuccessful();

        $this->assertSame(['Problem statement', 'Delivery'], GradingRubric::find($rubricId)->criteria()->pluck('criterion_name')->all());
    }
}
