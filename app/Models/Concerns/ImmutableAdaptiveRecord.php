<?php

namespace App\Models\Concerns;

trait ImmutableAdaptiveRecord
{
    protected static function bootImmutableAdaptiveRecord(): void
    {
        static::updating(fn () => throw new \LogicException('Adaptive historical records cannot be updated.'));
        static::deleting(fn () => throw new \LogicException('Adaptive historical records cannot be deleted.'));
    }
}
