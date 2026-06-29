<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_class_id', 'name', 'code', 'status'])]
class StudentGroup extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public function schoolClass(): BelongsTo { return $this->belongsTo(SchoolClass::class); }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'student_group_members')->withTimestamps();
    }
}
