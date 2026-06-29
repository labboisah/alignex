<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'question_id', 'question_order', 'option_order'])]
class CandidatePaper extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'option_order' => 'array',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CandidateExamAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
