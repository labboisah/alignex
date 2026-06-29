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
    'actor_user_id',
    'actor_type',
    'event_type',
    'description',
    'metadata',
    'ip_address',
    'user_agent',
    'occurred_at',
])]
class ExamAuditLog extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
