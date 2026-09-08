<?php

namespace App\Models\Concerns;

trait FreezesAdaptiveIdentity
{
    protected static function bootFreezesAdaptiveIdentity(): void
    {
        static::updating(function ($record): void {
            foreach ($record->frozenFields as $field) {
                if ($record->isDirty($field)) {
                    throw new \LogicException('Frozen adaptive identity or budget cannot change: '.$field);
                }
            }
        });
        static::deleting(fn () => throw new \LogicException('Adaptive history must be retained.'));
    }
}
