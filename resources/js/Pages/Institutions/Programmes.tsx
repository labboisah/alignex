import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';
import { Pencil, Trash2, X } from 'lucide-react';
import { ActionDropdown, DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function InstitutionProgrammes({ institution, faculties, departments, programmes }: { institution: any; faculties: any[]; departments: any[]; programmes: any[] }) {
    const [editing, setEditing] = useState<any | null>(null);
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({ faculty_id: '', department_id: '', name: '', code: '', duration: '', status: 'active' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => cancelEdit() };

        if (editing) {
            patch(`/institutions/${institution.id}/programmes/${editing.id}`, options);
            return;
        }

        post(`/institutions/${institution.id}/programmes`, options);
    };

    const editProgramme = (programme: any) => {
        setEditing(programme);
        clearErrors();
        setData({
            faculty_id: programme.faculty_id ? String(programme.faculty_id) : '',
            department_id: programme.department_id ? String(programme.department_id) : '',
            name: programme.name ?? '',
            code: programme.code ?? '',
            duration: programme.duration ?? '',
            status: programme.status ?? 'active',
        });
    };

    const cancelEdit = () => {
        setEditing(null);
        clearErrors();
        reset();
    };

    const deleteProgramme = (programme: any) => {
        if (window.confirm(`Delete ${programme.name}? Linked courses will remain but lose this programme link.`)) {
            router.delete(`/institutions/${institution.id}/programmes/${programme.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Programmes">
            <Head title="Programmes" />
            <PageHeader eyebrow={institution.name} title="Programmes" description="Create academic programmes and tracks for the institution." />
            <form onSubmit={submit} className="mb-6">
                <FormSection
                    title={editing ? 'Edit Programme' : 'New Programme'}
                    description="Add or update a programme under a faculty or department."
                    footer={
                        <div className="flex gap-2">
                            {editing && <Button type="button" variant="secondary" onClick={cancelEdit}><X className="h-4 w-4" />Cancel</Button>}
                            <Button disabled={processing}>{editing ? 'Update Programme' : 'Save Programme'}</Button>
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
                        <Field label="Department" error={errors.department_id}>
                            <select required className={inputClass} value={data.department_id} onChange={(event) => setData('department_id', event.target.value)}>
                                <option value="">Select department</option>
                                {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Code" error={errors.code}><input required className={inputClass} value={data.code} onChange={(event) => setData('code', event.target.value)} /></Field>
                        <Field label="Duration (months)" error={errors.duration}><input required type="number" min="1" max="600" className={inputClass} value={data.duration} onChange={(event) => setData('duration', event.target.value)} /></Field>
                        <Field label="Status" error={errors.status}>
                            <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </Field>
                    </Grid>
                </FormSection>
            </form>

            <DataTable rows={programmes} emptyTitle="No programmes" columns={[
                { key: 'name', header: 'Name' },
                { key: 'code', header: 'Code' },
                { key: 'duration', header: 'Duration', render: (row: any) => row.duration ? `${row.duration} months` : 'N/A' },
                { key: 'faculty', header: 'Faculty', render: (row: any) => row.faculty?.name ?? 'N/A' },
                { key: 'department', header: 'Department', render: (row: any) => row.department?.name ?? 'N/A' },
                { key: 'courses_count', header: 'Courses' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                {
                    key: 'actions',
                    header: 'Actions',
                    render: (row: any) => (
                        <ActionDropdown
                            items={[
                                { label: 'Edit', icon: Pencil, onSelect: () => editProgramme(row) },
                                { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => deleteProgramme(row) },
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
