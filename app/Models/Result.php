<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['owner_type', 'owner_id', 'organization_id', 'secondary_school_id', 'professional_school_id', 'cbt_center_id', 'exam_id', 'candidate_id', 'status'])]
class Result extends Model
{
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function secondarySchool(): BelongsTo { return $this->belongsTo(SecondarySchool::class); }
    public function professionalSchool(): BelongsTo { return $this->belongsTo(ProfessionalSchool::class); }
    public function cbtCenter(): BelongsTo { return $this->belongsTo(CbtCenter::class); }
    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
}
