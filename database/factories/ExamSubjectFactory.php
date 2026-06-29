<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSubject>
 */
class ExamSubjectFactory extends Factory
{
    protected $model = ExamSubject::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'subject_id' => Subject::factory(),
            'display_order' => 1,
            'duration_minutes' => 60,
            'total_marks' => 100,
            'question_count' => 50,
            'selection_rules' => ['difficulty' => ['easy' => 20, 'medium' => 20, 'hard' => 10]],
            'instructions' => fake()->sentence(),
        ];
    }
}
