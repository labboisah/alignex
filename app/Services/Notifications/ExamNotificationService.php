<?php

namespace App\Services\Notifications;

use App\Models\Candidate;
use App\Models\Exam;
use App\Services\PlanFeatureService;
use Carbon\CarbonInterface;

class ExamNotificationService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly PlanFeatureService $planFeatures,
    ) {}

    public function sendCandidateExamDetails(Exam $exam, Candidate $candidate): array
    {
        return $this->dispatcher->dispatch(
            'candidate_exam_details',
            $this->recipient($candidate),
            $this->context($exam, $candidate),
        );
    }

    public function scheduleCandidateExamReminder(Exam $exam, Candidate $candidate, ?CarbonInterface $scheduledAt = null): array
    {
        $scheduledAt ??= $exam->starts_at?->copy()->subHour();

        if ($scheduledAt === null) {
            return [];
        }

        return $this->dispatcher->dispatch(
            'candidate_exam_reminder',
            $this->recipient($candidate),
            $this->context($exam, $candidate),
            scheduledAt: $scheduledAt,
        );
    }

    private function recipient(Candidate $candidate): array
    {
        return [
            'name' => trim($candidate->first_name.' '.$candidate->last_name),
            'email' => $candidate->email,
            'phone' => $candidate->phone,
        ];
    }

    private function context(Exam $exam, Candidate $candidate): array
    {
        $organizationName = $exam->organization?->name
            ?? $exam->secondarySchool?->name
            ?? $exam->professionalSchool?->name
            ?? $exam->cbtCenter?->name
            ?? config('app.name', 'AlignEx');

        return [
            'candidate_name' => trim($candidate->first_name.' '.$candidate->last_name),
            'exam_title' => $exam->title,
            'start_time' => $exam->starts_at?->timezone($exam->timezone ?: config('app.timezone'))->format('d M Y, g:i A') ?? 'To be announced',
            'duration' => $exam->duration_minutes ? $exam->duration_minutes.' minutes' : 'To be announced',
            'center_name' => $exam->center?->name ?? $exam->cbtCenter?->name ?? 'To be announced',
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'organization_name' => $organizationName,
            'plan_features' => $this->planFeatures->featuresForOwner($this->owner($exam)),
        ];
    }

    private function owner(Exam $exam): mixed
    {
        return $exam->organization
            ?? $exam->secondarySchool
            ?? $exam->professionalSchool
            ?? $exam->cbtCenter
            ?? null;
    }
}
