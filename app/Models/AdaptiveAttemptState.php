<?php

namespace App\Models;

use App\Models\Concerns\FreezesAdaptiveIdentity;
use Illuminate\Database\Eloquent\Model;

class AdaptiveAttemptState extends Model
{
    use FreezesAdaptiveIdentity;

    protected $guarded = [];

    protected array $frozenFields = ['attempt_id', 'snapshot_id', 'delivery_mode'];

    protected function casts(): array
    {
        return [];
    }
}
