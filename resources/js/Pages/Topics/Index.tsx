import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { FormEvent } from 'react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Topic } from './types';

type Props = {
    topics: { data: Topic[] };
    can: { create: boolean };
};

export default function TopicsIndex({ topics, can }: Props) {
    return (
        <PortalAppShell title="Topics">
            <Head title="Topics" />
            <PageHeader
                eyebrow="Question Bank"
                title="Topics"
                description="Manage subject topic taxonomy for question organization."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button">
                            <Link href="/topics/create">
                                <Plus className="h-4 w-4" />
                                New Topic
                            </Link>
                        </Button>
                    </ProtectedAction>
                }
            />

            <BulkTools templateHref="/topics/template" uploadHref="/topics/import" />

            <DataTable<Topic>
                rows={topics.data}
                emptyTitle="No topics found"
                columns={[
                    { key: 'name', header: 'Name', render: (topic) => <span className="font-semibold text-slateDark">{topic.name}</span> },
                    { key: 'subject_name', header: 'Subject', render: (topic) => topic.subject_name ?? 'N/A' },
                    { key: 'parent_name', header: 'Parent', render: (topic) => topic.parent_name ?? 'None' },
                    { key: 'status', header: 'Status', render: (topic) => <StatusBadge label={topic.status_label} tone={topic.status === 'active' ? 'success' : 'neutral'} /> },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (topic) => (
                            <ActionDropdown
                                items={[
                                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/topics/${topic.id}/edit`) },
                                    {
                                        label: 'Delete',
                                        icon: Trash2,
                                        destructive: true,
                                        onSelect: () => window.confirm('Delete this topic?') && router.delete(`/topics/${topic.id}`, { preserveScroll: true }),
                                    },
                                ]}
                            />
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}

function BulkTools({ templateHref, uploadHref }: { templateHref: string; uploadHref: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({ file: null });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(uploadHref, { forceFormData: true, preserveScroll: true, onSuccess: () => reset('file') });
    };

    return (
        <form onSubmit={submit} className="mb-5 flex flex-wrap items-end gap-3 rounded-md border border-border bg-white p-4 shadow-sm">
            <Button asChild type="button" variant="secondary">
                <a href={templateHref}>
                    <Download className="h-4 w-4" />
                    Template
                </a>
            </Button>
            <label className="min-w-64 flex-1 text-sm font-semibold text-slateDark">
                Upload CSV
                <input className="mt-1 block w-full rounded-md border border-border text-sm file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-semibold" type="file" accept=".csv,text/csv" onChange={(event) => setData('file', event.target.files?.[0] ?? null)} />
                {errors.file && <span className="mt-1 block text-sm text-danger">{errors.file}</span>}
            </label>
            <Button type="submit" disabled={processing || !data.file}>
                <Upload className="h-4 w-4" />
                Upload
            </Button>
        </form>
    );
}
