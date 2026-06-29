<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exam_id',
    'owner_type',
    'owner_id',
    'organization_id',
    'secondary_school_id',
    'professional_school_id',
    'cbt_center_id',
    'exam_session_id',
    'candidate_exam_attempt_id',
    'candidate_id',
    'center_id',
    'reviewed_by',
    'event_type',
    'severity',
    'source',
    'payload',
    'occurred_at',
    'reviewed_at',
    'resolution_status',
    'resolution_notes',
])]
class ProctoringEvent extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CandidateExamAttempt::class, 'candidate_exam_attempt_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
