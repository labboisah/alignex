<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\ExamStatusService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exams:complete-overdue', function (ExamStatusService $service) {
    $count = $service->syncOverdue();
    $this->info("Completed {$count} overdue exam(s).");
})->purpose('Mark active or scheduled exams as completed after their end time');
