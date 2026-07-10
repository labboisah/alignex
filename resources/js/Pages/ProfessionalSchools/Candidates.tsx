import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { ActionDropdown, DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function Candidates({ professionalSchool, programmes, courses, batches, candidates, importSummary }: { professionalSchool: any; programmes: any[]; courses: any[]; batches: any[]; candidates: any[]; importSummary?: any }) {
    const { data, setData, post, processing, errors, reset } = useForm({ training_batch_id: '', registration_number: '', full_name: '', email: '', phone: '', status: 'active' });
    const importForm = useForm<{ file: File | null; training_batch_id: string }>({ file: null, training_batch_id: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/professional-schools/${professionalSchool.id}/candidates`, { onSuccess: () => reset() });
    };
    const submitImport = (event: FormEvent) => {
        event.preventDefault();
        importForm.post(`/professional-schools/${professionalSchool.id}/candidates/import`, { forceFormData: true, onSuccess: () => importForm.reset('file') });
    };
    return (
        <PortalAppShell title="Candidates">
            <Head title="Candidates" />
            <PageHeader eyebrow={professionalSchool.name} title="Candidates / Trainees" description="Register professional candidates for training, practice, and certification workflows." />
            <form onSubmit={submitImport} className="mb-6">
                <FormSection
                    title="Import Candidates"
                    description="Choose the training batch, then upload a CSV with candidate names and registration numbers."
                    footer={<div className="flex flex-wrap gap-2"><Button asChild type="button" variant="secondary"><a href={`/professional-schools/${professionalSchool.id}/candidates/template`}>Download Template</a></Button><Button disabled={importForm.processing || !importForm.data.file || !importForm.data.training_batch_id}>Import Candidates</Button></div>}
                >
                    <Grid>
                        <Field label="Training Batch" error={importForm.errors.training_batch_id}><select required className={inputClass} value={importForm.data.training_batch_id} onChange={(event) => importForm.setData('training_batch_id', event.target.value)}><option value="">Select batch</option>{batches.map((batch) => <option key={batch.id} value={batch.id}>{batch.name}</option>)}</select></Field>
                        <Field label="CSV File" error={importForm.errors.file}>
                            <input required type="file" accept=".csv,text/csv" className={inputClass} onChange={(event) => importForm.setData('file', event.target.files?.[0] ?? null)} />
                        </Field>
                    </Grid>
                    {importSummary && (
                        <div className="mt-4 rounded-md border border-border bg-white p-3 text-sm text-slate-600">
                            Imported {importSummary.created ?? 0}. Skipped {importSummary.skipped ?? 0} duplicates. Failed {importSummary.failed?.length ?? 0}.
                        </div>
                    )}
                </FormSection>
            </form>
            <form onSubmit={submit} className="mb-6">
                <FormSection title="New Candidate" description="Add a trainee or professional certification candidate." footer={<Button disabled={processing}>Register Candidate</Button>}>
                    <Grid>
                        <Field label="Training Batch" error={errors.training_batch_id}><select className={inputClass} value={data.training_batch_id} onChange={(event) => setData('training_batch_id', event.target.value)}><option value="">Optional</option>{batches.map((batch) => <option key={batch.id} value={batch.id}>{batch.name}</option>)}</select></Field>
                        <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></Field>
                        <Field label="Registration No." error={errors.registration_number}><input required className={inputClass} value={data.registration_number} onChange={(event) => setData('registration_number', event.target.value)} /></Field>
                        <Field label="Full Name" error={errors.full_name}><input required className={inputClass} value={data.full_name} onChange={(event) => setData('full_name', event.target.value)} /></Field>
                        <Field label="Email" error={errors.email}><input type="email" className={inputClass} value={data.email} onChange={(event) => setData('email', event.target.value)} /></Field>
                        <Field label="Phone" error={errors.phone}><input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} /></Field>
                    </Grid>
                </FormSection>
            </form>
            <DataTable rows={candidates} emptyTitle="No candidates" columns={[
                { key: 'registration_number', header: 'Reg. No.' },
                { key: 'full_name', header: 'Name' },
                { key: 'programme_name', header: 'Programme' },
                { key: 'course_name', header: 'Course' },
                { key: 'batch_name', header: 'Batch' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'actions', header: 'Actions', render: (row: any) => <ActionDropdown items={[
                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/candidates/${row.id}/edit`) },
                    { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => window.confirm('Delete this candidate?') && router.delete(`/candidates/${row.id}`, { preserveScroll: true }) },
                ]} /> },
            ]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
