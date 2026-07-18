<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('notifications.types', []) as $type => $template) {
            NotificationTemplate::query()->updateOrCreate(
                ['type' => $type],
                [
                    'name' => $template['name'],
                    'channels' => $template['channels'],
                    'email_subject' => $template['email_subject'],
                    'email_body' => $template['email_body'],
                    'sms_body' => $template['sms_body'],
                    'is_active' => true,
                ],
            );
        }
    }
}
