<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdaptiveResponse extends Model
{
    protected $guarded = [];

    protected $hidden = ['selected_options', 'is_correct', 'earned_units'];

    protected function casts(): array
    {
        return ['selected_options' => 'encrypted:array', 'is_correct' => 'boolean', 'committed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function ($row): void {
            if ($row->getOriginal('committed_at') || $row->isDirty(['decision_id', 'level_id'])) {
                throw new \LogicException('Committed responses and their identity are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Adaptive response history cannot be deleted.'));
    }
}
