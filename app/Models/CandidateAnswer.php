<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'candidate_exam_attempt_id',
    'question_id',
    'subject_id',
    'scored_by',
    'answer_payload',
    'selected_option_ids',
    'answer_text',
    'is_flagged',
    'time_spent_seconds',
    'ip_address',
    'device_fingerprint',
    'saved_at',
    'submitted_at',
    'score_awarded',
    'scored_at',
])]
class CandidateAnswer extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'answer_payload' => 'array',
            'selected_option_ids' => 'array',
            'is_flagged' => 'boolean',
            'time_spent_seconds' => 'integer',
            'saved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score_awarded' => 'decimal:2',
            'scored_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CandidateExamAttempt::class, 'candidate_exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
