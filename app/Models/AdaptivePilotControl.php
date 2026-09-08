<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdaptivePilotControl extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['online_enabled' => 'boolean', 'offline_enabled' => 'boolean'];
    }
}
