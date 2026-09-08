<?php

namespace Tests\Feature;

use App\Models\AdaptiveOfflineLease;
use App\Models\AdaptiveOfflinePackage;
use App\Models\Exam;
use App\Models\OfflineActivationCode;
use App\Models\OfflineServerActivation;
use App\Models\User;
use App\Services\AdaptiveOfflinePilotService;
use App\Services\AdaptivePilotService;
use App\Services\AdaptiveRolloutService;
use App\Support\ExamOwnershipRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdaptivePilotDeliveryTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    public function test_offline_only_approval_allows_scheduling_but_not_cloud_delivery_and_emergency_blocks_reservations(): void
    {
        [$attempt, $actor, $activation] = $this->setupPilot();
        app(AdaptivePilotService::class)->save($attempt->exam, $actor, ['online_enabled' => false, 'offline_enabled' => true, 'purpose' => 'Offline only diagnostic cohort.']);
        $rollout = app(AdaptiveRolloutService::class);
        $this->assertFalse($rollout->status($attempt->exam)['can_publish']);
        $rollout->ensureSaveAllowed($attempt->exam, $attempt->exam);
        config(['adaptive.pilot_emergency_stop' => true]);
        $this->assertFalse($rollout->status($attempt->exam)['can_publish']);
        $this->expectException(ValidationException::class);
        app(AdaptiveOfflinePilotService::class)->create($attempt->exam, $actor, $activation, [$attempt->candidate_id]);
    }

    private function setupPilot(): array
    {
        [$attempt] = $this->fixture(false);
        $exam = $attempt->exam;
        $actor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        app(AdaptivePilotService::class)->save($exam, $actor, ['online_enabled' => true, 'offline_enabled' => true, 'purpose' => 'Supervised diagnostic software pilot.']);
        $code = OfflineActivationCode::create(['created_by_user_id' => $actor->id, 'label' => 'Pilot test',
            'code_hash' => Hash::make('pilot-test'), 'code_encrypted' => Crypt::encryptString('pilot-test'),
            'status' => 'active', 'max_activations' => 1, 'activation_count' => 1, 'license_expires_at' => now()->addDays(3)]);
        $activation = OfflineServerActivation::create(['offline_activation_code_id' => $code->id, 'device_id' => 'pilot-center', 'admin_email' => $actor->email, 'center_name' => 'Test center',
            'license_key' => Str::random(64), 'status' => 'activated', 'activated_at' => now(), 'expires_at' => now()->addDays(3)]);

        return [$attempt, $actor, $activation];
    }

    public function test_explicit_pilot_controls_enable_and_pause_online_without_environment_changes(): void
    {
        [$attempt,$actor] = $this->setupPilot();
        $this->assertTrue(app(AdaptiveRolloutService::class)->status($attempt->exam)['can_publish']);
        $state = $this->start($attempt);
        app(AdaptivePilotService::class)->save($attempt->exam, $actor, ['online_enabled' => false, 'offline_enabled' => false, 'purpose' => 'Pause new diagnostic starts.']);
        $this->assertFalse(app(AdaptiveRolloutService::class)->status($attempt->exam)['can_publish']);
        $this->commit($attempt, $state, true, 'after-pilot-pause');
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'adaptive_pilot_changed']);
    }

    public function test_reserved_offline_package_replays_exact_recovery_and_rejects_conflicting_upload(): void
    {
        [$attempt,$actor,$activation] = $this->setupPilot();
        $service = app(AdaptiveOfflinePilotService::class);
        $package = $service->create($attempt->exam, $actor, $activation, [$attempt->candidate_id]);
        $envelope = $service->envelope($package, $activation);
        $this->assertSame(hash_hmac('sha256', $envelope['body'], $activation->license_key), $envelope['mac']);
        $this->assertSame('alignex.diagnostic-offline.v1', $package->payload['contract']);
        try {
            $this->start($attempt);
            $this->fail('Cloud start bypassed reservation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('exam', $e->errors());
        }
        $process = new Process(['node', base_path('tests/Support/simulate-pilot.cjs')]);
        $process->setInput(json_encode(['config' => $package->payload['config'], 'candidate' => $attempt->candidate_id, 'at' => now()->getTimestampMs() + 1], JSON_THROW_ON_ERROR));
        $process->mustRun();
        $transcript = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        unset($transcript['result']);
        $lease = AdaptiveOfflineLease::where('package_id', $package->id)->firstOrFail();
        $result = $service->sync($lease, $activation, $transcript);
        $this->assertSame('verified', $result['status']);
        $this->assertSame(540, $result['result']['balances'][0]['earned']);
        $this->assertSame(60, $result['result']['balances'][0]['penalty']);
        $this->assertFalse($result['result']['consequential_approved']);
        $this->assertSame($result, $service->sync($lease->fresh(), $activation, $transcript));
        $this->assertNull($attempt->fresh()->score);
        $transcript['state_hash'] = str_repeat('0', 64);
        $this->expectException(ValidationException::class);
        $service->sync($lease->fresh(), $activation, $transcript);
    }

    public function test_other_owner_cannot_manage_pilot_and_started_attempt_cannot_be_exported(): void
    {
        [$attempt,$actor,$activation] = $this->setupPilot();
        $other = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN]);
        $this->actingAs($other)->get('/exams/'.$attempt->exam_id.'/adaptive/pilot')->assertForbidden();
        $this->start($attempt);
        $this->expectException(ValidationException::class);
        app(AdaptiveOfflinePilotService::class)->create($attempt->exam, $actor, $activation, [$attempt->candidate_id]);
    }

    public static function pilotContexts(): array
    {
        return array_map(fn ($type) => [$type], ['organization', 'institution', 'professional_school', 'cbt_center', 'secondary_school']);
    }

    #[DataProvider('pilotContexts')]
    public function test_all_five_contexts_can_explicitly_reserve_formative_pilots(string $type): void
    {
        require_once base_path('tests/Browser/fixtures.php');
        $data = \browserFixture(['owner' => $type]);
        $exam = Exam::findOrFail($data['exam_id']);
        $actor = User::findOrFail($data['actor_id']);
        $this->actingAs($actor)->post('/exams/'.$exam->id.'/adaptive/pilot', [
            'online_enabled' => true, 'offline_enabled' => true, 'purpose' => 'Explicit supervised formative diagnostic cohort.', 'diagnostic_only' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(app(AdaptiveRolloutService::class)->status($exam)['can_publish']);
        $code = OfflineActivationCode::create(['created_by_user_id' => $actor->id, 'label' => 'Context pilot', 'code_hash' => Hash::make('test'),
            'code_encrypted' => Crypt::encryptString('test'), 'status' => 'active', 'max_activations' => 1, 'activation_count' => 1]);
        $activation = OfflineServerActivation::create(['offline_activation_code_id' => $code->id, 'device_id' => 'context-center', 'admin_email' => $actor->email,
            'center_name' => 'Context center', 'license_key' => Str::random(64), 'status' => 'activated', 'activated_at' => now(), 'expires_at' => now()->addDays(3)]);
        $ids = $exam->candidates()->pluck('candidates.id')->all();
        $this->post('/exams/'.$exam->id.'/adaptive/pilot/packages', ['activation_id' => $activation->id, 'candidate_ids' => $ids])
            ->assertSessionHasNoErrors()->assertRedirect();
        $package = AdaptiveOfflinePackage::where('exam_id', $exam->id)->firstOrFail();
        $this->assertSame($type, $package->payload['context']);
        $this->assertDatabaseHas('adaptive_offline_leases', ['exam_id' => $exam->id, 'candidate_id' => $ids[0], 'status' => 'reserved']);
        $this->get('/exams/'.$exam->id.'/adaptive/pilot')->assertOk();
        if ($type === 'secondary_school') {
            $exam->exam_category = 'terminal';
            $this->assertFalse(app(AdaptiveRolloutService::class)->status($exam)['can_publish']);
            $this->assertFalse(ExamOwnershipRules::isValid($type, 'terminal', 'adaptive'));
        }
    }

    public function test_invalid_replay_is_quarantined_without_recording_a_score(): void
    {
        [$attempt,$actor,$activation] = $this->setupPilot();
        $service = app(AdaptiveOfflinePilotService::class);
        $package = $service->create($attempt->exam, $actor, $activation, [$attempt->candidate_id]);
        $lease = AdaptiveOfflineLease::where('package_id', $package->id)->firstOrFail();
        $result = $service->sync($lease, $activation, ['commands' => [['key' => 'invalid-command', 'op' => 'commit', 'at' => now()->getTimestampMs() + 1]], 'state_hash' => str_repeat('0', 64)]);
        $this->assertSame('quarantined', $result['status']);
        $this->assertNull($result['result']);
        $this->assertNull($attempt->fresh()->score);
    }
}
