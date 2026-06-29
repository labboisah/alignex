<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use App\Models\ExamAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class CandidateExamSessionService
{
    public function makeToken(CandidateExamAttempt $attempt): string
    {
        return Crypt::encryptString(json_encode([
            'attempt_id' => $attempt->id,
            'expires_at' => now()->addHours(12)->timestamp,
        ]));
    }

    public function attemptFromRequest(Request $request): CandidateExamAttempt
    {
        $header = (string) $request->header('Authorization');
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : (string) $request->input('exam_token');

        if ($token === '') {
            throw ValidationException::withMessages(['token' => 'Exam token is required.']);
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['token' => 'Invalid exam token.']);
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['token' => 'Exam token has expired.']);
        }

        return CandidateExamAttempt::query()
            ->with(['candidate', 'exam', 'papers.question.subject', 'papers.question.options'])
            ->whereKey($payload['attempt_id'] ?? null)
            ->firstOrFail();
    }

    public function ensureWritable(CandidateExamAttempt $attempt): void
    {
        if (in_array($attempt->status, [
            CandidateExamAttempt::STATUS_SUBMITTED,
            CandidateExamAttempt::STATUS_AUTO_SUBMITTED,
            CandidateExamAttempt::STATUS_DISQUALIFIED,
        ], true)) {
            throw ValidationException::withMessages(['exam' => 'This exam attempt is already closed.']);
        }
    }

    public function remainingSeconds(CandidateExamAttempt $attempt): int
    {
        $dueAt = $attempt->server_due_at ?? now();

        return max(0, now()->diffInSeconds($dueAt, false));
    }

    public function log(Request $request, string $eventType, ?CandidateExamAttempt $attempt = null, array $metadata = [], ?string $description = null): void
    {
        ExamAuditLog::create([
            'exam_id' => $attempt?->exam_id,
            'exam_session_id' => $attempt?->exam_session_id,
            'candidate_exam_attempt_id' => $attempt?->id,
            'actor_type' => 'candidate',
            'event_type' => $eventType,
            'description' => $description ?? str($eventType)->replace('_', ' ')->headline()->toString(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
