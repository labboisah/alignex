<?php

namespace Tests\Feature;

use App\Models\OfflineActivationCode;
use App\Models\OfflineServerActivation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfflineActivationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_code_detects_same_device_and_blocks_other_devices_until_reset(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('secret-password'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $organizationAdmin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
        ]);

        $plainCode = 'AX-OFFLINE-TEST-CODE';
        $activationCode = OfflineActivationCode::query()->create([
            'created_by_user_id' => $admin->id,
            'label' => 'Offline server activation',
            'code_hash' => Hash::make($plainCode),
            'code_encrypted' => Crypt::encryptString($plainCode),
            'status' => OfflineActivationCode::STATUS_ACTIVE,
            'max_activations' => 1,
            'activation_count' => 0,
            'license_expires_at' => now()->addYear(),
        ]);

        $payload = [
            'activation_code' => $plainCode,
            'admin_email' => 'admin@example.test',
            'admin_password' => 'secret-password',
            'center_name' => 'Main Center',
        ];

        $this->postJson('/api/offline/activate', [
            ...$payload,
            'device_id' => 'device-one',
        ])
            ->assertOk()
            ->assertJsonPath('device_id', 'device-one')
            ->assertJsonPath('same_device', false);

        $this->assertSame(1, $activationCode->refresh()->activation_count);
        $this->assertSame('device-one', $activationCode->last_device_id);

        $this->postJson('/api/offline/activate', [
            ...$payload,
            'device_id' => 'device-one',
        ])
            ->assertOk()
            ->assertJsonPath('same_device', true);

        $this->assertSame(1, $activationCode->refresh()->activation_count);

        $this->postJson('/api/offline/activate', [
            ...$payload,
            'device_id' => 'device-two',
        ])->assertConflict();

        $this->actingAs($organizationAdmin)
            ->post("/admin/manage-activation/{$activationCode->id}/reset")
            ->assertForbidden();

        $this->actingAs($organizationAdmin)
            ->get('/admin/manage-activation')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/manage-activation')
            ->assertOk();

        $this->actingAs($admin)
            ->post("/admin/manage-activation/{$activationCode->id}/reset")
            ->assertRedirect('/admin/manage-activation');

        $this->assertSame(0, $activationCode->refresh()->activation_count);
        $this->assertNull($activationCode->last_device_id);
        $this->assertSame('revoked', OfflineServerActivation::query()->where('device_id', 'device-one')->value('status'));

        $this->postJson('/api/offline/activate', [
            ...$payload,
            'device_id' => 'device-two',
        ])
            ->assertOk()
            ->assertJsonPath('device_id', 'device-two')
            ->assertJsonPath('same_device', false);

        $this->assertSame(1, $activationCode->refresh()->activation_count);
        $this->assertSame('device-two', $activationCode->last_device_id);
    }
}
