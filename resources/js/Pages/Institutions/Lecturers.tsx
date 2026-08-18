import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Course = { id: number | string; name: string; code?: string | null; programme?: { name: string } | null };
type Lecturer = {
    id: number | string;
    name: string;
    email: string;
    course_ids: Array<number | string>;
    courses: Course[];
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function Lecturers({ institution, department, courses, lecturers }: { institution: { id: number | string; name: string }; department: { id: number | string; name: string; code?: string | null }; courses: Course[]; lecturers: Lecturer[] }) {
    const path = `/institutions/${institution.id}/departments/${department.id}/lecturers`;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        course_ids: [] as string[],
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(path, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const edit = (lecturer: Lecturer) => {
        const name = window.prompt('Lecturer name', lecturer.name);
        if (!name) return;
        const email = window.prompt('Lecturer email', lecturer.email);
        if (!email) return;
        const password = window.prompt('New password. Leave blank to keep current password.', '');
        const courseIds = window.prompt(`Course IDs. Available: ${courses.map((course) => `${course.id}:${course.name}`).join(', ')}`, lecturer.course_ids.join(','));
        if (!courseIds) return;

        router.patch(`${path}/${lecturer.id}`, {
            name,
            email,
            password,
            course_ids: courseIds.split(',').map((id) => id.trim()).filter(Boolean),
        }, { preserveScroll: true });
    };

    const destroy = (lecturer: Lecturer) => {
        if (window.confirm(`Delete ${lecturer.name}?`)) {
            router.delete(`${path}/${lecturer.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Lecturers">
            <Head title="Lecturers" />
            <PageHeader eyebrow={`${institution.name} / ${department.name}`} title="Lecturers" description="Create lecturer logins and assign the department courses they can manage." />

            <form onSubmit={submit}>
                <FormSection title="Register Lecturer" description="Assigned courses control question bank, question, and assessment access.">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                        <Field label="Email" error={errors.email}><input required type="email" className={inputClass} value={data.email} onChange={(event) => setData('email', event.target.value)} /></Field>
                        <Field label="Password" error={errors.password}><input required type="password" className={inputClass} value={data.password} onChange={(event) => setData('password', event.target.value)} /></Field>
                        <Field label="Courses" error={errors.course_ids}>
                            <select
                                multiple
                                required
                                className={`${inputClass} min-h-32`}
                                value={data.course_ids}
                                onChange={(event) => setData('course_ids', Array.from(event.target.selectedOptions).map((option) => option.value))}
                            >
                                {courses.map((course) => <option key={course.id} value={course.id}>{course.name} ({course.code ?? 'No code'})</option>)}
                            </select>
                        </Field>
                    </div>
                    <div className="mt-4 flex justify-end">
                        <Button disabled={processing}>Create Lecturer</Button>
                    </div>
                </FormSection>
            </form>

            <div className="mt-6">
                <DataTable<Lecturer> rows={lecturers} emptyTitle="No lecturers" columns={[
                    { key: 'name', header: 'Name' },
                    { key: 'email', header: 'Email' },
                    { key: 'courses', header: 'Courses', render: (lecturer) => lecturer.courses.map((course) => `${course.name} (${course.code ?? 'No code'})`).join(', ') },
                    { key: 'actions', header: 'Actions', render: (lecturer) => <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => edit(lecturer)}>Edit</Button><Button size="sm" variant="danger" onClick={() => destroy(lecturer)}>Delete</Button></div> },
                ]} />
            </div>
        </PortalAppShell>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="block"><span className="text-sm font-semibold text-slateDark">{label}</span>{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
