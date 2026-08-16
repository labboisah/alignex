import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function InstitutionProgrammes({ institution, faculties, departments, programmes }: { institution: any; faculties: any[]; departments: any[]; programmes: any[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ faculty_id: '', department_id: '', name: '', code: '', duration: '', description: '', status: 'active' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/institutions/${institution.id}/programmes`, { onSuccess: () => reset() });
    };

    return (
        <PortalAppShell title="Programmes">
            <Head title="Programmes" />
            <PageHeader eyebrow={institution.name} title="Programmes" description="Create academic programmes and tracks for the institution." />
            <form onSubmit={submit} className="mb-6">
                <FormSection title="New Programme" description="Add a programme under a faculty or department." footer={<Button disabled={processing}>Save Programme</Button>}>
                    <Grid>
                        <Field label="Faculty" error={errors.faculty_id}>
                            <select className={inputClass} value={data.faculty_id} onChange={(event) => setData('faculty_id', event.target.value)}>
                                <option value="">Select faculty</option>
                                {faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Department" error={errors.department_id}>
                            <select className={inputClass} value={data.department_id} onChange={(event) => setData('department_id', event.target.value)}>
                                <option value="">Select department</option>
                                {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Code" error={errors.code}><input required className={inputClass} value={data.code} onChange={(event) => setData('code', event.target.value)} /></Field>
                        <Field label="Duration" error={errors.duration}><input className={inputClass} value={data.duration} onChange={(event) => setData('duration', event.target.value)} /></Field>
                        <Field label="Status" error={errors.status}>
                            <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </Field>
                    </Grid>
                    <Field label="Description" error={errors.description}><textarea rows={3} className={inputClass} value={data.description} onChange={(event) => setData('description', event.target.value)} /></Field>
                </FormSection>
            </form>

            <DataTable rows={programmes} emptyTitle="No programmes" columns={[
                { key: 'name', header: 'Name' },
                { key: 'code', header: 'Code' },
                { key: 'duration', header: 'Duration' },
                { key: 'faculty', header: 'Faculty', render: (row: any) => row.faculty?.name ?? 'N/A' },
                { key: 'department', header: 'Department', render: (row: any) => row.department?.name ?? 'N/A' },
                { key: 'courses_count', header: 'Courses' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
            ]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
