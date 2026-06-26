<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'organization_id', 'center_id', 'school_id', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ORGANIZATION_ADMIN = 'organization_admin';
    public const ROLE_CENTER_ADMIN = 'center_admin';
    public const ROLE_SCHOOL_ADMIN = 'school_admin';
    public const ROLE_EXAMINER = 'examiner';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_CANDIDATE = 'candidate';

    public const PORTAL_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ORGANIZATION_ADMIN,
        self::ROLE_CENTER_ADMIN,
        self::ROLE_SCHOOL_ADMIN,
        self::ROLE_EXAMINER,
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

    public function systemRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'name');
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
        return $this->role === self::ROLE_CENTER_ADMIN;
    }

    public function isSchoolAdmin(): bool
    {
        return $this->role === self::ROLE_SCHOOL_ADMIN;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isCandidate(): bool
    {
        return $this->role === self::ROLE_CANDIDATE;
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

        if (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
            return $this->systemRole()
                ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
                ->exists();
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

    public function portalRedirectRoute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ORGANIZATION_ADMIN,
            self::ROLE_CENTER_ADMIN,
            self::ROLE_SCHOOL_ADMIN,
            self::ROLE_EXAMINER,
            self::ROLE_SUPERVISOR => 'dashboard',
            default => 'dashboard',
        };
    }
}
