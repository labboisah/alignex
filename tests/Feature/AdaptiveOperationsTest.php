<?php

namespace Tests\Feature;

use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveCalibration;
use App\Models\AdaptiveEngineRun;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveProgression;
use App\Models\User;
use App\Services\AdaptiveOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdaptiveOperationsTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    public function test_aggregates_frozen_owner_cohorts_and_shadow_failures_without_private_data(): void
    {
        [$attempt, $snapshot] = $this->fixture(false);
        [$other] = $this->fixture(false);
        $progression = AdaptiveProgression::where('exam_id', $attempt->exam_id)->firstOrFail();
        $progression->update(['status' => 'blocked', 'stop_reason' => 'pool_exhausted']);
        AdaptiveAttemptState::where('attempt_id', $attempt->id)->update(['stop_reason' => 'pool_exhausted']);
        $actor = User::factory()->create();
        $calibration = AdaptiveCalibration::create(['snapshot_id' => $snapshot->id, 'owner_key' => $snapshot->owner_key,
            'version' => 1, 'status' => 'draft', 'fingerprint' => str_repeat('a', 64), 'payload' => ['private' => 'secret-calibration'],
            'created_by' => $actor->id]);
        foreach ([100, 300] as $duration) {
            AdaptiveEngineRun::create(['calibration_id' => $calibration->id, 'progression_id' => $progression->id,
                'level_id' => AdaptiveLevel::where('attempt_id', $attempt->id)->value('id'), 'actor_user_id' => $actor->id,
                'status' => 'failed', 'error_code' => 'engine_unavailable', 'request_hash' => str_repeat('b', 64),
                'request_payload' => ['private' => 'secret-answer'], 'result' => null, 'duration_ms' => $duration]);
        }
        $before = $progression->fresh()->getAttributes();
        // Mutable exam ownership cannot move frozen operational history to another cohort.
        DB::table('exams')->where('id', $attempt->exam_id)->update(['exam_owner_id' => $other->exam->organization_id]);
        $report = app(AdaptiveOperationsService::class)->report(24, $snapshot->owner_key);
        $this->assertCount(1, $report['cohort_states']);
        $this->assertSame('blocked', $report['cohort_states'][0]->status);
        $this->assertSame($snapshot->engine_version, $report['cohort_states'][0]->engine_version);
        $this->assertSame(1, (int) $report['recent_attempt_stops'][0]->attempts);
        $this->assertSame(2, (int) $report['shadow_evaluations'][0]->evaluations);
        $this->assertSame(200.0, (float) $report['shadow_evaluations'][0]->mean_duration_ms);
        $this->assertSame(300, (int) $report['shadow_evaluations'][0]->max_duration_ms);
        $json = json_encode($report, JSON_THROW_ON_ERROR);
        foreach (['secret-calibration', 'secret-answer', $attempt->candidate_id, $attempt->candidate->email] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertSame($before, $progression->fresh()->getAttributes());
        $this->assertContains('live_selection_latency', $report['unavailable_metrics']);
        $this->assertCount(0, app(AdaptiveOperationsService::class)->report(24, 'institution:unknown')['cohort_states']);
    }

    public function test_window_empty_states_and_command_validation(): void
    {
        [$attempt] = $this->fixture(false);
        DB::table('adaptive_progressions')->where('exam_id', $attempt->exam_id)->update(['created_at' => now()->subHours(25)]);
        $this->assertCount(0, app(AdaptiveOperationsService::class)->report(24)['cohort_states']);
        $this->assertCount(1, app(AdaptiveOperationsService::class)->report(48)['cohort_states']);
        $this->artisan('adaptive:operations', ['--hours' => 24])->expectsOutputToContain('unavailable_metrics')->assertSuccessful();
        $this->artisan('adaptive:operations', ['--hours' => 0])->assertExitCode(2);
        $this->artisan('adaptive:operations', ['--hours' => '1.5'])->assertExitCode(2);
        $this->artisan('adaptive:operations', ['--owner' => 'organization:%'])->assertExitCode(2);
    }
}
