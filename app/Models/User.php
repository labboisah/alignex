<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'organization_id', 'center_id', 'school_id', 'secondary_school_id', 'professional_school_id', 'cbt_center_id', 'active_context_type', 'active_context_id', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ORGANIZATION_ADMIN = 'organization_admin';
    public const ROLE_CENTER_ADMIN = 'center_admin';
    public const ROLE_SCHOOL_ADMIN = 'school_admin';
    public const ROLE_SECONDARY_SCHOOL_ADMIN = 'secondary_school_admin';
    public const ROLE_PROFESSIONAL_SCHOOL_ADMIN = 'professional_school_admin';
    public const ROLE_CBT_CENTER_ADMIN = 'cbt_center_admin';
    public const ROLE_EXAMINER = 'examiner';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_CANDIDATE = 'candidate';
    public const ROLE_STUDENT = 'student';

    public const PORTAL_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ORGANIZATION_ADMIN,
        self::ROLE_CENTER_ADMIN,
        self::ROLE_SCHOOL_ADMIN,
        self::ROLE_SECONDARY_SCHOOL_ADMIN,
        self::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
        self::ROLE_CBT_CENTER_ADMIN,
        self::ROLE_EXAMINER,
        self::ROLE_TEACHER,
        self::ROLE_SUPERVISOR,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
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

    public function systemRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    public function createdExams(): HasMany
    {
        return $this->hasMany(Exam::class, 'created_by');
    }

    public function createdQuestionBanks(): HasMany
    {
        return $this->hasMany(QuestionBank::class, 'created_by');
    }

    public function createdQuestions(): HasMany
    {
        return $this->hasMany(Question::class, 'created_by');
    }

    public function assignedSubjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'user_id', 'subject_id')
            ->withPivot('school_id', 'secondary_school_id', 'school_class_id')
            ->withTimestamps();
    }

    public function reviewedQuestions(): HasMany
    {
        return $this->hasMany(Question::class, 'reviewed_by');
    }

    public function scoredCandidateAnswers(): HasMany
    {
        return $this->hasMany(CandidateAnswer::class, 'scored_by');
    }

    public function examAuditLogs(): HasMany
    {
        return $this->hasMany(ExamAuditLog::class, 'actor_user_id');
    }

    public function reviewedProctoringEvents(): HasMany
    {
        return $this->hasMany(ProctoringEvent::class, 'reviewed_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isOrganizationAdmin(): bool
    {
        return $this->role === self::ROLE_ORGANIZATION_ADMIN;
    }

    public function isExaminer(): bool
    {
        return $this->role === self::ROLE_EXAMINER;
    }

    public function isCenterAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_CENTER_ADMIN, self::ROLE_CBT_CENTER_ADMIN], true);
    }

    public function isSchoolAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SCHOOL_ADMIN, self::ROLE_SECONDARY_SCHOOL_ADMIN, self::ROLE_PROFESSIONAL_SCHOOL_ADMIN], true);
    }

    public function isSecondarySchoolAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SECONDARY_SCHOOL_ADMIN, self::ROLE_SCHOOL_ADMIN], true);
    }

    public function isProfessionalSchoolAdmin(): bool
    {
        return $this->role === self::ROLE_PROFESSIONAL_SCHOOL_ADMIN;
    }

    public function isCbtCenterAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_CBT_CENTER_ADMIN, self::ROLE_CENTER_ADMIN], true);
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isCandidate(): bool
    {
        return $this->role === self::ROLE_CANDIDATE;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isPortalUser(): bool
    {
        return in_array($this->role, self::PORTAL_ROLES, true);
    }

    public function hasPermission(string $permission): bool
    {
        if (! array_key_exists($permission, AccessControl::permissions())) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
            $role = $this->systemRole()
                ->with('permissions:id,name')
                ->first();

            if ($role) {
                if ($role->permissions->contains('name', $permission)) {
                    return true;
                }

                if ($role->permissions->isNotEmpty()) {
                    return false;
                }
            }
        }

        return in_array($permission, AccessControl::defaults()[$this->role] ?? [], true);
    }

    public function belongsToOrganization(int|string|null $organizationId): bool
    {
        return $organizationId !== null && (string) $this->organization_id === (string) $organizationId;
    }

    public function belongsToCenter(int|string|null $centerId): bool
    {
        return $centerId !== null && (string) $this->center_id === (string) $centerId;
    }

    public function belongsToSchool(int|string|null $schoolId): bool
    {
        return $schoolId !== null && (string) $this->school_id === (string) $schoolId;
    }

    public function canAccessOrganization(int|string|null $organizationId): bool
    {
        return $this->isSuperAdmin()
            || ($organizationId !== null && (string) $this->organization_id === (string) $organizationId);
    }

    public function canAccessSecondarySchool(int|string|null $secondarySchoolId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($secondarySchoolId !== null && (string) ($this->secondary_school_id ?? $this->school_id) === (string) $secondarySchoolId) {
            return true;
        }

        return $this->isSuperAdmin()
            || ($secondarySchoolId !== null && $this->organization_id !== null && SecondarySchool::query()
                ->whereKey($secondarySchoolId)
                ->where('organization_id', $this->organization_id)
                ->exists());
    }

    public function canAccessProfessionalSchool(int|string|null $professionalSchoolId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($professionalSchoolId !== null && (string) $this->professional_school_id === (string) $professionalSchoolId) {
            return true;
        }

        return $this->isSuperAdmin()
            || ($professionalSchoolId !== null && $this->organization_id !== null && ProfessionalSchool::query()
                ->whereKey($professionalSchoolId)
                ->where('organization_id', $this->organization_id)
                ->exists());
    }

    public function canAccessCbtCenter(int|string|null $cbtCenterId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($cbtCenterId !== null && (string) ($this->cbt_center_id ?? $this->center_id) === (string) $cbtCenterId) {
            return true;
        }

        return $this->isSuperAdmin()
            || ($cbtCenterId !== null && $this->organization_id !== null && CbtCenter::query()
                ->whereKey($cbtCenterId)
                ->where('organization_id', $this->organization_id)
                ->exists());
    }

    public function currentContext(): ?array
    {
        if ($this->active_context_type && $this->active_context_id) {
            return ['type' => $this->active_context_type, 'id' => $this->active_context_id];
        }

        return match (true) {
            $this->organization_id !== null => ['type' => 'organization', 'id' => $this->organization_id],
            $this->secondary_school_id !== null => ['type' => 'secondary_school', 'id' => $this->secondary_school_id],
            $this->professional_school_id !== null => ['type' => 'professional_school', 'id' => $this->professional_school_id],
            $this->cbt_center_id !== null => ['type' => 'cbt_center', 'id' => $this->cbt_center_id],
            $this->school_id !== null => ['type' => 'secondary_school', 'id' => $this->school_id],
            $this->center_id !== null => ['type' => 'cbt_center', 'id' => $this->center_id],
            default => null,
        };
    }

    public function portalRedirectRoute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ORGANIZATION_ADMIN,
            self::ROLE_CENTER_ADMIN,
            self::ROLE_SCHOOL_ADMIN,
            self::ROLE_SECONDARY_SCHOOL_ADMIN,
            self::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
            self::ROLE_CBT_CENTER_ADMIN,
            self::ROLE_EXAMINER,
            self::ROLE_TEACHER,
            self::ROLE_SUPERVISOR => 'dashboard',
            default => 'dashboard',
        };
    }
}
