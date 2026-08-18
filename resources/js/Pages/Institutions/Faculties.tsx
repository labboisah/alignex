import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';
import { Pencil, Trash2, X } from 'lucide-react';
import { ActionDropdown, DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function InstitutionFaculties({ institution, faculties }: { institution: any; faculties: any[] }) {
    const [editing, setEditing] = useState<any | null>(null);
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({ name: '', code: '', status: 'active' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => cancelEdit() };

        if (editing) {
            patch(`/institutions/${institution.id}/faculties/${editing.id}`, options);
            return;
        }

        post(`/institutions/${institution.id}/faculties`, options);
    };

    const editFaculty = (faculty: any) => {
        setEditing(faculty);
        clearErrors();
        setData({
            name: faculty.name ?? '',
            code: faculty.code ?? '',
            status: faculty.status ?? 'active',
        });
    };

    const cancelEdit = () => {
        setEditing(null);
        clearErrors();
        reset();
    };

    const deleteFaculty = (faculty: any) => {
        if (window.confirm(`Delete ${faculty.name}? Linked departments, programmes, and courses will remain but lose this faculty link.`)) {
            router.delete(`/institutions/${institution.id}/faculties/${faculty.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Faculties">
            <Head title="Faculties" />
            <PageHeader eyebrow={institution.name} title="Faculties" description="Create and manage academic faculties in the institution." />
            <form onSubmit={submit} className="mb-6">
                <FormSection
                    title={editing ? 'Edit Faculty' : 'New Faculty'}
                    description="Add or update a faculty for a group of departments and programmes."
                    footer={
                        <div className="flex gap-2">
                            {editing && <Button type="button" variant="secondary" onClick={cancelEdit}><X className="h-4 w-4" />Cancel</Button>}
                            <Button disabled={processing}>{editing ? 'Update Faculty' : 'Save Faculty'}</Button>
                        </div>
                    }
                >
                    <Grid>
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

            <DataTable rows={faculties} emptyTitle="No faculties" columns={[
                { key: 'name', header: 'Name' },
                { key: 'code', header: 'Code' },
                { key: 'departments_count', header: 'Departments' },
                { key: 'programmes_count', header: 'Programmes' },
                { key: 'courses_count', header: 'Courses' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                {
                    key: 'actions',
                    header: 'Actions',
                    render: (row: any) => (
                        <ActionDropdown
                            items={[
                                { label: 'Edit', icon: Pencil, onSelect: () => editFaculty(row) },
                                { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => deleteFaculty(row) },
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
