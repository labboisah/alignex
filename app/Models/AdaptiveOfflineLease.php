<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdaptiveOfflineLease extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['transcript'];

    protected function casts(): array
    {
        return ['transcript' => 'encrypted:array', 'result' => 'array'];
    }
}
