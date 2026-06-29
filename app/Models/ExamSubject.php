<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exam_id',
    'subject_id',
    'display_order',
    'duration_minutes',
    'total_marks',
    'question_count',
    'marks_per_question',
    'selection_rules',
    'difficulty_distribution',
    'instructions',
])]
class ExamSubject extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'marks_per_question' => 'decimal:2',
            'selection_rules' => 'array',
            'difficulty_distribution' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
