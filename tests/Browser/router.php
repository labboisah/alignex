<?php

use App\Models\AdaptiveLevel;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveResponse;
use App\Models\Exam;
use App\Models\User;
use App\Services\AdaptiveLifecycleService;
use App\Services\AdaptiveRolloutService;
use App\Services\ExamMonitorService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

// This router is never registered by the application. Only the isolated browser test server loads it.
$root = dirname(__DIR__, 2);
$expected = str_replace('\\', '/', $root.'/storage/framework/testing/adaptive-browser.sqlite');
if (getenv('ADAPTIVE_BROWSER_TEST') !== '1' || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite' || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== $expected) {
    http_response_code(503);
    exit('Browser harness refuses a non-test database.');
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$static = realpath($root.'/public'.$path);
if ($static && is_file($static) && str_starts_with(str_replace('\\', '/', $static), str_replace('\\', '/', $root.'/public/'))) {
    return false;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || config('database.default') !== 'sqlite' || str_replace('\\', '/', config('database.connections.sqlite.database')) !== $expected) {
    http_response_code(503);
    exit('Clear cached configuration before using the browser harness.');
}
Vite::usePrefetchStrategy(null)->useBuildDirectory('phase4-test')->useHotFile($root.'/storage/framework/testing/no-browser-hot');
$app->instance(AdaptiveRolloutService::class, new class extends AdaptiveRolloutService
{
    public function ensureDeliveryAllowed(Exam $exam): void {}
});
require __DIR__.'/fixtures.php';
$app['router']->post('/__browser/fixture', fn (Request $request) => response()->json(browserFixture($request->all())));
$app['router']->post('/__browser/action', function (Request $request) {
    $exam = Exam::findOrFail($request->input('exam_id'));
    $attempt = $exam->attempts()->orderByDesc('attempt_number')->firstOrFail();
    if ($request->input('action') === 'expire') {
        AdaptiveLevel::where('attempt_id', $attempt->id)->update(['due_at' => now()->subSecond()]);
    } elseif ($request->input('action') === 'end') {
        app(AdaptiveLifecycleService::class)->endBySupervisor($attempt, User::findOrFail($request->input('actor_id')));
    } else {
        abort(422);
    }

    return response()->json(['ok' => true]);
});
$app['router']->get('/__browser/state/{exam}', function (string $exam) {
    $exam = Exam::findOrFail($exam);
    $progression = AdaptiveProgression::where('exam_id', $exam->id)->first();

    return response()->json(['levels' => $exam->attempts()->count(),
        'committed' => $progression ? AdaptiveResponse::whereIn('level_id', AdaptiveLevel::where('progression_id', $progression->id)->select('id'))->whereNotNull('committed_at')->count() : 0,
        'penalties' => $progression?->penalty_units, 'earned' => $progression?->earned_units,
        'rows' => app(ExamMonitorService::class)->rows($exam)]);
});
$app->handleRequest(Request::capture());
