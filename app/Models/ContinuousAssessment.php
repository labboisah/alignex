<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'exam_id',
    'candidate_id',
    'subject_id',
    'academic_session_id',
    'academic_term_id',
    'school_class_id',
    'student_group_id',
    'ca_score',
    'exam_score',
    'total_score',
    'grade',
    'teacher_comment',
])]
class ContinuousAssessment extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'ca_score' => 'decimal:2',
            'exam_score' => 'decimal:2',
            'total_score' => 'decimal:2',
        ];
    }

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function academicSession(): BelongsTo { return $this->belongsTo(AcademicSession::class); }
    public function academicTerm(): BelongsTo { return $this->belongsTo(AcademicTerm::class); }
    public function schoolClass(): BelongsTo { return $this->belongsTo(SchoolClass::class); }
    public function studentGroup(): BelongsTo { return $this->belongsTo(StudentGroup::class); }
}
