import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function InstitutionDepartments({ institution, faculties, departments }: { institution: any; faculties: any[]; departments: any[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ faculty_id: '', name: '', code: '', head_name: '', email: '', phone: '', status: 'active' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/institutions/${institution.id}/departments`, { onSuccess: () => reset() });
    };

    return (
        <PortalAppShell title="Departments">
            <Head title="Departments" />
            <PageHeader eyebrow={institution.name} title="Departments" description="Add school or faculty departments to the institution structure." />
            <form onSubmit={submit} className="mb-6">
                <FormSection title="New Department" description="Create academic departments aligned to a faculty." footer={<Button disabled={processing}>Save Department</Button>}>
                    <Grid>
                        <Field label="Faculty" error={errors.faculty_id}>
                            <select required className={inputClass} value={data.faculty_id} onChange={(event) => setData('faculty_id', event.target.value)}>
                                <option value="">Select faculty</option>
                                {faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Code" error={errors.code}><input required className={inputClass} value={data.code} onChange={(event) => setData('code', event.target.value)} /></Field>
                        <Field label="Head / HOD" error={errors.head_name}><input className={inputClass} value={data.head_name} onChange={(event) => setData('head_name', event.target.value)} /></Field>
                        <Field label="Email" error={errors.email}><input type="email" className={inputClass} value={data.email} onChange={(event) => setData('email', event.target.value)} /></Field>
                        <Field label="Phone" error={errors.phone}><input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} /></Field>
                        <Field label="Status" error={errors.status}>
                            <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </Field>
                    </Grid>
                </FormSection>
            </form>

            <DataTable rows={departments} emptyTitle="No departments" columns={[
                { key: 'name', header: 'Name' },
                { key: 'faculty', header: 'Faculty', render: (row: any) => row.faculty?.name ?? 'N/A' },
                { key: 'code', header: 'Code' },
                { key: 'head_name', header: 'HOD' },
                { key: 'email', header: 'Email' },
                { key: 'programmes_count', header: 'Programmes' },
                { key: 'courses_count', header: 'Courses' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
            ]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
