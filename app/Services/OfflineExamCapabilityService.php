<?php

namespace App\Services;

use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveProgression;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class OfflineExamCapabilityService
{
    public const PACKAGE_CONTRACT = 'alignex.fixed-paper.v1';

    public function ensureExportAllowed(Exam $exam, User $actor): void
    {
        // Offline capability is independent of the online pilot allowlist.
        // Frozen bindings also protect exams whose mutable mode labels changed.
        $adaptive = app(AdaptiveRolloutService::class)->isAdaptive($exam)
            || AdaptiveProgression::where('exam_id', $exam->id)->exists()
            || AdaptiveAttemptState::whereIn('attempt_id',
                CandidateExamAttempt::where('exam_id', $exam->id)->select('id')
            )->exists();

        if ($adaptive) {
            $exam->auditLogs()->create([
                'actor_user_id' => $actor->id, 'actor_type' => 'user',
                'event_type' => 'adaptive_offline_export_blocked',
                'description' => 'Unsupported adaptive offline export rejected.',
                'metadata' => ['supported_contract' => self::PACKAGE_CONTRACT], 'occurred_at' => now(),
            ]);
            throw new HttpResponseException(response()->json([
                'code' => 'adaptive_offline_unsupported',
                'message' => 'Adaptive exams cannot be imported for offline delivery. Use the online adaptive exam.',
            ], 422));
        }
    }
}
