<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'question_bank_id',
    'subject_id',
    'topic_id',
    'created_by',
    'reviewed_by',
    'question_type',
    'stem',
    'image_path',
    'explanation',
    'difficulty',
    'marks',
    'negative_marks',
    'status',
    'scoring_metadata',
    'reviewed_at',
])]
class Question extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public const TYPE_SINGLE_CHOICE = 'single_choice';
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_ESSAY = 'essay';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
            'negative_marks' => 'decimal:2',
            'scoring_metadata' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function candidateAnswers(): HasMany
    {
        return $this->hasMany(CandidateAnswer::class);
    }
}
