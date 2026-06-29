<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_id', 'label', 'option_text', 'display_order', 'is_correct', 'score_weight'])]
class QuestionOption extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'score_weight' => 'decimal:2',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
