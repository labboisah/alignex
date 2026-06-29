<?php

namespace Database\Factories;

use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAuditLog>
 */
class ExamAuditLogFactory extends Factory
{
    protected $model = ExamAuditLog::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'exam_session_id' => ExamSession::factory(),
            'candidate_exam_attempt_id' => CandidateExamAttempt::factory(),
            'actor_user_id' => User::factory(),
            'actor_type' => 'user',
            'event_type' => 'candidate_login',
            'description' => fake()->sentence(),
            'metadata' => ['source' => 'factory'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Factory Browser',
            'occurred_at' => now(),
        ];
    }
}
