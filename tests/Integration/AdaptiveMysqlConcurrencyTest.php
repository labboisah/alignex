<?php

namespace Tests\Integration;

use App\Models\AdaptiveDecision;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveMarkEntry;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveResponse;
use App\Models\CandidateExamAttempt;
use App\Services\AdaptiveLifecycleService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\AdaptiveFixtures;
use Tests\TestCase;

class AdaptiveMysqlConcurrencyTest extends TestCase
{
    use AdaptiveFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'alignex_adaptive_phase3_test') {
            $this->markTestSkipped('Requires the dedicated alignex_adaptive_phase3_test MySQL database.');
        }
        // Never migrate or reset the development database.
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    public function test_concurrent_commits_and_next_level_starts_are_exactly_once(): void
    {
        [$attempt] = $this->fixture();
        $state = $this->start($attempt);
        $data = $this->answerData($state, true, 'shared-commit');
        $results = $this->race($attempt, [
            ['operation' => 'commit', 'data' => $data],
            ['operation' => 'commit', 'data' => $data],
        ]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['result']['current_item'], $results[1]['result']['current_item']);
        $this->assertSame(1, AdaptiveResponse::whereNotNull('committed_at')->count());
        $this->assertSame(2, AdaptiveDecision::count());
        $this->assertSame(200, (int) AdaptiveProgression::firstOrFail()->earned_units);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $results = $this->race($attempt, [
            ['operation' => 'next-level', 'data' => ['idempotency_key' => 'shared-start']],
            ['operation' => 'next-level', 'data' => ['idempotency_key' => 'shared-start']],
        ]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['result']['attempt']['id'], $results[1]['result']['attempt']['id']);
        $this->assertSame(2, AdaptiveLevel::count());
        $this->assertSame(1, AdaptiveMarkEntry::where('kind', 'penalty')->count());
        $this->assertSame(40, (int) AdaptiveProgression::firstOrFail()->penalty_units);
    }

    public function test_submission_and_disqualification_win_over_waiting_late_writes(): void
    {
        foreach (['submit', 'disqualify'] as $closing) {
            [$attempt] = $this->fixture();
            $state = $this->start($attempt);
            $data = $this->answerData($state, true, 'late');
            $results = $this->race($attempt, [['operation' => 'commit', 'data' => $data]], $closing);
            $this->assertTrue($results[0]['ok']);
            $level = AdaptiveLevel::where('attempt_id', $attempt->id)->firstOrFail();
            $this->assertSame(0, AdaptiveResponse::where('level_id', $level->id)->whereNotNull('committed_at')->count());
            $this->assertSame($closing === 'submit' ? 'submitted' : 'disqualified', $attempt->fresh()->status);
        }
    }

    public function test_deadline_is_rechecked_after_acquiring_mysql_lock(): void
    {
        [$attempt] = $this->fixture();
        $state = $this->start($attempt);
        $data = $this->answerData($state, true, 'late-time');
        $this->travel(31)->minutes();
        $results = $this->race($attempt, [['operation' => 'commit', 'data' => $data]]);
        $this->assertTrue($results[0]['ok']);
        $this->assertSame('auto_submitted', $attempt->fresh()->status);
        $this->assertSame(0, AdaptiveResponse::whereNotNull('committed_at')->count());
    }

    private function race(CandidateExamAttempt $attempt, array $operations, ?string $closeWhileLocked = null): array
    {
        $level = AdaptiveLevel::where('attempt_id', $attempt->id)->firstOrFail();
        $connection = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'alignex_adaptive_phase3_test', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'BROADCAST_CONNECTION' => 'null', 'BCRYPT_ROUNDS' => '4',
        ];
        $directory = storage_path('framework/testing/adaptive-races');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        DB::beginTransaction();
        AdaptiveProgression::whereKey($level->progression_id)->lockForUpdate()->firstOrFail();
        $workers = [];
        try {
            foreach ($operations as $operation) {
                $file = $directory.'/'.bin2hex(random_bytes(8)).'.json';
                file_put_contents($file, json_encode(['attempt_id' => $attempt->id, 'now' => now()->toISOString(), ...$operation], JSON_THROW_ON_ERROR));
                $process = new Process([PHP_BINARY, base_path('tests/Support/adaptive-race-worker.php'), $file], base_path(), $env);
                $process->setTimeout(30);
                $process->start();
                $workers[] = [$process, $file];
            }
            $until = microtime(true) + 15;
            foreach ($workers as [$process, $file]) {
                while (! file_exists($file.'.ready') && microtime(true) < $until && $process->isRunning()) {
                    usleep(10000);
                }
                $this->assertFileExists($file.'.ready', $process->getErrorOutput());
            }
            if ($closeWhileLocked === 'disqualify') {
                app(AdaptiveLifecycleService::class)->disqualify($attempt, 'Locked supervisor decision');
            } elseif ($closeWhileLocked) {
                app(AdaptiveLifecycleService::class)->execute($attempt, $closeWhileLocked);
            }
            DB::commit();
            $results = [];
            foreach ($workers as [$process, $file]) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($workers as [$process, $file]) {
                if ($process->isRunning()) {
                    $process->stop();
                }
                @unlink($file);
                @unlink($file.'.ready');
            }
        }
    }
}
