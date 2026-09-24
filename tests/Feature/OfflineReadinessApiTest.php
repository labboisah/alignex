<?php

namespace Tests\Feature;

use App\Models\OfflineActivationCode;
use App\Models\OfflineReadinessPackage;
use App\Models\OfflineReadinessReport;
use App\Models\OfflineServerActivation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineReadinessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_readiness_package_uses_activation_token_without_portal_password(): void
    {
        [$activation, $token] = $this->activation();
        $payload = [
            'subjects' => [[
                'id' => 'subject-1',
                'name' => 'Synthetic',
                'questions' => [[
                    'id' => 'question-1',
                    'body' => 'Synthetic question',
                    'options' => [
                        ['label' => 'A', 'text' => 'Option A'],
                        ['label' => 'B', 'text' => 'Option B'],
                    ],
                ]],
            ]],
        ];
        OfflineReadinessPackage::query()->create([
            'code' => 'autoboot.production.v1-15',
            'version' => '1',
            'capacity_profile' => 15,
            'candidate_count' => 15,
            'backup_percent' => 15,
            'autoboot_target_clients' => 18,
            'question_count' => 1,
            'subject_count' => 1,
            'status' => OfflineReadinessPackage::STATUS_ACTIVE,
            'payload' => $payload,
        ]);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-AlignEx-Device-Id' => $activation->device_id,
        ])->getJson('/api/offline/readiness-packages/autoboot.production.v1-15')
            ->assertOk()
            ->assertJsonPath('package.code', 'autoboot.production.v1-15')
            ->assertJsonStructure(['package' => ['payload_json', 'checksum_sha256']]);
    }

    public function test_readiness_package_rejects_invalid_activation_token(): void
    {
        [$activation, , $admin] = $this->activation();

        $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'X-AlignEx-Device-Id' => $activation->device_id,
        ])->getJson('/api/offline/readiness-packages/missing')->assertUnauthorized();
    }

    public function test_super_admin_can_download_a_checksum_verified_readiness_evidence_bundle(): void
    {
        [$activation] = $this->activation();
        $payload = ['report_id' => 'source-report-1', 'drill_id' => 'drill-1', 'events' => [['type' => 'answer_acknowledged']]];
        $report = OfflineReadinessReport::query()->create([
            'id' => (string) Str::uuid(),
            'drill_id' => 'drill-1',
            'activation_id' => $activation->id,
            'report_version' => '1',
            'drill_name' => 'Capacity 25',
            'readiness_status' => 'incomplete',
            'content_pack_version' => 'autoboot.v1-25',
            'expected_clients' => 29,
            'connected_clients' => 29,
            'completed_clients' => 0,
            'failed_clients' => 29,
            'total_questions' => 100,
            'total_answers' => 260,
            'total_submissions' => 0,
            'event_count' => 1,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'payload' => $payload,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson("/offline-readiness-reports/{$report->id}/evidence")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="readiness-evidence-drill-1.json"')
            ->assertJsonPath('evidence_bundle_version', 'alignex.readiness-evidence.v1')
            ->assertJsonPath('report.payload_sha256', $report->payload_hash)
            ->assertJsonPath('evidence.events.0.type', 'answer_acknowledged');
    }

    public function test_evidence_download_rejects_a_tampered_stored_payload(): void
    {
        [$activation, , $admin] = $this->activation();
        $report = OfflineReadinessReport::query()->create([
            'id' => (string) Str::uuid(),
            'drill_id' => 'drill-tampered',
            'activation_id' => $activation->id,
            'report_version' => '1',
            'drill_name' => 'Tampered evidence',
            'readiness_status' => 'incomplete',
            'content_pack_version' => 'autoboot.v1-25',
            'payload_hash' => str_repeat('a', 64),
            'payload' => ['report_id' => 'source-report-2', 'drill_id' => 'drill-tampered'],
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson("/offline-readiness-reports/{$report->id}/evidence")
            ->assertStatus(409);
    }

    private function activation(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $code = OfflineActivationCode::query()->create([
            'created_by_user_id' => $admin->id,
            'label' => 'Readiness API test',
            'code_hash' => Hash::make('readiness-api-test'),
            'status' => OfflineActivationCode::STATUS_ACTIVE,
            'max_activations' => 1,
        ]);
        $token = Str::random(64);
        $activation = OfflineServerActivation::query()->create([
            'offline_activation_code_id' => $code->id,
            'device_id' => 'readiness-test-device',
            'admin_email' => $admin->email,
            'center_name' => 'Readiness Test Center',
            'license_key' => $token,
            'status' => 'activated',
            'activated_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        return [$activation, $token, $admin];
    }
}