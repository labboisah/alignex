import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';
import { Pencil, Trash2, X } from 'lucide-react';
import { ActionDropdown, DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function InstitutionDepartments({ institution, faculties, departments }: { institution: any; faculties: any[]; departments: any[] }) {
    const [editing, setEditing] = useState<any | null>(null);
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({ faculty_id: '', name: '', code: '', status: 'active' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => cancelEdit() };

        if (editing) {
            patch(`/institutions/${institution.id}/departments/${editing.id}`, options);
            return;
        }

        post(`/institutions/${institution.id}/departments`, options);
    };

    const editDepartment = (department: any) => {
        setEditing(department);
        clearErrors();
        setData({
            faculty_id: department.faculty_id ? String(department.faculty_id) : '',
            name: department.name ?? '',
            code: department.code ?? '',
            status: department.status ?? 'active',
        });
    };

    const cancelEdit = () => {
        setEditing(null);
        clearErrors();
        reset();
    };

    const deleteDepartment = (department: any) => {
        if (window.confirm(`Delete ${department.name}? Linked programmes and courses will remain but lose this department link.`)) {
            router.delete(`/institutions/${institution.id}/departments/${department.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Departments">
            <Head title="Departments" />
            <PageHeader eyebrow={institution.name} title="Departments" description="Add school or faculty departments to the institution structure." />
            <form onSubmit={submit} className="mb-6">
                <FormSection
                    title={editing ? 'Edit Department' : 'New Department'}
                    description="Create or update academic departments aligned to a faculty."
                    footer={
                        <div className="flex gap-2">
                            {editing && <Button type="button" variant="secondary" onClick={cancelEdit}><X className="h-4 w-4" />Cancel</Button>}
                            <Button disabled={processing}>{editing ? 'Update Department' : 'Save Department'}</Button>
                        </div>
                    }
                >
                    <Grid>
                        <Field label="Faculty" error={errors.faculty_id}>
                            <select required className={inputClass} value={data.faculty_id} onChange={(event) => setData('faculty_id', event.target.value)}>
                                <option value="">Select faculty</option>
                                {faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Code" error={errors.code}><input required className={inputClass} value={data.code} onChange={(event) => setData('code', event.target.value)} /></Field>
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
                { key: 'programmes_count', header: 'Programmes' },
                { key: 'courses_count', header: 'Courses' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                {
                    key: 'actions',
                    header: 'Actions',
                    render: (row: any) => (
                        <ActionDropdown
                            items={[
                                { label: 'Edit', icon: Pencil, onSelect: () => editDepartment(row) },
                                { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => deleteDepartment(row) },
                            ]}
                        />
                    ),
                },
            ]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
