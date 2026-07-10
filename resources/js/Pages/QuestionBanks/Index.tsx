import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, Eye, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { FormEvent } from 'react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { QuestionBank } from './types';

type Props = {
    questionBanks: { data: QuestionBank[] };
    can: { create: boolean };
};

export default function QuestionBanksIndex({ questionBanks, can }: Props) {
    const currentContext = usePage().props.current_context as { type?: string } | undefined;
    const isProfessional = currentContext?.type === 'professional_school' || questionBanks.data.some((bank) => bank.professional_school_id);

    return (
        <PortalAppShell title="Question Bank">
            <Head title="Question Bank" />
            <PageHeader
                eyebrow="Assessment"
                title="Question Bank"
                description={isProfessional ? 'Manage question-bank containers by course, module, and scope.' : 'Manage question-bank containers by subject and scope.'}
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button">
                            <Link href="/question-bank/create">
                                <Plus className="h-4 w-4" />
                                New Bank
                            </Link>
                        </Button>
                    </ProtectedAction>
                }
            />

            <BulkTools templateHref="/question-bank/template" uploadHref="/question-bank/import" />

            <DataTable<QuestionBank>
                rows={questionBanks.data}
                emptyTitle="No question banks found"
                columns={[
                    { key: 'name', header: 'Name', render: (bank) => <span className="font-semibold text-slateDark">{bank.name}</span> },
                    { key: 'structure', header: isProfessional ? 'Course / Module' : 'Subject', render: (bank) => isProfessional ? [bank.course_name, bank.module_name].filter(Boolean).join(' / ') || bank.subject_name || 'N/A' : bank.subject_name ?? 'N/A' },
                    { key: 'scope', header: 'Scope', render: (bank) => bank.organization_name ?? bank.professional_school_name ?? bank.secondary_school_name ?? bank.cbt_center_name ?? bank.school_name ?? bank.center_name ?? 'Platform' },
                    { key: 'questions_count', header: 'Questions', render: (bank) => String(bank.questions_count ?? 0) },
                    { key: 'status', header: 'Status', render: (bank) => <StatusBadge label={bank.status_label} tone={bank.status === 'active' ? 'success' : bank.status === 'archived' ? 'neutral' : 'warning'} /> },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (bank) => (
                            <ActionDropdown
                                items={[
                                    { label: 'View', icon: Eye, disabled: bank.can?.view === false, onSelect: () => router.visit(`/question-bank/${bank.id}`) },
                                    { label: 'Edit', icon: Pencil, disabled: bank.can?.update === false, onSelect: () => router.visit(`/question-bank/${bank.id}/edit`) },
                                    {
                                        label: 'Delete',
                                        icon: Trash2,
                                        destructive: true,
                                        disabled: bank.can?.delete === false,
                                        onSelect: () => window.confirm('Delete this question bank?') && router.delete(`/question-bank/${bank.id}`, { preserveScroll: true }),
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
