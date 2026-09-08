<?php

namespace App\Services;

use App\Models\AdaptiveOfflineLease;
use App\Models\AdaptivePilotControl;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdaptivePilotService
{
    public function control(Exam $exam): ?AdaptivePilotControl
    {
        return AdaptivePilotControl::where('exam_id', $exam->id)->where('owner_key', app(AdaptiveRolloutService::class)->ownerKey($exam))->first();
    }

    public function save(Exam $exam, User $actor, array $data): void
    {
        DB::transaction(function () use ($exam, $actor, $data): void {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            if (! app(AdaptiveRolloutService::class)->isAdaptive($exam) || ! in_array($exam->effectiveOwnerType(), ['organization', 'institution', 'professional_school', 'cbt_center', 'secondary_school'], true)
                || ! in_array($exam->exam_category, ['assessment', 'practice'], true)) {
                throw ValidationException::withMessages(['purpose' => 'This pilot requires a supported diagnostic assessment/practice exam.']);
            }
            AdaptivePilotControl::updateOrCreate(['exam_id' => $exam->id], [
                'owner_key' => app(AdaptiveRolloutService::class)->ownerKey($exam), 'online_enabled' => $data['online_enabled'],
                'offline_enabled' => $data['offline_enabled'], 'purpose' => $data['purpose'], 'updated_by' => $actor->id]);
            $exam->auditLogs()->create(['actor_user_id' => $actor->id, 'actor_type' => 'user', 'event_type' => 'adaptive_pilot_changed',
                'description' => 'Diagnostic pilot controls changed.', 'metadata' => $data, 'occurred_at' => now()]);
        });
    }

    public function ensureCloudCandidate(Exam $exam, string $candidate): void
    {
        if (AdaptiveOfflineLease::where('exam_id', $exam->id)->where('candidate_id', $candidate)->exists()) {
            throw ValidationException::withMessages(['exam' => 'This progression is assigned to an offline center. Its cloud start is reserved to prevent duplicate delivery.']);
        }
    }
}
