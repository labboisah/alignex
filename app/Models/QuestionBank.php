<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['owner_type', 'owner_id', 'organization_id', 'school_id', 'center_id', 'secondary_school_id', 'professional_school_id', 'cbt_center_id', 'programme_id', 'course_id', 'module_id', 'subject_id', 'created_by', 'name', 'code', 'description', 'status'])]
class QuestionBank extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function secondarySchool(): BelongsTo
    {
        return $this->belongsTo(SecondarySchool::class);
    }

    public function professionalSchool(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSchool::class);
    }

    public function cbtCenter(): BelongsTo
    {
        return $this->belongsTo(CbtCenter::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ProfessionalModule::class, 'module_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
