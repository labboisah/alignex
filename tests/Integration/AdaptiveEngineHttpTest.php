<?php

namespace Tests\Integration;

use App\Services\AdaptiveEngineClient;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdaptiveEngineHttpTest extends TestCase
{
    public function test_real_fastapi_contract_and_deterministic_replay(): void
    {
        $directory = base_path('services/adaptive-engine');
        $python = $directory.(PHP_OS_FAMILY === 'Windows' ? '/.venv/Scripts/python.exe' : '/.venv/bin/python');
        if (! is_file($python)) {
            $this->markTestSkipped('Install the isolated adaptive engine requirements first.');
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertNotFalse($socket);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $secret = str_repeat('integration-test-', 3);
        $process = new Process([$python, '-m', 'uvicorn', 'app:app', '--host', '127.0.0.1', '--port', (string) $port, '--no-access-log'], $directory, ['ADAPTIVE_ENGINE_SECRET' => $secret]);
        $process->setTimeout(30);
        try {
            $process->start();
            $connected = false;
            for ($i = 0; $i < 100; $i++) {
                $connection = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 0.05);
                if ($connection) {
                    fclose($connection);
                    $connected = true;
                    break;
                }
                usleep(50000);
            }
            $this->assertTrue($connected, 'The isolated FastAPI server did not start.');
            config(['adaptive.engine.shadow_enabled' => true, 'adaptive.engine.url' => 'http://127.0.0.1:'.$port, 'adaptive.engine.secret' => $secret]);
            $payload = ['protocol' => 'alignex-shadow-v1', 'request_id' => 'php-python-integration', 'state_version' => 1,
                'calibration_fingerprint' => str_repeat('a', 64),
                'items' => [
                    ['id' => '1', 'a' => 1.0, 'b' => 0.0, 'area' => 'row:1', 'topic' => null, 'eligible' => false],
                    ['id' => '2', 'a' => 1.0, 'b' => 0.0, 'area' => 'row:1', 'topic' => null, 'eligible' => true],
                ],
                'responses' => [['id' => '1', 'correct' => true]], 'areas' => ['row:1'], 'required_topics' => [],
                'policy' => ['min_questions' => 1, 'max_questions' => 3, 'min_per_area' => 1, 'target_sd' => 0.5, 'cutpoint' => null]];
            $client = app(AdaptiveEngineClient::class);
            $result = $client->evaluate($payload);
            $this->assertSame('2', $result['selected_item_id']);
            $this->assertGreaterThan(0, $result['theta']);
            $this->assertSame($result, $client->evaluate($payload));
            $this->assertSame(401, Http::post('http://127.0.0.1:'.$port.'/v1/evaluate', $payload)->status());
        } finally {
            $process->stop();
        }
    }
}
