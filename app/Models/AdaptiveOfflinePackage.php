<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdaptiveOfflinePackage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array'];
    }
}
