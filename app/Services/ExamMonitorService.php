<?php

namespace App\Services;

use App\Events\ExamMonitorEvent;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamMonitorService
{
    /**
     * @return array<string, int>
     */
    public function summary(Exam $exam): array
    {
        $attempts = $this->attempts($exam);

        return [
            'total_candidates' => $exam->candidates()->count(),
            'logged_in' => $attempts->whereNotNull('started_at')->count(),
            'active' => $attempts->filter(fn ($attempt) => $this->statusFor($attempt) === 'active')->count(),
            'submitted' => $attempts->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED])->count(),
            'disconnected' => $attempts->filter(fn ($attempt) => $this->statusFor($attempt) === 'disconnected')->count(),
            'disqualified' => $attempts->where('status', CandidateExamAttempt::STATUS_DISQUALIFIED)->count(),
            'suspicious' => $attempts->sum(fn ($attempt) => $this->suspiciousCount($attempt)),
        ];
    }

    /**
     * @return Collection<int, CandidateExamAttempt>
     */
    public function attempts(Exam $exam): Collection
    {
        return CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->with(['candidate', 'answers', 'auditLogs', 'proctoringEvents'])
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(Exam $exam): array
    {
        return $this->attempts($exam)
            ->map(fn (CandidateExamAttempt $attempt) => $this->row($attempt))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(CandidateExamAttempt $attempt): array
    {
        $attempt->loadMissing(['candidate', 'answers', 'auditLogs', 'proctoringEvents']);
        $answered = $attempt->answers->filter(fn ($answer) => ! empty($answer->selected_option_ids))->count();

        return [
            'attempt_id' => $attempt->id,
            'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
            'registration_number' => $attempt->candidate?->candidate_number,
            'status' => $this->statusFor($attempt),
            'progress' => $attempt->total_questions > 0 ? round(($answered / $attempt->total_questions) * 100) : 0,
            'answered_questions' => $answered,
            'total_questions' => $attempt->total_questions,
            'login_time' => $attempt->started_at?->toISOString(),
            'remaining_time' => $this->remainingSeconds($attempt),
            'remaining_time_label' => $this->formatSeconds($this->remainingSeconds($attempt)),
            'suspicious_event_count' => $this->suspiciousCount($attempt),
            'ip_address' => $attempt->ip_address,
            'last_seen_at' => $this->lastSeenAt($attempt)?->toISOString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function feed(Exam $exam, int $limit = 50): array
    {
        $audit = $exam->auditLogs()
            ->with('attempt.candidate')
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'id' => 'audit-'.$log->id,
                'type' => $this->feedType($log->event_type),
                'candidate_name' => $log->attempt?->candidate ? trim($log->attempt->candidate->first_name.' '.$log->attempt->candidate->last_name) : null,
                'registration_number' => $log->attempt?->candidate?->candidate_number,
                'message' => str($log->event_type)->replace('_', ' ')->headline()->toString(),
                'occurred_at' => $log->occurred_at?->toISOString(),
                'snapshot_url' => null,
            ]);

        $events = $exam->proctoringEvents()
            ->with('candidate')
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn ($event) => [
                'id' => 'event-'.$event->id,
                'type' => $event->event_type === 'webcam_heartbeat' ? 'webcam' : 'suspicious',
                'candidate_name' => $event->candidate ? trim($event->candidate->first_name.' '.$event->candidate->last_name) : null,
                'registration_number' => $event->candidate?->candidate_number,
                'message' => str($event->event_type)->replace('_', ' ')->headline()->toString(),
                'occurred_at' => $event->occurred_at?->toISOString(),
                'snapshot_url' => data_get($event->payload ?? [], 'snapshot_url'),
            ]);

        return $audit
            ->concat($events)
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function events(Exam $exam, int $limit = 100): array
    {
        return $exam->proctoringEvents()
            ->with('candidate')
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'candidate_name' => $event->candidate ? trim($event->candidate->first_name.' '.$event->candidate->last_name) : null,
                'registration_number' => $event->candidate?->candidate_number,
                'event_type' => $event->event_type,
                'severity' => $event->severity,
                'source' => $event->source,
                'snapshot_url' => data_get($event->payload ?? [], 'snapshot_url'),
                'occurred_at' => $event->occurred_at?->toISOString(),
                'payload' => collect($event->payload ?? [])->except(['snapshot_path', 'snapshot_url'])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function broadcast(Exam $exam, string $type, CandidateExamAttempt $attempt, array $extra = []): void
    {
        $freshAttempt = $attempt->fresh(['candidate', 'answers', 'auditLogs', 'proctoringEvents']) ?? $attempt;

        broadcast(new ExamMonitorEvent($exam->id, $type, [
            'row' => $this->row($freshAttempt),
            ...$extra,
        ]));
    }

    public function resetAttempt(Exam $exam, CandidateExamAttempt $attempt, User $actor, string $reason): CandidateExamAttempt
    {
        if ($exam->ends_at && $exam->ends_at->isPast()) {
            throw ValidationException::withMessages(['exam' => 'This exam has ended. Candidate attempts can no longer be reset.']);
        }

        return DB::transaction(function () use ($exam, $attempt, $actor, $reason): CandidateExamAttempt {
            $attempt = CandidateExamAttempt::query()
                ->where('exam_id', $exam->id)
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attempt->answers()->update([
                'submitted_at' => null,
                'score_awarded' => null,
                'scored_at' => null,
            ]);

            $startedAt = $attempt->started_at ?? now();
            $dueAt = now()->addMinutes((int) $exam->duration_minutes);

            if ($exam->ends_at && $exam->ends_at->lessThan($dueAt)) {
                $dueAt = $exam->ends_at;
            }

            $attempt->update([
                'status' => CandidateExamAttempt::STATUS_IN_PROGRESS,
                'started_at' => $startedAt,
                'server_due_at' => $dueAt,
                'submitted_at' => null,
                'auto_submitted_at' => null,
                'disqualified_at' => null,
                'disqualification_reason' => null,
                'score' => null,
                'result_status' => null,
                'result_hash' => null,
            ]);

            ExamAuditLog::query()->create([
                'exam_id' => $exam->id,
                'exam_session_id' => $attempt->exam_session_id,
                'candidate_exam_attempt_id' => $attempt->id,
                'actor_user_id' => $actor->id,
                'actor_type' => 'supervisor',
                'event_type' => 'candidate_reset',
                'description' => 'Candidate attempt reset by supervisor.',
                'metadata' => [
                    'reason' => $reason,
                    'reset_reason_category' => str_contains(strtolower($reason), 'device') ? 'device_change' : 'supervisor_intervention',
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'occurred_at' => now(),
            ]);

            $this->broadcast($exam, 'candidate_reset', $attempt, ['reason' => $reason]);

            return $attempt->refresh();
        });
    }

    public function remainingSeconds(CandidateExamAttempt $attempt): int
    {
        if (! $attempt->server_due_at || in_array($attempt->status, [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED, CandidateExamAttempt::STATUS_DISQUALIFIED], true)) {
            return 0;
        }

        return max(0, now()->diffInSeconds($attempt->server_due_at, false));
    }

    private function statusFor(CandidateExamAttempt $attempt): string
    {
        if ($attempt->status === CandidateExamAttempt::STATUS_DISQUALIFIED) {
            return 'disqualified';
        }

        if (in_array($attempt->status, [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED], true)) {
            return 'submitted';
        }

        if (! $attempt->started_at) {
            return 'not_started';
        }

        if ($this->remainingSeconds($attempt) <= 0) {
            return 'disconnected';
        }

        $lastSeen = $this->lastSeenAt($attempt);

        if ($lastSeen && $lastSeen->lessThan(now()->subMinutes(2))) {
            return 'disconnected';
        }

        return 'active';
    }

    private function suspiciousCount(CandidateExamAttempt $attempt): int
    {
        return $attempt->proctoringEvents
            ->whereIn('severity', ['warning', 'high', 'critical'])
            ->count();
    }

    private function lastSeenAt(CandidateExamAttempt $attempt)
    {
        return collect([
            $attempt->started_at,
            $attempt->answers->max('saved_at'),
            $attempt->auditLogs->max('occurred_at'),
            $attempt->proctoringEvents->max('occurred_at'),
        ])->filter()->max();
    }

    private function feedType(string $eventType): string
    {
        return match ($eventType) {
            'login_success' => 'login',
            'answer_saved' => 'answer_saved',
            'submission', 'auto_submit' => 'submitted',
            'candidate_reset' => 'candidate_reset',
            'disqualified' => 'disqualified',
            'disconnected' => 'disconnected',
            default => str_contains($eventType, 'focus') || str_contains($eventType, 'tab') || str_contains($eventType, 'blur') || str_contains($eventType, 'fullscreen') || str_contains($eventType, 'copy') || str_contains($eventType, 'paste') || str_contains($eventType, 'right_click') || str_contains($eventType, 'print_screen') ? 'suspicious' : $eventType,
        };
    }

    private function formatSeconds(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
