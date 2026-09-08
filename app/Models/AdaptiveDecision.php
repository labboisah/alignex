<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAdaptiveRecord;
use Illuminate\Database\Eloquent\Model;

class AdaptiveDecision extends Model
{
    use ImmutableAdaptiveRecord;

    protected $guarded = [];

    protected $hidden = ['decision'];

    protected function casts(): array
    {
        return ['decision' => 'array'];
    }
}
