<?php

namespace Database\Factories;

use App\Models\ExamType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamType>
 */
class ExamTypeFactory extends Factory
{
    protected $model = ExamType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('TYPE-###')),
            'description' => fake()->sentence(),
            'status' => ExamType::STATUS_ACTIVE,
        ];
    }
}
