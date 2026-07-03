import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, Pencil, Trash2, Upload } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { ActionDropdown, DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { CandidateRow, CbtCenter } from './types';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

type CandidateGroupOption = {
    id: string;
    name: string;
    code?: string | null;
};

export default function Candidates({ center, candidates, candidateGroups = [] }: { center: CbtCenter; candidates: CandidateRow[]; candidateGroups?: CandidateGroupOption[] }) {
    const page = usePage<{ flash?: { import_summary?: { created: any[]; failed: any[]; duplicates: any[] } } }>();
    const summary = page.props.flash?.import_summary;
    const { data, setData, post, processing, errors, reset } = useForm({ registration_number: '', full_name: '', email: '', phone: '', nin: '', status: 'active', candidate_group_id: '' });
    const upload = useForm<{ file: File | null; candidate_group_id: string }>({ file: null, candidate_group_id: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/cbt-centers/${center.id}/candidates`, { onSuccess: () => reset() });
    };

    const importCsv = (event: FormEvent) => {
        event.preventDefault();
        upload.post(`/cbt-centers/${center.id}/candidates/import`, { forceFormData: true, preserveScroll: true, onSuccess: () => upload.reset() });
    };

    return (
        <PortalAppShell title={`${center.name} Candidates`}>
            <Head title={`${center.name} Candidates`} />
            <PageHeader eyebrow={center.name} title="Candidates" description="Register and import CBT center candidates without academic classes, programmes, or training batches." />
            <div className="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
                <form onSubmit={submit}>
                    <FormSection title="New Candidate" description="Add one candidate to this CBT center." footer={<Button disabled={processing}>Register Candidate</Button>}>
                        <Grid>
                            <Field label="Registration Number" error={errors.registration_number}><input required className={inputClass} value={data.registration_number} onChange={(event) => setData('registration_number', event.target.value.toUpperCase())} /></Field>
                            <Field label="Full Name" error={errors.full_name}><input required className={inputClass} value={data.full_name} onChange={(event) => setData('full_name', event.target.value)} /></Field>
                            <Field label="Email" error={errors.email}><input type="email" className={inputClass} value={data.email} onChange={(event) => setData('email', event.target.value)} /></Field>
                            <Field label="Phone" error={errors.phone}><input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} /></Field>
                            <Field label="NIN" error={errors.nin}><input className={inputClass} value={data.nin} onChange={(event) => setData('nin', event.target.value)} /></Field>
                            <Field label="Candidate Group" error={errors.candidate_group_id}>
                                <select required className={inputClass} value={data.candidate_group_id} onChange={(event) => setData('candidate_group_id', event.target.value)}>
                                    <option value="">Choose group</option>
                                    {candidateGroups.map((group) => <option key={group.id} value={group.id}>{group.name}{group.code ? ` (${group.code})` : ''}</option>)}
                                </select>
                            </Field>
                            <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></Field>
                        </Grid>
                    </FormSection>
                </form>
                <form onSubmit={importCsv}>
                    <FormSection title="Import Candidates" description="Upload a CSV into the selected candidate group." footer={<div className="flex flex-wrap justify-end gap-2"><Button asChild type="button" variant="secondary"><a href={`/cbt-centers/${center.id}/candidates/template`}><Download className="h-4 w-4" />Template</a></Button><Button disabled={upload.processing || !upload.data.file || !upload.data.candidate_group_id}><Upload className="h-4 w-4" />Import CSV</Button></div>}>
                        <Field label="Candidate Group" error={upload.errors.candidate_group_id}>
                            <select required className={inputClass} value={upload.data.candidate_group_id} onChange={(event) => upload.setData('candidate_group_id', event.target.value)}>
                                <option value="">Choose group</option>
                                {candidateGroups.map((group) => <option key={group.id} value={group.id}>{group.name}{group.code ? ` (${group.code})` : ''}</option>)}
                            </select>
                        </Field>
                        <input type="file" accept=".csv,text/csv" onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)} className={inputClass} />
                        {upload.errors.file && <div className="mt-2 text-sm text-danger">{upload.errors.file}</div>}
                        {summary && (
                            <div className="mt-4 grid gap-3 text-sm md:grid-cols-3">
                                <Report title="Imported" rows={summary.created?.map((row) => `Row ${row.row}: ${row.registration_number}`) ?? []} />
                                <Report title="Duplicates" rows={summary.duplicates?.map((row) => `Row ${row.row}: ${row.registration_number}`) ?? []} />
                                <Report title="Failed" rows={summary.failed?.map((row) => `Row ${row.row}: ${row.reason}`) ?? []} />
                            </div>
                        )}
                    </FormSection>
                </form>
            </div>
            <div className="mt-6">
                <DataTable rows={candidates} emptyTitle="No candidates" columns={[
                    { key: 'registration_number', header: 'Registration' },
                    { key: 'full_name', header: 'Name' },
                    { key: 'email', header: 'Email' },
                    { key: 'phone', header: 'Phone' },
                    { key: 'nin', header: 'NIN' },
                    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                    { key: 'actions', header: 'Actions', render: (row) => <ActionDropdown items={[
                        { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/candidates/${row.id}/edit`) },
                        { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => window.confirm('Delete this candidate?') && router.delete(`/candidates/${row.id}`, { preserveScroll: true }) },
                    ]} /> },
                ]} />
            </div>
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
function Report({ title, rows }: { title: string; rows: string[] }) { return <div className="rounded-md border border-border bg-white p-3"><div className="font-semibold text-slateDark">{title}</div><div className="mt-2 space-y-1 text-xs text-slate-500">{rows.length ? rows.slice(0, 5).map((row) => <div key={row}>{row}</div>) : <div>None</div>}</div></div>; }
