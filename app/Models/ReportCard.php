<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['secondary_school_id', 'academic_session_id', 'academic_term_id', 'student_id', 'candidate_id', 'exam_id', 'total_score', 'average_score', 'grade', 'status', 'metadata', 'published_at'])]
class ReportCard extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'average_score' => 'decimal:2',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function secondarySchool(): BelongsTo
    {
        return $this->belongsTo(SecondarySchool::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
