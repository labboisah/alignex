<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'channel', 'status', 'provider', 'provider_message_id', 'recipient_name', 'recipient_email', 'recipient_phone', 'subject', 'body', 'payload', 'error_message', 'scheduled_at', 'sent_at'])]
class NotificationDelivery extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DRY_RUN = 'dry_run';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
