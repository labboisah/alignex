import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function Modules({ professionalSchool, programmes, courses, modules }: { professionalSchool: any; programmes: any[]; courses: any[]; modules: any[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ programme_id: '', course_id: '', name: '', code: '', description: '', status: 'active' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/professional-schools/${professionalSchool.id}/modules`, { onSuccess: () => reset() });
    };
    return (
        <PortalAppShell title="Modules">
            <Head title="Modules" />
            <PageHeader eyebrow={professionalSchool.name} title="Modules" description="Modules are the professional unit used for question banks and exams." />
            <form onSubmit={submit} className="mb-6">
                <FormSection title="New Module" description="Add a module under a course." footer={<Button disabled={processing}>Save Module</Button>}>
                    <Grid>
                        <Field label="Programme" error={errors.programme_id}><select className={inputClass} value={data.programme_id} onChange={(event) => setData('programme_id', event.target.value)}><option value="">Optional</option>{programmes.map((programme) => <option key={programme.id} value={programme.id}>{programme.name}</option>)}</select></Field>
                        <Field label="Course" error={errors.course_id}><select required className={inputClass} value={data.course_id} onChange={(event) => setData('course_id', event.target.value)}><option value="">Select course</option>{courses.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}</select></Field>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select></Field>
                    </Grid>
                    <Field label="Description" error={errors.description}><textarea rows={3} className={inputClass} value={data.description} onChange={(event) => setData('description', event.target.value)} /></Field>
                </FormSection>
            </form>
            <DataTable rows={modules} emptyTitle="No modules" columns={[{ key: 'name', header: 'Name' }, { key: 'programme', header: 'Programme', render: (row: any) => row.programme?.name ?? 'N/A' }, { key: 'course', header: 'Course', render: (row: any) => row.course?.name ?? 'N/A' }, { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> }]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
