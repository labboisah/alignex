<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' School',
            'code' => fake()->unique()->bothify('SCH-####'),
            'location' => fake()->address(),
            'capacity' => fake()->numberBetween(100, 2000),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'status' => School::STATUS_ACTIVE,
        ];
    }
}
