<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id', 'drill_id', 'activation_id', 'organization_id', 'cbt_center_id', 'report_version',
    'drill_name', 'readiness_status', 'content_pack_version', 'expected_clients', 'connected_clients',
    'completed_clients', 'failed_clients', 'total_questions', 'total_answers', 'total_submissions',
    'event_count', 'payload_hash', 'payload', 'started_at', 'closed_at', 'uploaded_at',
])]
class OfflineReadinessReport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(OfflineServerActivation::class, 'activation_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function cbtCenter(): BelongsTo
    {
        return $this->belongsTo(CbtCenter::class);
    }
}
