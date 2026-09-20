<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code', 'version', 'capacity_profile', 'candidate_count', 'backup_percent', 'autoboot_target_clients',
    'question_count', 'subject_count', 'status', 'checksum_sha256', 'payload', 'candidate_ids',
    'subject_ids', 'question_bank_ids', 'paper_rows', 'created_by',
])]
class OfflineReadinessPackage extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_RETIRED,
    ];

    public const CAPACITIES = [15, 25, 50, 100, 150, 200, 250];

    protected function casts(): array
    {
        return [
            'capacity_profile' => 'integer',
            'candidate_count' => 'integer',
            'backup_percent' => 'decimal:2',
            'autoboot_target_clients' => 'integer',
            'question_count' => 'integer',
            'subject_count' => 'integer',
            'payload' => 'array',
            'candidate_ids' => 'array',
            'subject_ids' => 'array',
            'question_bank_ids' => 'array',
            'paper_rows' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calculateTarget(): int
    {
        return (int) ceil($this->capacity_profile * (1 + ((float) $this->backup_percent / 100)));
    }
}
