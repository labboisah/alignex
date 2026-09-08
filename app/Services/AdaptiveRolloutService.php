<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use Illuminate\Validation\ValidationException;

class AdaptiveRolloutService
{
    public const MESSAGE = 'Adaptive delivery is not available yet. Save this exam as a draft. Existing started attempts keep their original paper.';

    public function isAdaptive(Exam $exam): bool
    {
        // Treat inconsistent legacy mode fields conservatively until explicitly reconciled.
        return $exam->mode === Exam::MODE_ADAPTIVE || $exam->exam_mode === Exam::MODE_ADAPTIVE;
    }

    public function ownerKey(Exam $exam): string
    {
        $type = $exam->effectiveOwnerType();
        $id = $exam->exam_owner_id ?? $exam->owner_id ?? match ($type) {
            Exam::OWNER_ORGANIZATION => $exam->organization_id,
            Exam::OWNER_INSTITUTION => $exam->institution_id,
            Exam::OWNER_SECONDARY_SCHOOL => $exam->secondary_school_id ?? $exam->school_id,
            Exam::OWNER_PROFESSIONAL_SCHOOL => $exam->professional_school_id,
            Exam::OWNER_CBT_CENTER => $exam->cbt_center_id ?? $exam->center_id,
            default => null,
        };

        return $type.':'.$id;
    }

    public function status(Exam $exam): array
    {
        $enabled = (bool) config('adaptive.pilot_enabled', false);
        $allowlisted = in_array($this->ownerKey($exam), config('adaptive.pilot_owners', []), true);
        $categoryEligible = in_array($exam->exam_category, [Exam::CATEGORY_ASSESSMENT, Exam::CATEGORY_PRACTICE], true)
            && $exam->effectiveOwnerType() !== Exam::OWNER_SECONDARY_SCHOOL;
        // Change only when the implementation acceptance gates are met, not via an environment toggle.
        $runtimeReady = false;

        return [
            'pilot_enabled' => $enabled,
            'owner_allowlisted' => $allowlisted,
            'category_eligible' => $categoryEligible,
            'runtime_ready' => $runtimeReady,
            'can_publish' => $enabled && $allowlisted && $categoryEligible && $runtimeReady,
            'message' => self::MESSAGE,
        ];
    }

    public function ensureDeliveryAllowed(Exam $exam): void
    {
        if ($this->isAdaptive($exam) && ! $this->status($exam)['can_publish']) {
            throw ValidationException::withMessages(['exam' => self::MESSAGE]);
        }
    }

    public function ensureAttemptAccess(CandidateExamAttempt $attempt): void
    {
        // Resume/read/finalize historical started attempts without converting their delivery mode.
        if ($attempt->started_at !== null) {
            return;
        }

        $this->ensureDeliveryAllowed($attempt->exam);
    }

    public function ensureSaveAllowed(Exam $proposed, ?Exam $existing): void
    {
        if ($existing && ($this->isAdaptive($existing) || $this->isAdaptive($proposed))
            && $existing->attempts()->whereNotNull('started_at')->exists()) {
            throw ValidationException::withMessages(['exam' => 'An exam with started attempts cannot be changed to or edited in adaptive mode. Its existing paper and scoring are preserved.']);
        }

        if ($this->isAdaptive($proposed) && in_array($proposed->status, [Exam::STATUS_SCHEDULED, Exam::STATUS_ACTIVE], true)) {
            $this->ensureDeliveryAllowed($proposed);
        }
    }
}
