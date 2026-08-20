<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_id', 'user_id', 'assigned_by', 'role', 'permissions', 'assigned_at', 'revoked_at'])]
class ExamSupervisor extends Model
{
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_CO_MANAGER = 'co_manager';

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'assigned_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
