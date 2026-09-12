<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'exam_participant_id', 'question_id', 'question_order', 'option_order', 'marks'])]
class CandidatePaper extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'option_order' => 'array',
            'marks' => 'decimal:2',
        ];
    }

    public function scoringMarks(): float
    {
        // Papers generated before mark snapshots retain their original scoring rule.
        return (float) ($this->marks ?? $this->question?->marks ?? 0);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CandidateExamAttempt::class, 'attempt_id');
    }

    public function examParticipant(): BelongsTo
    {
        return $this->belongsTo(ExamParticipant::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
