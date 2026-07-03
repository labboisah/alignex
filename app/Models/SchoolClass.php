<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'secondary_school_id', 'name', 'code', 'level', 'level_order', 'status'])]
class SchoolClass extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function secondarySchool(): BelongsTo { return $this->belongsTo(SecondarySchool::class); }
    public function groups(): HasMany { return $this->hasMany(StudentGroup::class); }
    public function classArms(): HasMany { return $this->hasMany(ClassArm::class); }
    public function students(): HasMany { return $this->hasMany(Student::class); }
}
