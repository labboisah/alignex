<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'candidate_id',
    'exam_id',
    'subject_id',
    'topic_id',
    'difficulty',
    'total_questions',
    'correct_answers',
    'score_percentage',
    'mastery_level',
])]
class CandidatePerformanceProfile extends Model
{
    use HasFactory, HasUlids;

    public const MASTERY_WEAK = 'weak';
    public const MASTERY_AVERAGE = 'average';
    public const MASTERY_STRONG = 'strong';

    protected function casts(): array
    {
        return [
            'total_questions' => 'integer',
            'correct_answers' => 'integer',
            'score_percentage' => 'decimal:2',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
