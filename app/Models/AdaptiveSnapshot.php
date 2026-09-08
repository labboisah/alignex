<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAdaptiveRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdaptiveSnapshot extends Model
{
    use ImmutableAdaptiveRecord;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array', 'blueprint' => 'array', 'readiness' => 'array', 'ready' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdaptivePoolItem::class, 'snapshot_id');
    }
}
