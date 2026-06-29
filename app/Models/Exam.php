<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'center_id',
    'school_id',
    'secondary_school_id',
    'professional_school_id',
    'cbt_center_id',
    'owner_type',
    'owner_id',
    'exam_owner_type',
    'exam_owner_id',
    'exam_type_id',
    'created_by',
    'title',
    'code',
    'description',
    'mode',
    'exam_mode',
    'exam_category',
    'delivery_mode',
    'duration_minutes',
    'total_marks',
    'pass_mark',
    'starts_at',
    'ends_at',
    'timezone',
    'status',
    'security_settings',
    'navigation_settings',
    'result_release_settings',
    'settings',
])]
class Exam extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const OWNER_ORGANIZATION = 'organization';
    public const OWNER_SECONDARY_SCHOOL = 'secondary_school';
    public const OWNER_PROFESSIONAL_SCHOOL = 'professional_school';
    public const OWNER_CBT_CENTER = 'cbt_center';

    public const CATEGORY_TERMINAL = 'terminal';
    public const CATEGORY_RECRUITMENT = 'recruitment';
    public const CATEGORY_ASSESSMENT = 'assessment';
    public const CATEGORY_CERTIFICATION = 'certification';
    public const CATEGORY_PROFESSIONAL = 'professional';
    public const CATEGORY_PRACTICE = 'practice';
    public const CATEGORY_GENERAL = 'general';

    public const MODE_TRADITIONAL = 'traditional';
    public const MODE_ADAPTIVE = 'adaptive';

    public const OWNER_TYPES = [
        self::OWNER_ORGANIZATION,
        self::OWNER_SECONDARY_SCHOOL,
        self::OWNER_PROFESSIONAL_SCHOOL,
        self::OWNER_CBT_CENTER,
    ];

    public const CATEGORIES = [
        self::CATEGORY_TERMINAL,
        self::CATEGORY_RECRUITMENT,
        self::CATEGORY_ASSESSMENT,
        self::CATEGORY_CERTIFICATION,
        self::CATEGORY_PROFESSIONAL,
        self::CATEGORY_PRACTICE,
        self::CATEGORY_GENERAL,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'total_marks' => 'decimal:2',
            'pass_mark' => 'decimal:2',
            'security_settings' => 'array',
            'navigation_settings' => 'array',
            'result_release_settings' => 'array',
            'settings' => 'array',
        ];
    }

    public function effectiveOwnerType(): ?string
    {
        return $this->exam_owner_type
            ?? $this->owner_type
            ?? match (true) {
                $this->organization_id !== null => self::OWNER_ORGANIZATION,
                $this->secondary_school_id !== null || $this->school_id !== null => self::OWNER_SECONDARY_SCHOOL,
                $this->professional_school_id !== null => self::OWNER_PROFESSIONAL_SCHOOL,
                $this->cbt_center_id !== null || $this->center_id !== null => self::OWNER_CBT_CENTER,
                default => null,
            };
    }

    public function effectiveMode(): ?string
    {
        return $this->exam_mode ?? $this->mode;
    }

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

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CandidateExamAttempt::class);
    }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'exam_candidates')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ExamAuditLog::class);
    }

    public function proctoringEvents(): HasMany
    {
        return $this->hasMany(ProctoringEvent::class);
    }

    public function performanceProfiles(): HasMany
    {
        return $this->hasMany(CandidatePerformanceProfile::class);
    }

    public function certificateTemplates(): HasMany
    {
        return $this->hasMany(CertificateTemplate::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
