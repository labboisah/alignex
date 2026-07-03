<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['professional_school_id', 'name', 'code', 'description', 'duration', 'status'])]
class Programme extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public function professionalSchool(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSchool::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ProfessionalModule::class);
    }

    public function trainingBatches(): HasMany
    {
        return $this->hasMany(TrainingBatch::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
