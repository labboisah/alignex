<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'candidate_id',
    'exam_participant_id',
    'participant_type',
    'participant_id',
    'exam_id',
    'exam_session_id',
    'center_id',
    'access_code_hash',
    'access_code',
    'payment_status',
    'payment_reference',
    'attempt_number',
    'retake_of_attempt_id',
    'retake_starts_at',
    'retake_ends_at',
    'retake_duration_minutes',
    'retake_reason',
    'retake_scheduled_by',
    'retake_cancelled_at',
    'status',
    'started_at',
    'server_due_at',
    'submitted_at',
    'auto_submitted_at',
    'disqualified_at',
    'disqualification_reason',
    'score',
    'total_questions',
    'total_marks',
    'percentage',
    'grade',
    'duration_used_seconds',
    'suspicious_event_count',
    'certificate_eligible',
    'result_status',
    'result_hash',
    'device_fingerprint_hash',
    'device_fingerprint',
    'ip_address',
    'user_agent',
])]
class CandidateExamAttempt extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_AUTO_SUBMITTED = 'auto_submitted';

    public const STATUS_DISQUALIFIED = 'disqualified';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_WAIVED = 'waived';

    public const PAYMENT_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'retake_starts_at' => 'datetime',
            'retake_ends_at' => 'datetime',
            'retake_duration_minutes' => 'integer',
            'retake_cancelled_at' => 'datetime',
            'started_at' => 'datetime',
            'server_due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'auto_submitted_at' => 'datetime',
            'disqualified_at' => 'datetime',
            'access_code' => 'encrypted',
            'score' => 'decimal:2',
            'total_questions' => 'integer',
            'total_marks' => 'decimal:2',
            'percentage' => 'decimal:2',
            'duration_used_seconds' => 'integer',
            'suspicious_event_count' => 'integer',
            'certificate_eligible' => 'boolean',
        ];
    }

    public function accessStartsAt(): ?Carbon
    {
        return $this->retake_of_attempt_id ? $this->retake_starts_at : $this->exam?->starts_at;
    }

    public function accessEndsAt(): ?Carbon
    {
        return $this->retake_of_attempt_id ? $this->retake_ends_at : $this->exam?->ends_at;
    }

    public function durationMinutes(): int
    {
        return (int) ($this->retake_duration_minutes ?? $this->exam?->duration_minutes);
    }

    /** Pending retakes never hide the most recent completed traditional result. */
    public function scopeCurrentResult(Builder $query): Builder
    {
        return $query->whereIn('candidate_exam_attempts.status', [self::STATUS_SUBMITTED, self::STATUS_AUTO_SUBMITTED])
            ->whereNotIn('candidate_exam_attempts.id', AdaptiveAttemptState::select('attempt_id'))
            ->whereNotExists(function ($newer): void {
                $newer->selectRaw('1')->from('candidate_exam_attempts as newer')
                    ->whereColumn('newer.exam_id', 'candidate_exam_attempts.exam_id')
                    ->where(function ($identity): void {
                        $identity->whereColumn('newer.candidate_id', 'candidate_exam_attempts.candidate_id')
                            ->orWhere(function ($participant): void {
                                $participant->whereNull('newer.candidate_id')->whereNull('candidate_exam_attempts.candidate_id')
                                    ->whereColumn('newer.exam_participant_id', 'candidate_exam_attempts.exam_participant_id');
                            });
                    })
                    ->whereNull('newer.deleted_at')
                    ->whereIn('newer.status', [self::STATUS_SUBMITTED, self::STATUS_AUTO_SUBMITTED])
                    ->whereNotIn('newer.id', AdaptiveAttemptState::select('attempt_id'))
                    ->whereColumn('newer.attempt_number', '>', 'candidate_exam_attempts.attempt_number');
            });
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examParticipant(): BelongsTo
    {
        return $this->belongsTo(ExamParticipant::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CandidateAnswer::class);
    }

    public function papers(): HasMany
    {
        return $this->hasMany(CandidatePaper::class, 'attempt_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ExamAuditLog::class);
    }

    public function proctoringEvents(): HasMany
    {
        return $this->hasMany(ProctoringEvent::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class, 'candidate_exam_attempt_id');
    }
}
