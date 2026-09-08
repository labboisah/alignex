<?php

namespace App\Models;

use App\Models\Concerns\FreezesAdaptiveIdentity;
use Illuminate\Database\Eloquent\Model;

class AdaptiveProgression extends Model
{
    use FreezesAdaptiveIdentity;

    protected $guarded = [];

    protected array $frozenFields = ['snapshot_id', 'exam_id', 'candidate_id', 'owner_key', 'original_units', 'closes_at'];

    protected function casts(): array
    {
        return ['closes_at' => 'datetime'];
    }
}
