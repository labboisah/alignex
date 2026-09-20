import { Head, router, useForm } from '@inertiajs/react';
import { Edit, Eye, FileCheck2, Plus, Save, Trash2, X } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type ReadinessPackage = {
    id: number;
    code: string;
    version: string;
    capacity_profile: number;
    candidate_count: number;
    backup_percent: number;
    autoboot_target_clients: number;
    question_count: number;
    subject_count: number;
    status: string;
    checksum_sha256: string;
    created_by: string | null;
    created_at: string | null;
    subject_ids?: string[];
    question_bank_ids?: string[];
    paper_rows?: PaperRow[];
};

type Option = { id: string; name: string; code?: string | null };
type BankOption = Option & { subject_id: string; questions_count: number; subject?: { name: string } | null };
type PaperRow = { subject_id: string; question_bank_id: string; question_count: number };

type FormData = {
    code: string;
    version: string;
    capacity_profile: number;
    question_count: number;
    subject_count: number;
    status: string;
    candidate_count: number;
    paper_rows: PaperRow[];
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const blankForm: FormData = { code: '', version: '', capacity_profile: 250, candidate_count: 250, question_count: 0, subject_count: 0, status: 'draft', paper_rows: [{ subject_id: '', question_bank_id: '', question_count: 10 }] };

export default function OfflineReadinessPackagesIndex({ packages, capacities, statuses, subjects, questionBanks }: { packages: ReadinessPackage[]; capacities: number[]; statuses: string[]; subjects: Option[]; questionBanks: BankOption[] }) {
    const [editing, setEditing] = useState<ReadinessPackage | null>(null);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(blankForm);

    const beginCreate = () => { setEditing(null); clearErrors(); reset(); };
    const beginEdit = (item: ReadinessPackage) => {
        setEditing(item); clearErrors();
        setData({ code: item.code, version: item.version, capacity_profile: item.capacity_profile, candidate_count: item.candidate_count ?? item.capacity_profile, question_count: item.question_count, subject_count: item.paper_rows?.length ?? item.subject_ids?.length ?? 0, status: item.status, paper_rows: item.paper_rows ?? [] });
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const request = { ...data, question_count: data.paper_rows.reduce((total, row) => total + Number(row.question_count || 0), 0), subject_count: data.paper_rows.length, _method: editing ? 'patch' : undefined };
        if (editing) { router.post(`/offline-readiness-packages/${editing.id}`, request, { preserveScroll: true, onSuccess: beginCreate }); }
        else { router.post('/offline-readiness-packages', request, { preserveScroll: true, onSuccess: beginCreate }); }
    };
    const updateRow = (index: number, changes: Partial<PaperRow>) => {
        setData('paper_rows', data.paper_rows.map((row, rowIndex) => rowIndex === index ? { ...row, ...changes } : row));
    };

    return <PortalAppShell title="Readiness Packages">
        <Head title="Offline Readiness Packages" />
        <section className="mx-auto max-w-7xl">
                    <PageHeader eyebrow="Platform" title="Offline Readiness Packages" description="Manage the capacity-specific Autoboot packages imported by Center Servers." actions={<Button type="button" onClick={beginCreate}><Plus className="h-4 w-4" /> Create Package</Button>} />
            <div className="mb-6 rounded-md border border-border bg-white p-5 shadow-sm">
                <div className="mb-4 flex items-center justify-between gap-3"><div><h2 className="font-semibold text-slateDark">{editing ? `Edit ${editing.code}` : 'Create package'}</h2><p className="mt-1 text-sm text-slate-600">Only one active package is retained per capacity tier. The backup is fixed at 15%.</p></div>{editing && <Button type="button" variant="secondary" onClick={beginCreate}><X className="h-4 w-4" /> Cancel</Button>}</div>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 md:grid-cols-4">
                        <Field label="Package code" error={errors.code}><input className={inputClass} value={data.code} onChange={e => setData('code', e.target.value)} placeholder="alignex.readiness-pack.v1-250" required /></Field>
                        <Field label="Version" error={errors.version}><input className={inputClass} value={data.version} onChange={e => setData('version', e.target.value)} placeholder="1" required /></Field>
                        <Field label="Live capacity" error={errors.capacity_profile}><select className={inputClass} value={data.capacity_profile} onChange={e => { const capacity = Number(e.target.value); setData({ ...data, capacity_profile: capacity, candidate_count: capacity }); }}>{capacities.map(capacity => <option key={capacity} value={capacity}>{capacity} candidates</option>)}</select></Field>
                        <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={e => setData('status', e.target.value)}>{statuses.map(status => <option key={status} value={status}>{status}</option>)}</select></Field>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3"><Field label="Candidate count" error={errors.candidate_count}><input className={inputClass} type="number" min="1" value={data.candidate_count} onChange={e => setData('candidate_count', Number(e.target.value))} required /></Field><Field label="Total questions" error={errors.question_count}><input className={inputClass} type="number" min="1" value={data.paper_rows.reduce((total, row) => total + Number(row.question_count || 0), 0)} readOnly /></Field><Field label="Subject rows" error={errors.subject_count}><input className={inputClass} type="number" min="1" value={data.paper_rows.length} readOnly /></Field></div>
                    <div className="space-y-3"><div className="flex items-center justify-between"><h3 className="font-semibold text-slateDark">Question paper setup</h3><Button type="button" variant="secondary" onClick={() => setData('paper_rows', [...data.paper_rows, { subject_id: '', question_bank_id: '', question_count: 10 }])}><Plus className="h-4 w-4" /> Add Subject</Button></div>{data.paper_rows.map((row, index) => <div key={index} className="grid gap-3 rounded-md border border-border bg-slate-50 p-4 md:grid-cols-[1fr_1fr_160px_auto]"><Field label={`Subject ${index + 1}`} error={errors[`paper_rows.${index}.subject_id`]}><select className={inputClass} value={row.subject_id} onChange={event => updateRow(index, { subject_id: event.target.value, question_bank_id: '' })}><option value="">Select subject</option>{subjects.map(subject => <option key={subject.id} value={subject.id}>{subject.name}{subject.code ? ` (${subject.code})` : ''}</option>)}</select></Field><Field label="Question bank" error={errors[`paper_rows.${index}.question_bank_id`]}><select className={inputClass} value={row.question_bank_id} onChange={event => updateRow(index, { question_bank_id: event.target.value })}><option value="">Select bank</option>{questionBanks.filter(bank => !row.subject_id || String(bank.subject_id) === String(row.subject_id)).map(bank => <option key={bank.id} value={bank.id}>{bank.name} ({bank.questions_count})</option>)}</select></Field><Field label="Questions" error={errors[`paper_rows.${index}.question_count`]}><input className={inputClass} type="number" min="1" value={row.question_count} onChange={event => updateRow(index, { question_count: Number(event.target.value) })} /></Field><Button type="button" variant="danger" className="self-end" disabled={data.paper_rows.length === 1} onClick={() => setData('paper_rows', data.paper_rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 className="h-4 w-4" /> Remove</Button></div>)}</div>
                    <p className="text-sm text-slate-600">Saving the package generates a read-only question paper from the selected subjects and question banks. Correct answers are never included in the client manifest.</p>
                    <div className="flex justify-end"><Button type="submit" disabled={processing}><Save className="h-4 w-4" /> {editing ? 'Regenerate Paper' : 'Generate Paper'}</Button></div>
                </form>
            </div>
            <div className="overflow-x-auto rounded-md border border-border bg-white shadow-sm"><table className="min-w-full divide-y divide-border text-sm"><thead className="bg-slate-50"><tr>{['Package', 'Capacity', 'Target', 'Questions', 'Status', 'Checksum', 'Actions'].map(header => <th key={header} className="px-4 py-3 text-left font-semibold text-slate-600">{header}</th>)}</tr></thead><tbody className="divide-y divide-border">{packages.map(item => <tr key={item.id}><td className="px-4 py-3"><div className="font-semibold text-slateDark">{item.code}</div><div className="text-xs text-slate-500">v{item.version}</div></td><td className="px-4 py-3">{item.capacity_profile}</td><td className="px-4 py-3">{item.autoboot_target_clients} <span className="text-xs text-slate-500">(+15%)</span></td><td className="px-4 py-3">{item.question_count}</td><td className="px-4 py-3"><StatusBadge label={item.status} tone={item.status === 'active' ? 'success' : item.status === 'retired' ? 'neutral' : 'warning'} /></td><td className="px-4 py-3"><code className="text-xs">{item.checksum_sha256.slice(0, 12)}...</code></td><td className="px-4 py-3"><div className="flex flex-wrap gap-2"><Button asChild type="button" variant="secondary" className="h-8 px-2"><a href={`/offline-readiness-packages/${item.id}/paper`}><Eye className="h-4 w-4" /> View Paper</a></Button><Button type="button" variant="secondary" className="h-8 px-2" disabled={item.status === 'active'} title={item.status === 'active' ? 'Active packages are immutable. Retire it before regeneration.' : 'Generate a new paper from the saved rows.'} onClick={() => window.confirm('Regenerate this question paper?') && router.post(`/offline-readiness-packages/${item.id}/generate-paper`, {}, { preserveScroll: true })}><FileCheck2 className="h-4 w-4" /> Generate Paper</Button><Button type="button" variant="secondary" className="h-8 px-2" onClick={() => beginEdit(item)}><Edit className="h-4 w-4" /> Edit</Button><Button type="button" variant="danger" className="h-8 px-2" disabled={item.status === 'active'} onClick={() => window.confirm('Delete this package?') && router.delete(`/offline-readiness-packages/${item.id}`, { preserveScroll: true })}><Trash2 className="h-4 w-4" /> Delete</Button></div></td></tr>)}</tbody></table>{packages.length === 0 && <p className="p-6 text-sm text-slate-600">No readiness packages have been created.</p>}</div>
        </section>
    </PortalAppShell>;
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
