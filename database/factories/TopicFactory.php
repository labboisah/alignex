<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('TOP-###')),
            'description' => fake()->sentence(),
            'status' => Topic::STATUS_ACTIVE,
        ];
    }
}
