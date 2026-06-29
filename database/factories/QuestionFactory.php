<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'question_bank_id' => QuestionBank::factory(),
            'subject_id' => Subject::factory(),
            'topic_id' => Topic::factory(),
            'created_by' => User::factory(),
            'reviewed_by' => null,
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'stem' => fake()->sentence().'?',
            'explanation' => fake()->sentence(),
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'marks' => 1,
            'negative_marks' => null,
            'status' => Question::STATUS_APPROVED,
            'scoring_metadata' => null,
            'reviewed_at' => null,
        ];
    }
}
