<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'organization_type', 'description', 'logo', 'website', 'contact_person', 'email', 'phone', 'address', 'status'])]
class Organization extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_NGO = 'ngo';
    public const TYPE_COMPANY = 'company';
    public const TYPE_GOVERNMENT_AGENCY = 'government_agency';
    public const TYPE_RECRUITMENT_AGENCY = 'recruitment_agency';
    public const TYPE_CERTIFICATION_BODY = 'certification_body';
    public const TYPE_EDUCATION_FOUNDATION = 'education_foundation';
    public const TYPE_ASSOCIATION = 'association';
    public const TYPE_COMMUNITY_GROUP = 'community_group';
    public const TYPE_SCHOOL_GROUP_OWNER = 'school_group_owner';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_NGO,
        self::TYPE_COMPANY,
        self::TYPE_GOVERNMENT_AGENCY,
        self::TYPE_RECRUITMENT_AGENCY,
        self::TYPE_CERTIFICATION_BODY,
        self::TYPE_EDUCATION_FOUNDATION,
        self::TYPE_ASSOCIATION,
        self::TYPE_COMMUNITY_GROUP,
        self::TYPE_SCHOOL_GROUP_OWNER,
        self::TYPE_OTHER,
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function secondarySchools(): HasMany
    {
        return $this->hasMany(SecondarySchool::class);
    }

    public function professionalSchools(): HasMany
    {
        return $this->hasMany(ProfessionalSchool::class);
    }

    public function cbtCenters(): HasMany
    {
        return $this->hasMany(CbtCenter::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function questionBanks(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
