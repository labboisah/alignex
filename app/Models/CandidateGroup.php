<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CandidateGroup extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'institution_id',
        'faculty_id',
        'department_id',
        'cbt_center_id',
        'created_by',
        'name',
        'code',
        'description',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function cbtCenter(): BelongsTo
    {
        return $this->belongsTo(CbtCenter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'candidate_group_candidate')->withTimestamps();
    }
}
