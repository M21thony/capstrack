<?php

namespace Database\Factories;

use App\Models\CapstoneExpert;
use App\Models\ExpertAwayDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpertAwayDate>
 */
class ExpertAwayDateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+3 weeks');

        return [
            'expert_id' => CapstoneExpert::factory(),
            'starts_on' => $start->format('Y-m-d'),
            'ends_on' => (clone $start)->modify('+1 day')->format('Y-m-d'),
            'reason' => fake()->randomElement(ExpertAwayDate::REASONS),
        ];
    }
}
