<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['created_by_user_id', 'organization_id', 'cbt_center_id', 'label', 'code_hash', 'code_encrypted', 'status', 'max_activations', 'activation_count', 'expires_at', 'license_expires_at', 'last_activated_at', 'last_device_id', 'last_admin_email', 'metadata'])]
class OfflineActivationCode extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_REVOKED = 'revoked';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'license_expires_at' => 'datetime',
            'last_activated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cbtCenter(): BelongsTo
    {
        return $this->belongsTo(CbtCenter::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(OfflineServerActivation::class);
    }
}
