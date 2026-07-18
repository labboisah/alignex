import { Head, router, useForm } from '@inertiajs/react';
import { Download, Edit, Plus, Save, Trash2, X } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Artifact = 'server' | 'client_app';

type AppRelease = {
    id: number;
    artifact: Artifact;
    artifact_label: string;
    version: string;
    filename: string;
    file_path: string;
    size_bytes: number;
    formatted_size: string;
    sha256: string;
    release_notes: string | null;
    is_active: boolean;
    published_at: string | null;
    created_at: string | null;
    download_url: string;
};

type FormData = {
    artifact: Artifact;
    version: string;
    file: File | null;
    file_path: string;
    release_notes: string;
    is_active: boolean;
    published_at: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

const blankForm: FormData = {
    artifact: 'server',
    version: '',
    file: null,
    file_path: '',
    release_notes: '',
    is_active: true,
    published_at: '',
};

export default function AppReleasesIndex({ releases }: { releases: { data: AppRelease[] } }) {
    const [editing, setEditing] = useState<AppRelease | null>(null);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(blankForm);

    const beginCreate = () => {
        setEditing(null);
        clearErrors();
        reset();
    };

    const beginEdit = (release: AppRelease) => {
        setEditing(release);
        clearErrors();
        setData({
            artifact: release.artifact,
            version: release.version,
            file: null,
            file_path: release.file_path,
            release_notes: release.release_notes ?? '',
            is_active: release.is_active,
            published_at: toDateTimeLocal(release.published_at),
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editing) {
            router.post(`/app-releases/${editing.id}`, { ...data, _method: 'patch' }, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: beginCreate,
            });
            return;
        }

        post('/app-releases', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: beginCreate,
        });
    };

    return (
        <PortalAppShell title="App Releases">
            <Head title="App Releases" />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Platform"
                    title="App Releases"
                    description="Manage offline server packages and client app installers used by portal downloads and in-app update checks."
                    actions={(
                        <Button type="button" variant="secondary" onClick={beginCreate}>
                            <Plus className="h-4 w-4" />
                            New Release
                        </Button>
                    )}
                />

                <div className="mb-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slateDark">{editing ? `Edit ${editing.artifact_label} ${editing.version}` : 'Create App Release'}</h2>
                            <p className="mt-1 text-sm text-slate-600">Mark one server and one client app release active so update checks know what to offer.</p>
                        </div>
                        {editing && (
                            <Button type="button" variant="secondary" onClick={beginCreate}>
                                <X className="h-4 w-4" />
                                Cancel
                            </Button>
                        )}
                    </div>

                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="App" error={errors.artifact}>
                                <select className={inputClass} value={data.artifact} onChange={(event) => setData('artifact', event.target.value as Artifact)}>
                                    <option value="server">Offline Server</option>
                                    <option value="client_app">Client App</option>
                                </select>
                            </Field>
                            <Field label="Version" error={errors.version}>
                                <input className={inputClass} value={data.version} onChange={(event) => setData('version', event.target.value)} placeholder="1.0.0" required />
                            </Field>
                            <Field label="Published At" error={errors.published_at}>
                                <input className={inputClass} type="datetime-local" value={data.published_at} onChange={(event) => setData('published_at', event.target.value)} />
                            </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="Upload File" error={errors.file}>
                                <input
                                    className={inputClass}
                                    type="file"
                                    accept={data.artifact === 'server' ? '.zip' : '.exe'}
                                    onChange={(event) => setData('file', event.target.files?.[0] ?? null)}
                                />
                            </Field>
                            <Field label="Existing Public File Path" error={errors.file_path}>
                                <input
                                    className={inputClass}
                                    value={data.file_path}
                                    onChange={(event) => setData('file_path', event.target.value)}
                                    placeholder={data.artifact === 'server' ? 'downloads/offline-server/AlignEx-Center-Server-win-unpacked.zip' : 'downloads/candidate-client/AlignEx-Client-App-Setup-1.0.0.exe'}
                                />
                            </Field>
                        </div>

                        <Field label="Release Notes" error={errors.release_notes}>
                            <textarea className={inputClass} rows={4} value={data.release_notes} onChange={(event) => setData('release_notes', event.target.value)} />
                        </Field>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <label className="inline-flex h-10 items-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-semibold text-slate-700">
                                <input
                                    type="checkbox"
                                    className="rounded border-border text-primary focus:ring-primary"
                                    checked={data.is_active}
                                    onChange={(event) => setData('is_active', event.target.checked)}
                                />
                                Active release
                            </label>

                            <Button type="submit" disabled={processing}>
                                <Save className="h-4 w-4" />
                                {editing ? 'Save Release' : 'Create Release'}
                            </Button>
                        </div>
                    </form>
                </div>

                <DataTable<AppRelease>
                    rows={releases.data}
                    emptyTitle="No app releases found"
                    columns={[
                        { key: 'artifact', header: 'App', render: (release) => <span className="font-semibold text-slateDark">{release.artifact_label}</span> },
                        { key: 'version', header: 'Version', render: (release) => release.version },
                        { key: 'filename', header: 'File', render: (release) => <span className="break-all">{release.filename}</span> },
                        { key: 'formatted_size', header: 'Size', render: (release) => release.formatted_size },
                        { key: 'sha256', header: 'SHA-256', render: (release) => <code className="text-xs text-slate-600">{release.sha256.slice(0, 12)}...</code> },
                        { key: 'published_at', header: 'Published', render: (release) => formatDate(release.published_at) },
                        { key: 'status', header: 'Status', render: (release) => <StatusBadge label={release.is_active ? 'Active' : 'Inactive'} tone={release.is_active ? 'success' : 'neutral'} /> },
                        {
                            key: 'actions',
                            header: 'Actions',
                            render: (release) => (
                                <div className="flex flex-wrap gap-2">
                                    <Button asChild variant="secondary" className="h-9 px-3">
                                        <a href={release.download_url}>
                                            <Download className="h-4 w-4" />
                                            Download
                                        </a>
                                    </Button>
                                    <Button type="button" variant="secondary" className="h-9 px-3" onClick={() => beginEdit(release)}>
                                        <Edit className="h-4 w-4" />
                                        Edit
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="danger"
                                        className="h-9 px-3"
                                        onClick={() => window.confirm('Delete this app release?') && router.delete(`/app-releases/${release.id}`, { preserveScroll: true })}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Delete
                                    </Button>
                                </div>
                            ),
                        },
                    ]}
                />
            </section>
        </PortalAppShell>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block text-sm font-semibold text-slateDark">
            {label}
            {children}
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}

function toDateTimeLocal(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toISOString().slice(0, 16);
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
