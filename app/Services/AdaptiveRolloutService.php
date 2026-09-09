<?php

namespace App\Services;

use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveOfflinePackage;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use Illuminate\Validation\ValidationException;

class AdaptiveRolloutService
{
    public const MESSAGE = 'This adaptive exam is not open for new levels. Check its availability and delivery settings. Existing started levels keep their original time and answers.';

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
        $pilot = app(AdaptivePilotService::class)->control($exam);
        $approved = $pilot?->online_enabled ?? false;
        $enabled = $approved;
        $categoryEligible = in_array($exam->exam_category, [Exam::CATEGORY_ASSESSMENT, Exam::CATEGORY_PRACTICE], true)
            && in_array($exam->effectiveOwnerType(), [Exam::OWNER_ORGANIZATION, Exam::OWNER_INSTITUTION, Exam::OWNER_PROFESSIONAL_SCHOOL, Exam::OWNER_CBT_CENTER, Exam::OWNER_SECONDARY_SCHOOL], true);
        $runtimeReady = true; // Online descriptive diagnostics only; not validated ability scoring.
        $online = in_array($exam->delivery_mode, ['online', 'hybrid'], true);

        return [
            'pilot_enabled' => $enabled,
            'owner_allowlisted' => $pilot !== null,
            'category_eligible' => $categoryEligible,
            'runtime_ready' => $runtimeReady,
            'exam_allowlisted' => $approved,
            'online_eligible' => $online,
            'can_publish' => ! config('adaptive.pilot_emergency_stop', false) && $enabled && $categoryEligible && $online && $runtimeReady,
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

        if ($this->isAdaptive($attempt->exam) && ! AdaptiveAttemptState::where('attempt_id', $attempt->id)->exists()) {
            throw ValidationException::withMessages(['exam' => 'This candidate has no adaptive level prepared. Ask the organizer to check the exam setup.']);
        }
        $this->ensureDeliveryAllowed($attempt->exam);
    }

    public function ensureSaveAllowed(Exam $proposed, ?Exam $existing, bool $checkPublication = true): void
    {
        if ($existing && AdaptiveOfflinePackage::where('exam_id', $existing->id)->exists()) {
            throw ValidationException::withMessages(['exam' => 'An offline package has frozen this exam. Create another exam for changed configuration.']);
        }
        if ($existing && ($this->isAdaptive($existing) || $this->isAdaptive($proposed))
            && $existing->attempts()->whereNotNull('started_at')->exists()) {
            throw ValidationException::withMessages(['exam' => 'An exam with started attempts cannot be changed to or edited in adaptive mode. Its existing paper and scoring are preserved.']);
        }

        if ($checkPublication && $this->isAdaptive($proposed) && in_array($proposed->status, [Exam::STATUS_SCHEDULED, Exam::STATUS_ACTIVE], true)) {
            $proposed = clone $proposed;
            $proposed->setAttribute('id', $existing?->id);
            $status = $this->status($proposed);
            $offlineApproved = ! config('adaptive.pilot_emergency_stop', false)
                && app(AdaptivePilotService::class)->control($proposed)?->offline_enabled
                && $status['category_eligible'];
            if (! $offlineApproved) {
                $this->ensureDeliveryAllowed($proposed);
            }
        }
    }
}
