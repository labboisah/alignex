<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<CandidateExamAttempt>
 */
class CandidateExamAttemptFactory extends Factory
{
    protected $model = CandidateExamAttempt::class;

    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'exam_id' => Exam::factory(),
            'exam_session_id' => ExamSession::factory(),
            'center_id' => Center::factory(),
            'access_code_hash' => Hash::make('access-code'),
            'attempt_number' => 1,
            'status' => CandidateExamAttempt::STATUS_NOT_STARTED,
            'started_at' => null,
            'server_due_at' => null,
            'submitted_at' => null,
            'auto_submitted_at' => null,
            'disqualified_at' => null,
            'disqualification_reason' => null,
            'score' => null,
            'result_status' => null,
            'device_fingerprint_hash' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Factory Browser',
        ];
    }
}
