<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAdaptiveRecord;
use Illuminate\Database\Eloquent\Model;

class AdaptiveLevelRun extends Model
{
    use ImmutableAdaptiveRecord;

    protected $guarded = [];

    protected $hidden = ['area_plan'];

    protected function casts(): array
    {
        return ['area_plan' => 'array', 'is_practice' => 'boolean'];
    }
}
