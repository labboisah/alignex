<?php

namespace App\Models;

use App\Models\Concerns\FreezesAdaptiveIdentity;
use Illuminate\Database\Eloquent\Model;

class AdaptiveAreaBalance extends Model
{
    use FreezesAdaptiveIdentity;

    protected $guarded = [];

    protected array $frozenFields = ['progression_id', 'area_key', 'original_units'];

    protected function casts(): array
    {
        return [];
    }
}
