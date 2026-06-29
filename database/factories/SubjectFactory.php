<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'school_id' => null,
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('SUB-###')),
            'description' => fake()->sentence(),
            'status' => Subject::STATUS_ACTIVE,
        ];
    }
}
