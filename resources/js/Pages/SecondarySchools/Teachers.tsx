import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { DataTable, PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Subject = { id: string; name: string; code: string };
type SchoolClass = { id: string; name: string; level?: string | null };
type Teacher = {
    id: number;
    name: string;
    email: string;
    school_class_id?: string | null;
    subject_ids: string[];
    subjects: Array<Subject & { school_class_id?: string | null; class_name?: string | null }>;
};

type TeacherForm = {
    name: string;
    email: string;
    password: string;
    school_class_id: string;
    subject_ids: string[];
};

export default function Teachers({ secondarySchool, classes, subjects, teachers, basePath }: { secondarySchool: { id: number; name: string }; classes: SchoolClass[]; subjects: Array<Subject & { school_class_id?: string | null }>; teachers: Teacher[]; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchool.id}/teachers`;
    const form = useForm<TeacherForm>({ name: '', email: '', password: '', school_class_id: '', subject_ids: [] });
    const filteredSubjects = subjects.filter((subject) => subject.school_class_id === form.data.school_class_id);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(path, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const edit = (teacher: Teacher) => {
        const name = window.prompt('Teacher name', teacher.name);
        if (name === null) return;
        const email = window.prompt('Teacher email', teacher.email);
        if (email === null) return;
        const password = window.prompt('New password (leave blank to keep current password)', '');
        if (password === null) return;
        const classList = classes.map((schoolClass) => `${schoolClass.name}:${schoolClass.id}`).join(', ');
        const schoolClassId = window.prompt(`Class ID. Available: ${classList}`, teacher.school_class_id ?? '');
        if (schoolClassId === null) return;
        const classSubjects = subjects.filter((subject) => subject.school_class_id === schoolClassId);
        const subjectCodes = classSubjects.map((subject) => `${subject.code}:${subject.id}`).join(', ');
        const current = teacher.subject_ids.join(',');
        const subjectIds = window.prompt(`Subject IDs separated by commas. Available: ${subjectCodes}`, current);
        if (subjectIds === null) return;

        router.patch(`${path}/${teacher.id}`, {
            name,
            email,
            password,
            school_class_id: schoolClassId,
            subject_ids: subjectIds.split(',').map((value) => value.trim()).filter(Boolean),
        }, { preserveScroll: true });
    };

    const destroy = (teacher: Teacher) => {
        if (window.confirm(`Delete ${teacher.name}?`)) {
            router.delete(`${path}/${teacher.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Teachers">
            <Head title="Teachers" />
            <PageHeader eyebrow={secondarySchool.name} title="Teachers" description="Create school teacher logins and assign the subjects they can manage." />

            <form onSubmit={submit} className="mb-6 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm lg:grid-cols-[1fr_1fr_1fr_1fr_2fr_auto]">
                <Input label="Name" value={form.data.name} error={form.errors.name} onChange={(value) => form.setData('name', value)} />
                <Input label="Email" type="email" value={form.data.email} error={form.errors.email} onChange={(value) => form.setData('email', value)} />
                <Input label="Password" type="password" value={form.data.password} error={form.errors.password} onChange={(value) => form.setData('password', value)} />
                <label className="block text-sm font-semibold text-slateDark">
                    Class
                    <select
                        className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary"
                        value={form.data.school_class_id}
                        onChange={(event) => form.setData({ ...form.data, school_class_id: event.target.value, subject_ids: [] })}
                    >
                        <option value="">Select class</option>
                        {classes.map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>)}
                    </select>
                    {form.errors.school_class_id && <span className="text-danger">{form.errors.school_class_id}</span>}
                </label>
                <label className="block text-sm font-semibold text-slateDark">
                    Assigned Subjects
                    <select
                        multiple
                        className="mt-1 block min-h-24 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary"
                        value={form.data.subject_ids}
                        disabled={!form.data.school_class_id}
                        onChange={(event) => form.setData('subject_ids', Array.from(event.target.selectedOptions, (option) => option.value))}
                    >
                        {filteredSubjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name} ({subject.code})</option>)}
                    </select>
                    {form.errors.subject_ids && <span className="text-danger">{form.errors.subject_ids}</span>}
                </label>
                <div className="flex items-end">
                    <Button disabled={form.processing}>Add Teacher</Button>
                </div>
            </form>

            <DataTable<Teacher> rows={teachers} emptyTitle="No teachers" columns={[
                { key: 'name', header: 'Name' },
                { key: 'email', header: 'Email' },
                { key: 'subjects', header: 'Class / Subjects', render: (teacher) => teacher.subjects.map((subject) => `${subject.class_name ?? 'Class'} - ${subject.name} (${subject.code})`).join(', ') },
                { key: 'actions', header: 'Actions', render: (teacher) => <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => edit(teacher)}>Edit</Button><Button size="sm" variant="danger" onClick={() => destroy(teacher)}>Delete</Button></div> },
            ]} />
        </PortalAppShell>
    );
}

function Input({ label, value, error, onChange, type = 'text' }: { label: string; value: string; error?: string; onChange: (value: string) => void; type?: string }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<input type={type} className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)} />{error && <span className="text-danger">{error}</span>}</label>;
}
