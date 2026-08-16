import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function InstitutionFaculties({ institution, faculties }: { institution: any; faculties: any[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', dean_name: '', email: '', phone: '', status: 'active' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/institutions/${institution.id}/faculties`, { onSuccess: () => reset() });
    };

    return (
        <PortalAppShell title="Faculties">
            <Head title="Faculties" />
            <PageHeader eyebrow={institution.name} title="Faculties" description="Create and manage academic faculties in the institution." />
            <form onSubmit={submit} className="mb-6">
                <FormSection title="New Faculty" description="Add a faculty for a group of departments and programmes." footer={<Button disabled={processing}>Save Faculty</Button>}>
                    <Grid>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Code" error={errors.code}><input required className={inputClass} value={data.code} onChange={(event) => setData('code', event.target.value)} /></Field>
                        <Field label="Dean / Head" error={errors.dean_name}><input className={inputClass} value={data.dean_name} onChange={(event) => setData('dean_name', event.target.value)} /></Field>
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

            <DataTable rows={faculties} emptyTitle="No faculties" columns={[
                { key: 'name', header: 'Name' },
                { key: 'code', header: 'Code' },
                { key: 'dean_name', header: 'Dean' },
                { key: 'email', header: 'Email' },
                { key: 'departments_count', header: 'Departments' },
                { key: 'programmes_count', header: 'Programmes' },
                { key: 'courses_count', header: 'Courses' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
            ]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
