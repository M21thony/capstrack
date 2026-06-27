<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    public function withCompleteProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'f_name' => fake()->firstName(),
            'l_name' => fake()->lastName(),
            'p_picture' => 'avatar-01',
            'student_number' => fake()->unique()->numerify('#########'),
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th Year · BSCS-4A',
        ]);
    }
}
