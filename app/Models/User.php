<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    public function isCandidate(): bool
    {
        return $this->role === 'candidate';
    }

    public function isPortalUser(): bool
    {
        return ! $this->isCandidate();
    }

    public function portalRedirectRoute(): string
    {
        return match ($this->role) {
            'super_admin',
            'organization_admin',
            'exam_manager',
            'question_author',
            'reviewer',
            'supervisor',
            'support',
            'admin' => 'dashboard',
            default => 'dashboard',
        };
    }
}
