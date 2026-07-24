import { Head, router, useForm } from '@inertiajs/react';
import { Loader2, RotateCcw, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AlertBanner, DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type ActivationCodeRow = {
    id: number;
    code: string;
    label: string | null;
    status: string;
    created_by: string | null;
    creator_email: string | null;
    creator_role: string | null;
    owner_type: string;
    owner_name: string | null;
    organization_name: string | null;
    center_name: string | null;
    activation_count: number;
    max_activations: number;
    remaining_activations: number;
    license_expires_at: string | null;
    last_activated_at: string | null;
    last_device_id: string | null;
    last_admin_email: string | null;
    created_at: string | null;
    can_reset: boolean;
    devices: ActivationDevice[];
};

type ActivationDevice = {
    id: number;
    device_id: string;
    admin_email: string;
    center_name: string | null;
    status: string;
    activated_at: string | null;
    expires_at: string | null;
};

export default function OfflineActivationResetsIndex({ codes }: { codes: ActivationCodeRow[] }) {
    const [resettingCodeId, setResettingCodeId] = useState<number | null>(null);
    const [removingDeviceId, setRemovingDeviceId] = useState<number | null>(null);
    const resetForm = useForm({});

    function resetCode(code: ActivationCodeRow) {
        if (!window.confirm('Reset this activation code so another device can use it? The current device will no longer be the active binding.')) {
            return;
        }

        setResettingCodeId(code.id);
        resetForm.post(`/admin/manage-activation/${code.id}/reset`, {
            preserveScroll: true,
            onFinish: () => setResettingCodeId(null),
        });
    }

    function removeDevice(code: ActivationCodeRow, device: ActivationDevice) {
        if (!window.confirm('Remove this device from the activation code? This frees one device slot for another offline server.')) {
            return;
        }

        setRemovingDeviceId(device.id);
        router.delete(`/admin/manage-activation/${code.id}/devices/${device.id}`, {
            preserveScroll: true,
            onFinish: () => setRemovingDeviceId(null),
        });
    }

    return (
        <PortalAppShell title="Manage Activation">
            <Head title="Manage Activation" />
            <section className="mx-auto max-w-7xl space-y-6">
                <PageHeader
                    eyebrow="Admin"
                    title="Manage Activation"
                    description="Review offline activation codes, plan device limits, active devices, and remove a device when a center server moves to another machine."
                />

                <AlertBanner
                    tone="warning"
                    title="Super admin action"
                    message="Removing a device revokes only that device and immediately frees one activation slot for the same activation code."
                />

                <DataTable<ActivationCodeRow>
                    rows={codes}
                    emptyTitle="No activation codes found"
                    columns={[
                        {
                            key: 'code',
                            header: 'Activation Code',
                            render: (code) => <span className="break-all font-mono text-sm font-semibold text-slateDark">{code.code}</span>,
                        },
                        { key: 'status', header: 'Status', render: (code) => <StatusBadge label={code.status} tone={code.status === 'active' ? 'success' : 'neutral'} /> },
                        {
                            key: 'usage',
                            header: 'Usage',
                            render: (code) => (
                                <div className="min-w-28">
                                    <div className="font-semibold text-slateDark">{code.activation_count}/{code.max_activations} used</div>
                                    <div className="text-xs text-slate-500">{code.remaining_activations} remaining</div>
                                </div>
                            ),
                        },
                        {
                            key: 'created_by',
                            header: 'Admin',
                            render: (code) => (
                                <div className="min-w-48">
                                    <div className="font-semibold text-slateDark">{code.created_by ?? '-'}</div>
                                    <div className="text-xs text-slate-500">{code.creator_email ?? '-'}</div>
                                    <div className="text-xs text-slate-500">{code.creator_role ?? '-'}</div>
                                </div>
                            ),
                        },
                        {
                            key: 'owner_name',
                            header: 'Scope',
                            render: (code) => (
                                <div className="min-w-44">
                                    <div className="font-semibold text-slateDark">{code.owner_name ?? '-'}</div>
                                    <div className="text-xs text-slate-500">{code.owner_type}</div>
                                </div>
                            ),
                        },
                        { key: 'organization_name', header: 'Organization', render: (code) => code.organization_name ?? '-' },
                        { key: 'center_name', header: 'Center / School', render: (code) => code.center_name ?? code.owner_name ?? '-' },
                        {
                            key: 'devices',
                            header: 'Active Devices',
                            render: (code) => (
                                <div className="grid min-w-80 gap-2">
                                    {code.devices.length === 0 && <span className="text-sm text-slate-500">No active device</span>}
                                    {code.devices.map((device) => (
                                        <div key={device.id} className="rounded-md border border-border bg-lightBackground p-2">
                                            <div className="break-all font-mono text-xs font-semibold text-slateDark">{device.device_id}</div>
                                            <div className="mt-1 text-xs text-slate-500">{device.admin_email}</div>
                                            <div className="mt-1 text-xs text-slate-500">{device.center_name ?? 'Offline server'} - {formatDate(device.activated_at)}</div>
                                            <Button
                                                className="mt-2 h-8 px-2"
                                                disabled={removingDeviceId === device.id}
                                                onClick={() => removeDevice(code, device)}
                                                size="sm"
                                                type="button"
                                                variant="danger"
                                            >
                                                {removingDeviceId === device.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                                                Remove
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            ),
                        },
                        { key: 'last_admin_email', header: 'Last Admin', render: (code) => code.last_admin_email ?? '-' },
                        { key: 'last_activated_at', header: 'Last Activated', render: (code) => formatDate(code.last_activated_at) },
                        { key: 'license_expires_at', header: 'License Expiry', render: (code) => formatDate(code.license_expires_at) },
                        {
                            key: 'actions',
                            header: 'Actions',
                            render: (code) => (
                                <Button
                                    disabled={!code.can_reset || resetForm.processing}
                                    onClick={() => resetCode(code)}
                                    size="sm"
                                    type="button"
                                    variant="outline"
                                >
                                    {resettingCodeId === code.id ? <Loader2 className="h-4 w-4 animate-spin" /> : code.can_reset ? <RotateCcw className="h-4 w-4" /> : <ShieldCheck className="h-4 w-4" />}
                                    {code.can_reset ? 'Reset All' : 'No Binding'}
                                </Button>
                            ),
                        },
                    ]}
                />
            </section>
        </PortalAppShell>
    );
}

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '-';
}
