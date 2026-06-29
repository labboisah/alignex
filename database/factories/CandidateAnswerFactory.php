<?php

namespace Database\Factories;

use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateAnswer>
 */
class CandidateAnswerFactory extends Factory
{
    protected $model = CandidateAnswer::class;

    public function definition(): array
    {
        return [
            'candidate_exam_attempt_id' => CandidateExamAttempt::factory(),
            'question_id' => Question::factory(),
            'subject_id' => Subject::factory(),
            'scored_by' => null,
            'answer_payload' => ['selected' => ['A']],
            'selected_option_ids' => [],
            'answer_text' => null,
            'is_flagged' => false,
            'saved_at' => now(),
            'submitted_at' => null,
            'score_awarded' => null,
            'scored_at' => null,
        ];
    }
}
