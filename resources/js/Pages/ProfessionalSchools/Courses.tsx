import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function Courses({ professionalSchool, programmes, courses, canManageStructure = true }: { professionalSchool: any; programmes: any[]; courses: any[]; canManageStructure?: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({ programme_id: '', name: '', code: '', description: '', status: 'active' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/professional-schools/${professionalSchool.id}/courses`, { onSuccess: () => reset() });
    };
    return (
        <PortalAppShell title="Courses">
            <Head title="Courses" />
            <PageHeader eyebrow={professionalSchool.name} title="Courses" description="Courses sit under programmes and contain professional modules." />
            {canManageStructure && (
                <form onSubmit={submit} className="mb-6">
                    <FormSection title="New Course" description="Add a course under a professional programme." footer={<Button disabled={processing}>Save Course</Button>}>
                        <Grid>
                            <Field label="Programme" error={errors.programme_id}><select required className={inputClass} value={data.programme_id} onChange={(event) => setData('programme_id', event.target.value)}><option value="">Select programme</option>{programmes.map((programme) => <option key={programme.id} value={programme.id}>{programme.name}</option>)}</select></Field>
                            <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select></Field>
                            <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        </Grid>
                        <Field label="Description" error={errors.description}><textarea rows={3} className={inputClass} value={data.description} onChange={(event) => setData('description', event.target.value)} /></Field>
                    </FormSection>
                </form>
            )}
            <DataTable rows={courses} emptyTitle="No courses" columns={[{ key: 'name', header: 'Name' }, { key: 'programme', header: 'Programme', render: (row: any) => row.programme?.name ?? 'N/A' }, { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> }]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
