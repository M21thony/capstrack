<?php

namespace Database\Factories;

use App\Models\CapstoneExpert;
use App\Models\EvaluationTemplate;
use App\Models\ExpertEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationTemplate>
 */
class EvaluationTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'capsa_id' => CapstoneExpert::factory(),
            'title' => fake()->words(3, true).' evaluation',
            'defense_type' => fake()->randomElement(EvaluationTemplate::STAGES),
            'roles' => array_keys(ExpertEvaluation::ROLES),
            'is_active' => true,
        ];
    }

    /**
     * With questions to answer - the standard five unless told otherwise.
     *
     * @param  array<int, string>|null  $questions
     */
    public function withQuestions(?array $questions = null): static
    {
        return $this->afterCreating(function (EvaluationTemplate $template) use ($questions) {
            foreach (array_values($questions ?? EvaluationTemplate::STANDARD_QUESTIONS) as $i => $question) {
                $template->questions()->create(['question' => $question, 'sort_order' => $i]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
