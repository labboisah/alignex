<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionBank>
 */
class QuestionBankFactory extends Factory
{
    protected $model = QuestionBank::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'school_id' => null,
            'subject_id' => Subject::factory(),
            'created_by' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('QB-###')),
            'description' => fake()->sentence(),
            'status' => QuestionBank::STATUS_ACTIVE,
        ];
    }
}
