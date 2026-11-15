<?php

namespace Database\Factories;

use App\Models\TeamRatingRubric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamRatingRubric>
 */
class TeamRatingRubricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Rubric '.fake()->unique()->word(),
            'criteria' => collect(['Attendance', 'Output', 'Teamwork'])->map(fn (string $name) => [
                'name' => $name,
                'short' => $name,
                'levels' => array_map(fn (string $level) => $level.' '.mb_strtolower($name).'.', ['Excellent', 'Good', 'Fair', 'Weak', 'Poor']),
            ])->all(),
            'is_active' => false,
        ];
    }

    /** The rubric new ratings are made with. */
    public function inUse(): static
    {
        return $this->state(['is_active' => true]);
    }
}
