<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pricing_plans')) {
            DB::table('pricing_plans')
                ->orderBy('id')
                ->each(function (object $plan): void {
                    $limits = json_decode($plan->limits ?? '[]', true) ?: [];

                    if (! isset($limits['max_devices']) || (int) $limits['max_devices'] < 1) {
                        $limits['max_devices'] = $this->defaultDeviceLimit((string) $plan->slug);

                        DB::table('pricing_plans')
                            ->where('id', $plan->id)
                            ->update([
                                'limits' => json_encode($limits),
                                'updated_at' => now(),
                            ]);
                    }
                });
        }

        if (Schema::hasTable('offline_activation_codes') && Schema::hasTable('offline_server_activations')) {
            DB::table('offline_activation_codes')
                ->orderBy('id')
                ->each(function (object $code): void {
                    $activeActivations = DB::table('offline_server_activations')
                        ->where('offline_activation_code_id', $code->id)
                        ->where('status', 'activated')
                        ->orderByDesc('activated_at')
                        ->get(['device_id', 'admin_email', 'activated_at']);
                    $latest = $activeActivations->first();

                    DB::table('offline_activation_codes')
                        ->where('id', $code->id)
                        ->update([
                            'max_activations' => max((int) $code->max_activations, 1),
                            'activation_count' => $activeActivations->count(),
                            'last_activated_at' => $latest?->activated_at,
                            'last_device_id' => $latest?->device_id,
                            'last_admin_email' => $latest?->admin_email,
                            'updated_at' => now(),
                        ]);
                });
        }
    }

    public function down(): void
    {
        // This migration normalizes existing limits and usage counters only.
    }

    private function defaultDeviceLimit(string $slug): int
    {
        return match ($slug) {
            'professional' => 2,
            'enterprise' => 5,
            default => 1,
        };
    }
};
