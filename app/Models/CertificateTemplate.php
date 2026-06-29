<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'exam_id',
    'secondary_school_id',
    'professional_school_id',
    'cbt_center_id',
    'name',
    'title',
    'institution_name',
    'logo_url',
    'primary_color',
    'accent_color',
    'background_color',
    'use_logo_watermark',
    'body',
    'signatory_name',
    'signatory_title',
    'is_active',
])]
class CertificateTemplate extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'use_logo_watermark' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
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

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
