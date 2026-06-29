<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['academic_session_id', 'secondary_school_id', 'name', 'code', 'starts_on', 'ends_on', 'status'])]
class AcademicTerm extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function session(): BelongsTo { return $this->belongsTo(AcademicSession::class, 'academic_session_id'); }
    public function secondarySchool(): BelongsTo { return $this->belongsTo(SecondarySchool::class); }
}
