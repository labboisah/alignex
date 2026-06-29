<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'label' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'option_text' => fake()->sentence(),
            'display_order' => fake()->numberBetween(1, 4),
            'is_correct' => false,
            'score_weight' => null,
        ];
    }
}
