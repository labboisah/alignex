<?php

namespace App\Models;

use App\Models\Concerns\FreezesAdaptiveIdentity;
use Illuminate\Database\Eloquent\Model;

class AdaptiveLevel extends Model
{
    use FreezesAdaptiveIdentity;

    protected $guarded = [];

    protected array $frozenFields = ['progression_id', 'attempt_id', 'number', 'incoming_units', 'penalty_basis_points', 'penalty_units', 'available_units', 'weakness_snapshot'];

    protected function casts(): array
    {
        return ['weakness_snapshot' => 'array', 'started_at' => 'datetime', 'due_at' => 'datetime', 'submitted_at' => 'datetime'];
    }
}
