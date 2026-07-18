<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use App\Models\OfflineActivationCode;
use App\Services\ExamStatusService;
use App\Services\Notifications\NotificationDispatcher;
use Database\Seeders\NotificationTemplateSeeder;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exams:complete-overdue', function (ExamStatusService $service) {
    $count = $service->syncOverdue();
    $this->info("Completed {$count} overdue exam(s).");
})->purpose('Mark active or scheduled exams as completed after their end time');

Artisan::command('notifications:send-due {--limit=100}', function (NotificationDispatcher $dispatcher) {
    $sent = $dispatcher->sendDueScheduled((int) $this->option('limit'));
    $this->info('Queued '.$sent->count().' due notification(s).');
})->purpose('Send scheduled notification deliveries that are due');

Artisan::command('notifications:sync-templates', function () {
    app(NotificationTemplateSeeder::class)->run();
    $this->info('Notification templates synced from config.');
})->purpose('Sync notification template defaults into the database');

Schedule::command('notifications:send-due')->everyMinute()->withoutOverlapping();
Schedule::command('queue:work --queue=notifications,default --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Artisan::command('offline:activation-code {--organization_id=} {--cbt_center_id=} {--label=} {--max=1} {--days=365}', function () {
    $plainCode = 'AX-OFFLINE-'.Str::upper(Str::random(6)).'-'.Str::upper(Str::random(6));
    $days = max((int) $this->option('days'), 1);

    OfflineActivationCode::query()->create([
        'organization_id' => $this->option('organization_id') ?: null,
        'cbt_center_id' => $this->option('cbt_center_id') ?: null,
        'label' => $this->option('label') ?: 'Offline server activation',
        'code_hash' => Hash::make($plainCode),
        'code_encrypted' => Crypt::encryptString($plainCode),
        'status' => OfflineActivationCode::STATUS_ACTIVE,
        'max_activations' => max((int) $this->option('max'), 1),
        'activation_count' => 0,
        'license_expires_at' => now()->addDays($days),
    ]);

    $this->info('Offline activation code created.');
    $this->line("Code: {$plainCode}");
    $this->line("License days: {$days}");
})->purpose('Create an offline server activation code for an organization or CBT center');
