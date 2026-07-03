import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { ActionDropdown, DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function Questions({ professionalSchool, questions, questionBanks = [], importSummary }: { professionalSchool: any; questions: any[]; questionBanks?: any[]; importSummary?: any }) {
    const importForm = useForm<{ question_bank_id: string; file: File | null }>({ question_bank_id: '', file: null });
    const submitImport = (event: FormEvent) => {
        event.preventDefault();
        importForm.post(`/professional-schools/${professionalSchool.id}/questions/import`, { onSuccess: () => importForm.reset('file') });
    };

    return (
        <PortalAppShell title="Questions">
            <Head title="Questions" />
            <PageHeader
                eyebrow={professionalSchool.name}
                title="Questions"
                description="Manage the approved and draft questions available to this professional school."
                actions={<Button asChild><Link href="/questions/create">Add Question</Link></Button>}
            />
            <form onSubmit={submitImport} className="mb-6">
                <FormSection
                    title="Import Questions"
                    description="Upload CSV questions into the selected professional question bank. The course and module are taken from the bank."
                    footer={<div className="flex flex-wrap gap-2"><Button asChild type="button" variant="secondary"><a href={`/professional-schools/${professionalSchool.id}/questions/template`}>Download Template</a></Button><Button disabled={importForm.processing || questionBanks.length === 0}>Import Questions</Button></div>}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Question Bank" error={importForm.errors.question_bank_id}>
                            <select required className={inputClass} value={importForm.data.question_bank_id} onChange={(event) => importForm.setData('question_bank_id', event.target.value)}>
                                <option value="">Choose question bank</option>
                                {questionBanks.map((bank) => <option key={bank.id} value={bank.id}>{bank.name} ({bank.code}){bank.course_name || bank.module_name ? ` - ${[bank.course_name, bank.module_name].filter(Boolean).join(' / ')}` : ''}</option>)}
                            </select>
                        </Field>
                        <Field label="CSV File" error={importForm.errors.file}>
                            <input required type="file" accept=".csv,text/csv" className={inputClass} onChange={(event) => importForm.setData('file', event.target.files?.[0] ?? null)} />
                        </Field>
                    </div>
                    {importSummary && (
                        <div className="mt-4 rounded-md border border-border bg-white p-3 text-sm text-slate-600">
                            Imported {importSummary.created ?? 0}. Failed {importSummary.failed?.length ?? 0}.
                        </div>
                    )}
                </FormSection>
            </form>
            <DataTable
                rows={questions}
                emptyTitle="No questions"
                columns={[
                    { key: 'stem', header: 'Question' },
                    { key: 'structure', header: 'Course / Module', render: (row: any) => [row.course_name, row.module_name].filter(Boolean).join(' / ') || 'N/A' },
                    { key: 'question_bank_name', header: 'Question Bank' },
                    { key: 'difficulty', header: 'Difficulty' },
                    { key: 'marks', header: 'Marks' },
                    { key: 'options_count', header: 'Options' },
                    { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'approved' ? 'success' : 'neutral'} /> },
                    { key: 'actions', header: 'Actions', render: (row: any) => <ActionDropdown items={[
                        { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/questions/${row.id}/edit`) },
                        { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => window.confirm('Delete this question?') && router.delete(`/questions/${row.id}`, { preserveScroll: true }) },
                    ]} /> },
                ]}
            />
        </PortalAppShell>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
