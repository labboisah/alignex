<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProctoringEvent>
 */
class ProctoringEventFactory extends Factory
{
    protected $model = ProctoringEvent::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'exam_session_id' => ExamSession::factory(),
            'candidate_exam_attempt_id' => CandidateExamAttempt::factory(),
            'candidate_id' => Candidate::factory(),
            'center_id' => Center::factory(),
            'reviewed_by' => null,
            'event_type' => 'focus_loss',
            'severity' => 'warning',
            'source' => 'candidate_app',
            'payload' => ['duration_seconds' => 5],
            'occurred_at' => now(),
            'reviewed_at' => null,
            'resolution_status' => null,
            'resolution_notes' => null,
        ];
    }
}
