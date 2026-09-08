<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAdaptiveRecord;
use Illuminate\Database\Eloquent\Model;

class AdaptiveEngineRun extends Model
{
    use ImmutableAdaptiveRecord;

    protected $guarded = [];

    protected $hidden = ['request_payload', 'result'];

    protected function casts(): array
    {
        return ['request_payload' => 'encrypted:array', 'result' => 'encrypted:array'];
    }
}
