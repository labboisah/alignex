import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function Programmes({ professionalSchool, programmes }: { professionalSchool: any; programmes: any[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', duration: '', description: '', status: 'active' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/professional-schools/${professionalSchool.id}/programmes`, { onSuccess: () => reset() });
    };
    return (
        <PortalAppShell title="Programmes">
            <Head title="Programmes" />
            <PageHeader eyebrow={professionalSchool.name} title="Programmes" description="Professional programmes for courses, batches, candidates, and exams." />
            <form onSubmit={submit} className="mb-6">
                <FormSection title="New Programme" description="Add a skills, diploma, bootcamp, vocational, or certification programme." footer={<Button disabled={processing}>Save Programme</Button>}>
                    <Grid>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Duration" error={errors.duration}><input className={inputClass} value={data.duration} onChange={(event) => setData('duration', event.target.value)} /></Field>
                        <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select></Field>
                    </Grid>
                    <Field label="Description" error={errors.description}><textarea rows={3} className={inputClass} value={data.description} onChange={(event) => setData('description', event.target.value)} /></Field>
                </FormSection>
            </form>
            <DataTable rows={programmes} emptyTitle="No programmes" columns={[{ key: 'name', header: 'Name' }, { key: 'duration', header: 'Duration' }, { key: 'courses_count', header: 'Courses' }, { key: 'candidates_count', header: 'Candidates' }, { key: 'exams_count', header: 'Exams' }, { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> }]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
