<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'name', 'channels', 'email_subject', 'email_body', 'sms_body', 'is_active'])]
class NotificationTemplate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
