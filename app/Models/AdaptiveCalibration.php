<?php

namespace App\Models;

use App\Models\Concerns\FreezesAdaptiveIdentity;
use Illuminate\Database\Eloquent\Model;

class AdaptiveCalibration extends Model
{
    use FreezesAdaptiveIdentity;

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected array $frozenFields = ['snapshot_id', 'owner_key', 'version', 'fingerprint', 'payload', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'reviewed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
