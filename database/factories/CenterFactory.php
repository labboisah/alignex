<?php

namespace Database\Factories;

use App\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Center>
 */
class CenterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' CBT Center',
            'code' => fake()->unique()->bothify('CTR-####'),
            'location' => fake()->address(),
            'capacity' => fake()->numberBetween(50, 500),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'status' => Center::STATUS_ACTIVE,
        ];
    }
}
