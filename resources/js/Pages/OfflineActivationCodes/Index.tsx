import { Head, useForm } from '@inertiajs/react';
import { Copy, KeyRound, Loader2, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AlertBanner, DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type ActivationCodeRow = {
    id: number;
    code: string;
    label: string | null;
    status: string;
    created_by: string | null;
    organization_name: string | null;
    center_name: string | null;
    activation_count: number;
    max_activations: number;
    remaining_activations: number;
    expires_at: string | null;
    license_expires_at: string | null;
    last_activated_at: string | null;
    last_device_id: string | null;
    last_admin_email: string | null;
    created_at: string | null;
};

type Props = {
    codes: ActivationCodeRow[];
    canGenerateCode: boolean;
    generationLockedUntil: string | null;
};

export default function OfflineActivationCodesIndex({ codes, canGenerateCode, generationLockedUntil }: Props) {
    const [copiedCodeId, setCopiedCodeId] = useState<number | null>(null);
    const [copyError, setCopyError] = useState<string | null>(null);
    const { post, processing, errors } = useForm({});

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/offline-activation-codes', {
            preserveScroll: true,
            onSuccess: () => setCopiedCodeId(null),
        });
    }

    async function copyCode(code: ActivationCodeRow) {
        setCopyError(null);

        try {
            await copyText(code.code);
            setCopiedCodeId(code.id);
        } catch {
            setCopyError('Unable to copy automatically. Select the code text and copy it manually.');
        }
    }

    return (
        <PortalAppShell title="Offline Activation Codes">
            <Head title="Offline Activation Codes" />
            <section className="mx-auto max-w-7xl space-y-6">
                <PageHeader
                    eyebrow="Offline Delivery"
                    title="Activation Codes"
                    description="Generate activation codes for the generic AlignEx Center Server app. Scope and license settings are handled automatically."
                />

                {Object.keys(errors).length > 0 && (
                    <AlertBanner tone="danger" title="Unable to generate activation code" message={String(errors.activation_code ?? 'Please try again or contact a platform administrator.')} />
                )}

                {copyError && <AlertBanner tone="danger" title="Copy failed" message={copyError} />}

                <form onSubmit={submit} className="rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-md bg-green-50 text-primary">
                                <KeyRound className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="font-semibold text-slateDark">Generate Code</h2>
                                <p className="text-sm text-slate-500">
                                    {canGenerateCode
                                        ? 'A one-year activation code will be created for your account scope using the device limit in your pricing plan.'
                                        : `You already generated a code for this yearly period. Next generation: ${formatDate(generationLockedUntil)}.`}
                                </p>
                            </div>
                        </div>
                        <Button disabled={processing || !canGenerateCode}>
                            {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                            Generate Activation Code
                        </Button>
                    </div>
                </form>

                <DataTable<ActivationCodeRow>
                    rows={codes}
                    emptyTitle="No activation codes generated"
                    columns={[
                        {
                            key: 'code',
                            header: 'Activation Code',
                            render: (code) => (
                                <div className="flex min-w-72 items-center gap-2">
                                    <span className="break-all font-mono text-sm font-semibold text-slateDark">{code.code}</span>
                                    {code.code !== 'Unavailable' && (
                                        <Button type="button" size="sm" variant="outline" onClick={() => void copyCode(code)}>
                                            <Copy className="h-4 w-4" />
                                            {copiedCodeId === code.id ? 'Copied' : 'Copy'}
                                        </Button>
                                    )}
                                </div>
                            ),
                        },
                        { key: 'status', header: 'Status', render: (code) => <StatusBadge label={code.status} tone={code.status === 'active' ? 'success' : 'neutral'} /> },
                        {
                            key: 'usage',
                            header: 'Devices',
                            render: (code) => (
                                <div className="min-w-28">
                                    <div className="font-semibold text-slateDark">{code.activation_count}/{code.max_activations} used</div>
                                    <div className="text-xs text-slate-500">{code.remaining_activations} remaining</div>
                                </div>
                            ),
                        },
                        { key: 'created_by', header: 'Generated By', render: (code) => code.created_by ?? '-' },
                        { key: 'organization_name', header: 'Organization', render: (code) => code.organization_name ?? '-' },
                        { key: 'center_name', header: 'Center', render: (code) => code.center_name ?? '-' },
                        { key: 'last_device_id', header: 'Bound Device', render: (code) => code.last_device_id ? <span className="break-all font-mono text-xs">{code.last_device_id}</span> : '-' },
                        { key: 'license_expires_at', header: 'License Expiry', render: (code) => formatDate(code.license_expires_at) },
                        { key: 'expires_at', header: 'Code Expiry', render: (code) => formatDate(code.expires_at) },
                        { key: 'last_admin_email', header: 'Last Admin', render: (code) => code.last_admin_email ?? '-' },
                        { key: 'last_activated_at', header: 'Last Activated', render: (code) => formatDate(code.last_activated_at) },
                        { key: 'created_at', header: 'Generated At', render: (code) => formatDate(code.created_at) },
                    ]}
                />
            </section>
        </PortalAppShell>
    );
}

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '-';
}

async function copyText(value: string): Promise<void> {
    if (navigator.clipboard?.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', 'true');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        const copied = document.execCommand('copy');

        if (!copied) {
            throw new Error('Copy command was blocked.');
        }
    } finally {
        document.body.removeChild(textarea);
    }
}
