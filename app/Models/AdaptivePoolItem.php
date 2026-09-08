<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAdaptiveRecord;
use Illuminate\Database\Eloquent\Model;

class AdaptivePoolItem extends Model
{
    use ImmutableAdaptiveRecord;

    protected $guarded = [];

    protected $hidden = ['content'];

    protected function casts(): array
    {
        return ['content' => 'encrypted:array'];
    }
}
