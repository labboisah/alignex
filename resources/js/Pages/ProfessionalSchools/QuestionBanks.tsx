import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { ActionDropdown, DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function QuestionBanks({ professionalSchool, programmes, courses, modules, subjects, questionBanks }: { professionalSchool: any; programmes: any[]; courses: any[]; modules: any[]; subjects: any[]; questionBanks: any[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ programme_id: '', course_id: '', module_id: '', subject_id: '', name: '', code: '', description: '', status: 'draft' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/professional-schools/${professionalSchool.id}/question-banks`, { onSuccess: () => reset() });
    };
    return (
        <PortalAppShell title="Question Banks">
            <Head title="Question Banks" />
            <PageHeader eyebrow={professionalSchool.name} title="Question Banks" description="Create course and module question banks for professional and certification exams." />
            <form onSubmit={submit} className="mb-6">
                <FormSection title="New Question Bank" description="Attach a bank to the programme, course, and module it assesses." footer={<Button disabled={processing}>Save Question Bank</Button>}>
                    <Grid>
                        <Field label="Programme" error={errors.programme_id}><select required className={inputClass} value={data.programme_id} onChange={(event) => setData('programme_id', event.target.value)}><option value="">Select programme</option>{programmes.map((programme) => <option key={programme.id} value={programme.id}>{programme.name}</option>)}</select></Field>
                        <Field label="Course" error={errors.course_id}><select required className={inputClass} value={data.course_id} onChange={(event) => setData('course_id', event.target.value)}><option value="">Select course</option>{courses.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}</select></Field>
                        <Field label="Module" error={errors.module_id}><select required className={inputClass} value={data.module_id} onChange={(event) => setData('module_id', event.target.value)}><option value="">Select module</option>{modules.map((module) => <option key={module.id} value={module.id}>{module.name}</option>)}</select></Field>
                        <Field label="Subject" error={errors.subject_id}><select className={inputClass} value={data.subject_id} onChange={(event) => setData('subject_id', event.target.value)}><option value="">Auto from module</option>{subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}</select></Field>
                        <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></Field>
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                    </Grid>
                    <Field label="Description" error={errors.description}><textarea rows={3} className={inputClass} value={data.description} onChange={(event) => setData('description', event.target.value)} /></Field>
                </FormSection>
            </form>
            <DataTable rows={questionBanks} emptyTitle="No question banks" columns={[
                { key: 'name', header: 'Name' },
                { key: 'programme', header: 'Programme', render: (row: any) => row.programme?.name ?? 'N/A' },
                { key: 'course', header: 'Course', render: (row: any) => row.course?.name ?? 'N/A' },
                { key: 'module', header: 'Module', render: (row: any) => row.module?.name ?? 'N/A' },
                { key: 'questions_count', header: 'Questions' },
                { key: 'status', header: 'Status', render: (row: any) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'actions', header: 'Actions', render: (row: any) => <ActionDropdown items={[
                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/question-bank/${row.id}/edit`) },
                    { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => window.confirm('Delete this question bank?') && router.delete(`/question-bank/${row.id}`, { preserveScroll: true }) },
                ]} /> },
            ]} />
        </PortalAppShell>
    );
}

function Grid({ children }: { children: ReactNode }) { return <div className="grid gap-4 md:grid-cols-2">{children}</div>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>; }
