import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { ActionDropdown, DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { CbtCenter, QuestionBankRow } from './types';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function QuestionBanks({ center, subjects = [], questionBanks }: { center: CbtCenter; subjects?: any[]; questionBanks: QuestionBankRow[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ subject_id: '', name: '', code: '', description: '', status: 'active' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/cbt-centers/${center.id}/question-banks`, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <PortalAppShell title={`${center.name} Question Bank`}>
            <Head title={`${center.name} Question Bank`} />
            <PageHeader eyebrow={center.name} title="Question Bank" description="Create CBT center question banks for recruitment, certification, assessment, practice, and general exams." />
            <form onSubmit={submit} className="mb-6">
                <FormSection title="New Question Bank" description="This bank belongs directly to the CBT center and does not need academic or professional structure." footer={<Button disabled={processing}>Create Question Bank</Button>}>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Subject" error={errors.subject_id}><select required className={inputClass} value={data.subject_id} onChange={(event) => setData('subject_id', event.target.value)}><option value="">Select subject</option>{subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}</select></Field>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></Field>
                    </div>
                    <Field label="Description" error={errors.description}><textarea rows={3} className={inputClass} value={data.description} onChange={(event) => setData('description', event.target.value)} /></Field>
                </FormSection>
            </form>
            <DataTable rows={questionBanks} emptyTitle="No question banks" columns={[
                { key: 'name', header: 'Name' },
                { key: 'subject_name', header: 'Subject' },
                { key: 'questions_count', header: 'Questions' },
                { key: 'description', header: 'Description' },
                { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'actions', header: 'Actions', render: (row) => <ActionDropdown items={[
                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/question-bank/${row.id}/edit`) },
                    { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => window.confirm('Delete this question bank?') && router.delete(`/question-bank/${row.id}`, { preserveScroll: true }) },
                ]} /> },
            ]} />
        </PortalAppShell>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
