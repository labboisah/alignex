import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { FormEvent } from 'react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Subject } from './types';

type Props = {
    subjects: { data: Subject[] };
    can: { create: boolean };
};

export default function SubjectsIndex({ subjects, can }: Props) {
    const currentContext = usePage().props.current_context as { type?: string } | undefined;
    const isSecondary = currentContext?.type === 'secondary_school';

    return (
        <PortalAppShell title="Subjects">
            <Head title="Subjects" />
            <PageHeader
                eyebrow="Question Bank"
                title="Subjects"
                description="Manage examinable subjects for question authoring and exam configuration."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button">
                            <Link href="/subjects/create">
                                <Plus className="h-4 w-4" />
                                New Subject
                            </Link>
                        </Button>
                    </ProtectedAction>
                }
            />

            <BulkTools templateHref="/subjects/template" uploadHref="/subjects/import" />

            <DataTable<Subject>
                rows={subjects.data}
                emptyTitle="No subjects found"
                columns={[
                    { key: 'name', header: 'Name', render: (subject) => <span className="font-semibold text-slateDark">{subject.name}</span> },
                    { key: 'scope', header: 'Scope', render: (subject) => subject.secondary_school_name ?? subject.organization_name ?? subject.school_name ?? subject.center_name ?? 'Platform' },
                    { key: 'school_class_name', header: 'Class', render: (subject) => subject.school_class_name ?? 'All classes' },
                    ...(!isSecondary ? [{ key: 'topics_count', header: 'Topics', render: (subject: Subject) => String(subject.topics_count ?? 0) }] : []),
                    { key: 'question_banks_count', header: 'Banks', render: (subject) => String(subject.question_banks_count ?? 0) },
                    { key: 'status', header: 'Status', render: (subject) => <StatusBadge label={subject.status_label} tone={subject.status === 'active' ? 'success' : 'neutral'} /> },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (subject) => (
                            <ActionDropdown
                                items={[
                                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/subjects/${subject.id}/edit`) },
                                    {
                                        label: 'Delete',
                                        icon: Trash2,
                                        destructive: true,
                                        onSelect: () => window.confirm('Delete this subject?') && router.delete(`/subjects/${subject.id}`, { preserveScroll: true }),
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
