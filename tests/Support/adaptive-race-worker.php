<?php

use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Services\AdaptiveLifecycleService;
use App\Services\AdaptiveRolloutService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'alignex_adaptive_phase3_test') {
    throw new RuntimeException('Concurrency worker requires the isolated Phase 3 test database.');
}
$data = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
Carbon::setTestNow($data['now']);
$app->instance(AdaptiveRolloutService::class, new class extends AdaptiveRolloutService
{
    public function ensureDeliveryAllowed(Exam $exam): void {}
});
file_put_contents($argv[1].'.ready', 'ready');
try {
    $attempt = CandidateExamAttempt::findOrFail($data['attempt_id']);
    $service = app(AdaptiveLifecycleService::class);
    if ($data['operation'] === 'disqualify') {
        $service->disqualify($attempt, 'Concurrency test disqualification');
        $result = $service->execute($attempt, 'expire');
    } else {
        $result = $service->execute($attempt, $data['operation'], $data['data'] ?? []);
    }
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'errors' => $e->errors()], JSON_THROW_ON_ERROR);
}
