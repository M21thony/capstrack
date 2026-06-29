<?php

namespace Database\Factories;

use App\Models\CapstoneExpert;
use Illuminate\Database\Eloquent\Factories\Factory;

class CapstoneExpertFactory extends Factory
{
    protected $model = CapstoneExpert::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->email(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'is_admin' => false,
            'approved_status' => false,
            'max_teams' => 4,
            'reject_attempts' => 1,
            'created_at' => now(),
        ];
    }
}
