<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entity_type',
    'entity_id',
    'admin_name',
    'admin_email',
    'password',
    'entity_name',
    'entity_code',
    'location',
    'capacity',
    'contact_person',
    'phone',
    'entity_email',
    'address',
    'legal_registration_number',
    'website',
    'years_in_operation',
    'operating_scope',
    'accreditation_body',
    'accreditation_number',
    'facility_summary',
    'exam_experience',
    'expected_candidates',
    'status',
    'review_notes',
    'reviewed_by',
    'reviewed_at',
])]
class AdminRegistrationRequest extends Model
{
    use HasFactory;

    public const TYPE_ORGANIZATION = 'organization';
    public const TYPE_SCHOOL = 'school';
    public const TYPE_SECONDARY_SCHOOL = 'secondary_school';
    public const TYPE_PROFESSIONAL_SCHOOL = 'professional_school';
    public const TYPE_CENTER = 'center';
    public const TYPE_CBT_CENTER = 'cbt_center';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DEACTIVATED = 'deactivated';

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'years_in_operation' => 'integer',
            'expected_candidates' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
