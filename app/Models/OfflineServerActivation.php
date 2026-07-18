<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['offline_activation_code_id', 'organization_id', 'cbt_center_id', 'device_id', 'admin_email', 'center_name', 'license_key', 'status', 'activated_at', 'expires_at', 'request_payload'])]
class OfflineServerActivation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'request_payload' => 'array',
        ];
    }

    public function activationCode(): BelongsTo
    {
        return $this->belongsTo(OfflineActivationCode::class, 'offline_activation_code_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function cbtCenter(): BelongsTo
    {
        return $this->belongsTo(CbtCenter::class);
    }
}
