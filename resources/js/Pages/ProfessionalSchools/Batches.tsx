import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2, X } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function Batches({ professionalSchool, programmes, batches }: { professionalSchool: any; programmes: any[]; batches: any[] }) {
    const [editing, setEditing] = useState<any | null>(null);
    const { data, setData, post, patch, processing, errors, reset } = useForm({ programme_id: '', name: '', start_date: '', end_date: '', status: 'active' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        editing
            ? patch(`/professional-schools/${professionalSchool.id}/training-batches/${editing.id}`, { preserveScroll: true, onSuccess: clearEdit })
            : post(`/professional-schools/${professionalSchool.id}/training-batches`, { onSuccess: () => reset() });
    };
    const edit = (batch: any) => {
        setEditing(batch);
        setData({
            programme_id: String(batch.programme_id ?? batch.programme?.id ?? ''),
            name: batch.name ?? '',
            start_date: String(batch.starts_on ?? ''),
            end_date: String(batch.ends_on ?? ''),
            status: batch.status ?? 'active',
        });
    };
    const clearEdit = () => {
        setEditing(null);
        reset();
    };
    return (
        <PortalAppShell title="Training Batches">
            <Head title="Training Batches" />
            <PageHeader eyebrow={professionalSchool.name} title="Training Batches" description="Group candidates by professional programme intake or cohort." />
            <form onSubmit={submit} className="mb-6">
                <FormSection title={editing ? 'Edit Training Batch' : 'New Training Batch'} description="Create an intake for candidate registration and reporting." footer={<div className="flex justify-end gap-2">{editing && <Button type="button" variant="secondary" onClick={clearEdit}><X className="h-4 w-4" />Cancel</Button>}<Button disabled={processing}>Save Batch</Button></div>}>
                    <Grid>
                        <Field label="Programme" error={errors.programme_id}><select required className={inputClass} value={data.programme_id} onChange={(event) => setData('programme_id', event.target.value)}><option value="">Select programme</option>{programmes.map((programme) => <option key={programme.id} value={programme.id}>{programme.name}</option>)}</select></Field>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Start Date" error={errors.start_date}><input type="date" className={inputClass} value={data.start_date} onChange={(event) => setData('start_date', event.target.value)} /></Field>
                        <Field label="End Date" error={errors.end_date}><input type="date" className={inputClass} value={data.end_date} onChange={(event) => setData('end_date', event.target.value)} /></Field>
                        <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select></Field>
                    </Grid>
                </FormSection>
            </form>
            <DataTable rows={batches} emptyTitle="No training batches" columns={[
                { key: 'name', header: 'Name' },
                { key: 'programme', header: 'Programme', render: (row: any) => row.programme?.name ?? 'N/A' },
                { key: 'starts_on', header: 'Starts' },
                { key: 'ends_on', header: 'Ends' },
                { key: 'candidates_count', header: 'Candidates' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'actions', header: 'Actions', render: (row: any) => <div className="flex flex-wrap gap-2"><Button type="button" size="sm" variant="secondary" onClick={() => edit(row)}><Pencil className="h-4 w-4" />Edit</Button><Button type="button" size="sm" variant="danger" disabled={Number(row.candidates_count ?? 0) > 0} onClick={() => window.confirm('Delete this training batch?') && router.delete(`/professional-schools/${professionalSchool.id}/training-batches/${row.id}`, { preserveScroll: true })}><Trash2 className="h-4 w-4" />Delete</Button></div> },
            ]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
