<?php

namespace App\Http\Controllers;

use App\Models\OfflineActivationCode;
use App\Models\OfflineServerActivation;
use App\Support\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OfflineActivationCodeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('downloadOfflineServer'), 403);

        $user = $request->user();
        $yearlyCode = $this->yearlyCodeForUser($user->id);
        $codes = OfflineActivationCode::query()
            ->with(['creator:id,name,email', 'organization:id,name', 'cbtCenter:id,name'])
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('created_by_user_id', $user->id))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (OfflineActivationCode $code): array => [
                'id' => $code->id,
                'code' => $this->decryptCode($code),
                'label' => $code->label,
                'status' => $code->status,
                'created_by' => $code->creator?->name,
                'organization_name' => $code->organization?->name,
                'center_name' => $code->cbtCenter?->name,
                'activation_count' => $code->activation_count,
                'max_activations' => $code->max_activations,
                'expires_at' => $code->expires_at?->toISOString(),
                'license_expires_at' => $code->license_expires_at?->toISOString(),
                'last_activated_at' => $code->last_activated_at?->toISOString(),
                'last_device_id' => $code->last_device_id,
                'last_admin_email' => $code->last_admin_email,
                'created_at' => $code->created_at?->toISOString(),
            ]);

        return Inertia::render('OfflineActivationCodes/Index', [
            'codes' => $codes,
            'canGenerateCode' => $yearlyCode === null,
            'generationLockedUntil' => $yearlyCode?->created_at?->copy()->addYear()->toISOString(),
        ]);
    }

    public function resetIndex(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $codes = OfflineActivationCode::query()
            ->with([
                'creator:id,name,email,role,organization_id,center_id,school_id,secondary_school_id,professional_school_id,cbt_center_id',
                'creator.organization:id,name',
                'creator.center:id,name',
                'creator.school:id,name',
                'creator.secondarySchool:id,name',
                'creator.professionalSchool:id,name',
                'creator.cbtCenter:id,name',
                'organization:id,name',
                'cbtCenter:id,name',
            ])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (OfflineActivationCode $code): array => $this->activationManagementRow($code));

        return Inertia::render('OfflineActivationCodes/ResetIndex', [
            'codes' => $codes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('downloadOfflineServer'), 403);

        $user = $request->user();
        $centerId = $user->cbt_center_id ?? $user->center_id;
        $organizationId = $user->organization_id;

        $yearlyCode = $this->yearlyCodeForUser($user->id);

        if ($yearlyCode) {
            return back()->withErrors([
                'activation_code' => 'You can only generate one offline activation code in a year. Use the existing code or wait until '.($yearlyCode->created_at?->copy()->addYear()->toDayDateTimeString() ?? 'the current yearly lock expires').'.',
            ]);
        }

        $plainCode = 'AX-OFFLINE-'.Str::upper(Str::random(6)).'-'.Str::upper(Str::random(6));

        OfflineActivationCode::query()->create([
            'created_by_user_id' => $user->id,
            'organization_id' => $organizationId,
            'cbt_center_id' => $centerId,
            'label' => 'Offline server activation',
            'code_hash' => Hash::make($plainCode),
            'code_encrypted' => Crypt::encryptString($plainCode),
            'status' => OfflineActivationCode::STATUS_ACTIVE,
            'max_activations' => 1,
            'activation_count' => 0,
            'expires_at' => null,
            'license_expires_at' => now()->addYear(),
        ]);

        return redirect()
            ->route('offline-activation-codes.index')
            ->with('success', 'Offline activation code generated.')
            ->with('activation_code', $plainCode);
    }

    public function reset(Request $request, OfflineActivationCode $offlineActivationCode): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        DB::transaction(function () use ($offlineActivationCode): void {
            OfflineServerActivation::query()
                ->where('offline_activation_code_id', $offlineActivationCode->id)
                ->where('status', 'activated')
                ->update([
                    'status' => 'revoked',
                    'updated_at' => now(),
                ]);

            $offlineActivationCode->forceFill([
                'activation_count' => 0,
                'last_activated_at' => null,
                'last_device_id' => null,
                'last_admin_email' => null,
            ])->save();
        });

        return redirect()
            ->route('offline-activation-codes.reset-index')
            ->with('success', 'Activation code reset. It can now be used on another device.');
    }

    private function decryptCode(OfflineActivationCode $code): string
    {
        if (! $code->code_encrypted) {
            return 'Unavailable';
        }

        try {
            return Crypt::decryptString($code->code_encrypted);
        } catch (\Throwable) {
            return 'Unavailable';
        }
    }

    private function yearlyCodeForUser(int $userId): ?OfflineActivationCode
    {
        return OfflineActivationCode::query()
            ->where('created_by_user_id', $userId)
            ->where('created_at', '>=', now()->subYear())
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function activationManagementRow(OfflineActivationCode $code): array
    {
        $creator = $code->creator;
        $owner = $this->activationOwner($code);

        return [
            'id' => $code->id,
            'code' => $this->decryptCode($code),
            'label' => $code->label,
            'status' => $code->status,
            'created_by' => $creator?->name,
            'creator_email' => $creator?->email,
            'creator_role' => $creator?->role ? AccessControl::roleLabel($creator->role) : null,
            'owner_type' => $owner['type'],
            'owner_name' => $owner['name'],
            'organization_name' => $code->organization?->name ?? $creator?->organization?->name,
            'center_name' => $code->cbtCenter?->name ?? $creator?->cbtCenter?->name ?? $creator?->center?->name,
            'activation_count' => $code->activation_count,
            'max_activations' => $code->max_activations,
            'license_expires_at' => $code->license_expires_at?->toISOString(),
            'last_activated_at' => $code->last_activated_at?->toISOString(),
            'last_device_id' => $code->last_device_id,
            'last_admin_email' => $code->last_admin_email,
            'created_at' => $code->created_at?->toISOString(),
            'can_reset' => $code->activation_count > 0 || filled($code->last_device_id),
        ];
    }

    /**
     * @return array{type: string, name: string|null}
     */
    private function activationOwner(OfflineActivationCode $code): array
    {
        $creator = $code->creator;

        return match (true) {
            $code->cbtCenter !== null => ['type' => 'CBT Center', 'name' => $code->cbtCenter->name],
            $creator?->cbtCenter !== null => ['type' => 'CBT Center', 'name' => $creator->cbtCenter->name],
            $creator?->center !== null => ['type' => 'Center', 'name' => $creator->center->name],
            $creator?->secondarySchool !== null => ['type' => 'Secondary School', 'name' => $creator->secondarySchool->name],
            $creator?->professionalSchool !== null => ['type' => 'Professional School', 'name' => $creator->professionalSchool->name],
            $creator?->school !== null => ['type' => 'School', 'name' => $creator->school->name],
            $code->organization !== null => ['type' => 'Organization', 'name' => $code->organization->name],
            $creator?->organization !== null => ['type' => 'Organization', 'name' => $creator->organization->name],
            default => ['type' => 'Platform', 'name' => null],
        };
    }
}
