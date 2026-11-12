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
            // Qualified to chair by default, so a test about something else
            // is not tripped by the Panel Chair rule.
            'highest_degree' => 'master',
            'reject_attempts' => 1,
            'created_at' => now(),
        ];
    }

    /** Someone who can sit on a panel: only a CaPA or a consultant may. */
    public function panelist(): static
    {
        return $this->afterCreating(fn (CapstoneExpert $e) => $e->roles()->attach(\App\Models\Role::firstOrCreate(['role_name' => 'adviser'])->role_id));
    }
}
