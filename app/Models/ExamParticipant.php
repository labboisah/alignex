<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['exam_id', 'participant_type', 'participant_id', 'status'])]
class ExamParticipant extends Model
{
    use HasFactory;

    public const TYPE_CANDIDATE = 'candidate';
    public const TYPE_STUDENT = 'student';

    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_STARTED = 'started';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CANCELLED = 'cancelled';

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CandidateExamAttempt::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'participant_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'participant_id');
    }
}
