import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type ClassRow = { id: string | number; name: string };
type StudentImportForm = { file: File | null; school_class_id: string };

export default function Students({ secondarySchool, classes, students, basePath }: { secondarySchool: { id: number; name: string }; classes: ClassRow[]; students: Array<Record<string, unknown>>; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchool.id}/students`;
    const structureBase = basePath ? '/secondary-school/students' : `/secondary-schools/${secondarySchool.id}/students`;
    const { data, setData, post, processing, errors, reset } = useForm({ school_class_id: String(classes[0]?.id ?? ''), admission_number: '', full_name: '', gender: '', email: '', phone: '', guardian_name: '', guardian_phone: '', status: 'active' });
    const importForm = useForm<StudentImportForm>({ file: null, school_class_id: String(classes[0]?.id ?? '') });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(path, { preserveScroll: true, onSuccess: () => reset('admission_number', 'full_name', 'email', 'phone', 'guardian_name', 'guardian_phone') });
    };
    const edit = (row: Record<string, unknown>) => {
        const fullName = window.prompt('Student full name', String(row.full_name ?? ''));
        if (fullName === null) return;
        const admissionNumber = window.prompt('Admission number', String(row.admission_number ?? ''));
        if (admissionNumber === null) return;

        router.patch(`${path}/${row.id}`, {
            school_class_id: String(row.school_class_id ?? classes[0]?.id ?? ''),
            admission_number: admissionNumber,
            full_name: fullName,
            gender: row.gender ?? '',
            email: row.email ?? '',
            phone: row.phone ?? '',
            guardian_name: row.guardian_name ?? '',
            guardian_phone: row.guardian_phone ?? '',
            status: row.status ?? 'active',
        }, { preserveScroll: true });
    };
    const destroy = (row: Record<string, unknown>) => {
        if (window.confirm(`Delete ${String(row.full_name)}?`)) {
            router.delete(`${path}/${row.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Students">
            <Head title="Students" />
            <PageHeader eyebrow={secondarySchool.name} title="Students" description="Register students directly under the secondary school academic structure." />
            <ImportBox secondarySchoolId={secondarySchool.id} section="students" form={importForm} classes={classes} basePath={basePath ? structureBase : undefined} />
            <form onSubmit={submit} className="mb-6 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm md:grid-cols-4">
                <Select label="Class" value={data.school_class_id} error={errors.school_class_id} options={classes.map((row) => ({ value: String(row.id), label: row.name }))} onChange={(value) => setData('school_class_id', value)} />
                <Input label="Admission Number" value={data.admission_number} error={errors.admission_number} onChange={(value) => setData('admission_number', value)} />
                <Input label="Full Name" value={data.full_name} error={errors.full_name} onChange={(value) => setData('full_name', value)} />
                <Select label="Gender" value={data.gender} error={errors.gender} options={[{ value: '', label: 'Not specified' }, { value: 'male', label: 'Male' }, { value: 'female', label: 'Female' }]} onChange={(value) => setData('gender', value)} />
                <Input label="Email" value={data.email} error={errors.email} onChange={(value) => setData('email', value)} />
                <Input label="Phone" value={data.phone} error={errors.phone} onChange={(value) => setData('phone', value)} />
                <Input label="Guardian Name" value={data.guardian_name} error={errors.guardian_name} onChange={(value) => setData('guardian_name', value)} />
                <Input label="Guardian Phone" value={data.guardian_phone} error={errors.guardian_phone} onChange={(value) => setData('guardian_phone', value)} />
                <Button disabled={processing || !data.school_class_id}>Save Student</Button>
            </form>
            <DataTable rows={students} emptyTitle="No students" columns={[
                { key: 'photo', header: 'Photo', render: () => 'N/A' },
                { key: 'admission_number', header: 'Admission Number' },
                { key: 'full_name', header: 'Full Name' },
                { key: 'class_name', header: 'Class' },
                { key: 'guardian_phone', header: 'Guardian Phone' },
                { key: 'status', header: 'Status', render: (row) => <StatusBadge label={String(row.status)} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'actions', header: 'Actions', render: (row) => <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => edit(row)}>Edit</Button><Button size="sm" variant="danger" onClick={() => destroy(row)}>Delete</Button></div> },
            ]} />
        </PortalAppShell>
    );
}

function ImportBox({ secondarySchoolId, section, form, classes, basePath }: { secondarySchoolId: number; section: string; form: ReturnType<typeof useForm<StudentImportForm>>; classes: ClassRow[]; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchoolId}/${section}`;

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`${path}/import`, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('file') });
            }}
            className="mb-6 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm md:grid-cols-4"
        >
            <div className="flex items-end">
                <a className="text-sm font-semibold text-primary" href={`${path}/template`}>Download sample CSV</a>
            </div>
            <Select label="Class" value={form.data.school_class_id} error={form.errors.school_class_id} options={classes.map((row) => ({ value: String(row.id), label: row.name }))} onChange={(value) => form.setData('school_class_id', value)} />
            <label className="block text-sm font-semibold text-slateDark">Upload CSV<input type="file" accept=".csv,text/csv" className="mt-1 block text-sm" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} />{form.errors.file && <span className="text-danger">{form.errors.file}</span>}</label>
            <div className="flex items-end">
                <Button disabled={form.processing || !form.data.file || !form.data.school_class_id}>Import Students</Button>
            </div>
        </form>
    );
}

function Input({ label, value, error, onChange }: { label: string; value: string; error?: string; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<input className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)} />{error && <span className="text-danger">{error}</span>}</label>;
}

function Select({ label, value, error, options, onChange }: { label: string; value: string; error?: string; options: { value: string; label: string }[]; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)}>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>{error && <span className="text-danger">{error}</span>}</label>;
}
