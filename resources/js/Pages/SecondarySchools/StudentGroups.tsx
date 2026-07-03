import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ReactNode, useMemo, useState } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Option = { id: string | number; name: string; level?: string | null };
type StudentOption = { id: string | number; school_class_id?: string | number | null; name: string; code: string; class_name?: string | null };

export default function StudentGroups({ secondarySchool, classes, students = [], groups, basePath }: { secondarySchool: { id: number; name: string }; classes: Option[]; students?: StudentOption[]; groups: Array<Record<string, any>>; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchool.id}/student-groups`;
    const structureBase = basePath ? '/secondary-school/student-groups' : `/secondary-schools/${secondarySchool.id}/student-groups`;
    const [selectedGroupId, setSelectedGroupId] = useState(String(groups[0]?.id ?? ''));
    const selectedGroup = useMemo(() => groups.find((group) => String(group.id) === selectedGroupId) ?? null, [groups, selectedGroupId]);
    const initialStudentIds = selectedGroup ? groupStudentIds(selectedGroup) : [];
    const form = useForm({ school_class_id: String(classes[0]?.id ?? ''), name: '', code: '', status: 'active', student_ids: [] as string[] });
    const assignmentForm = useForm({ student_ids: initialStudentIds });
    const importForm = useForm<{ file: File | null }>({ file: null });
    const groupStudents = students.filter((student) => !form.data.school_class_id || String(student.school_class_id ?? '') === String(form.data.school_class_id));
    const assignmentStudents = selectedGroup
        ? students.filter((student) => !selectedGroup.school_class_id || !student.school_class_id || String(student.school_class_id) === String(selectedGroup.school_class_id))
        : students;
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(path, { preserveScroll: true, onSuccess: () => form.reset('name', 'code', 'student_ids') });
    };
    const selectGroup = (value: string) => {
        setSelectedGroupId(value);
        const group = groups.find((row) => String(row.id) === value);
        assignmentForm.setData('student_ids', group ? groupStudentIds(group) : []);
    };
    const toggleFormStudent = (id: string, checked: boolean) => {
        const next = checked ? [...form.data.student_ids, id] : form.data.student_ids.filter((studentId) => studentId !== id);
        form.setData('student_ids', Array.from(new Set(next)));
    };
    const toggleAssignmentStudent = (id: string, checked: boolean) => {
        const next = checked ? [...assignmentForm.data.student_ids, id] : assignmentForm.data.student_ids.filter((studentId) => studentId !== id);
        assignmentForm.setData('student_ids', Array.from(new Set(next)));
    };
    const saveAssignments = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedGroup) return;

        router.patch(`${path}/${selectedGroup.id}`, {
            school_class_id: String(selectedGroup.school_class_id ?? selectedGroup.school_class?.id ?? classes[0]?.id ?? ''),
            name: selectedGroup.name,
            code: selectedGroup.code ?? '',
            status: selectedGroup.status ?? 'active',
            student_ids: assignmentForm.data.student_ids,
        }, { preserveScroll: true });
    };
    const edit = (row: Record<string, any>) => {
        const name = window.prompt('Group name', String(row.name ?? ''));
        if (name === null) return;
        router.patch(`${path}/${row.id}`, {
            school_class_id: String(row.school_class_id ?? row.school_class?.id ?? classes[0]?.id ?? ''),
            name,
            code: row.code ?? '',
            status: row.status ?? 'active',
            student_ids: groupStudentIds(row),
        }, { preserveScroll: true });
    };
    const destroy = (row: Record<string, any>) => {
        if (window.confirm(`Delete ${String(row.name)}?`)) {
            router.delete(`${path}/${row.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Student Groups">
            <Head title="Student Groups" />
            <PageHeader eyebrow={secondarySchool.name} title="Student Groups" description="Create groups used to batch exams, such as Science, Arts, Commercial, or a special exam set." />
            <ImportBox secondarySchoolId={secondarySchool.id} section="student-groups" form={importForm} basePath={basePath ? structureBase : undefined} />
            <form onSubmit={submit} className="mb-6 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm md:grid-cols-5">
                <Select label="Class" value={form.data.school_class_id} error={form.errors.school_class_id} options={classes.map((row) => ({ value: String(row.id), label: `${row.name}${row.level ? ` (${row.level})` : ''}` }))} onChange={(value) => form.setData('school_class_id', value)} />
                <Input label="Group Name" value={form.data.name} error={form.errors.name} onChange={(value) => form.setData('name', value)} />
                <Select label="Status" value={form.data.status} error={form.errors.status} options={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]} onChange={(value) => form.setData('status', value)} />
                <Field label="Students" error={form.errors.student_ids}>
                    <div className="max-h-32 overflow-auto rounded-md border border-border bg-white p-2">
                        {groupStudents.length === 0 && <div className="text-sm text-slate-500">No students in this class.</div>}
                        {groupStudents.map((student) => (
                            <label key={student.id} className="flex items-center gap-2 py-1 text-sm font-medium text-slate-700">
                                <input type="checkbox" checked={form.data.student_ids.includes(String(student.id))} onChange={(event) => toggleFormStudent(String(student.id), event.target.checked)} />
                                <span>{student.name} ({student.code})</span>
                            </label>
                        ))}
                    </div>
                </Field>
                <Button disabled={form.processing || !form.data.school_class_id}>Save Group</Button>
            </form>
            <form onSubmit={saveAssignments} className="mb-6 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm md:grid-cols-[1fr_2fr_auto]">
                <Select label="Assign Group" value={selectedGroupId} error={assignmentForm.errors.student_ids} options={groups.map((group) => ({ value: String(group.id), label: String(group.name) }))} onChange={selectGroup} />
                <Field label="Group Students" error={assignmentForm.errors.student_ids}>
                    <div className="max-h-40 overflow-auto rounded-md border border-border bg-white p-2">
                        {!selectedGroup && <div className="text-sm text-slate-500">Create a group first.</div>}
                        {selectedGroup && assignmentStudents.length === 0 && <div className="text-sm text-slate-500">No students available for this group class.</div>}
                        {assignmentStudents.map((student) => (
                            <label key={student.id} className="flex items-center gap-2 py-1 text-sm font-medium text-slate-700">
                                <input type="checkbox" checked={assignmentForm.data.student_ids.includes(String(student.id))} onChange={(event) => toggleAssignmentStudent(String(student.id), event.target.checked)} />
                                <span>{student.name} ({student.code})</span>
                            </label>
                        ))}
                    </div>
                </Field>
                <Button disabled={!selectedGroup || assignmentForm.processing}>Save Students</Button>
            </form>
            <DataTable rows={groups} emptyTitle="No student groups" columns={[
                { key: 'name', header: 'Group' },
                { key: 'school_class', header: 'Class', render: (row) => row.school_class?.name ?? 'N/A' },
                { key: 'students_count', header: 'Students', render: (row) => row.students_count ?? row.candidates_count ?? 0 },
                { key: 'status', header: 'Status', render: (row) => <StatusBadge label={String(row.status)} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'actions', header: 'Actions', render: (row) => <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => edit(row)}>Edit</Button><Button size="sm" variant="danger" onClick={() => destroy(row)}>Delete</Button></div> },
            ]} />
        </PortalAppShell>
    );
}

function ImportBox({ secondarySchoolId, section, form, basePath }: { secondarySchoolId: number; section: string; form: ReturnType<typeof useForm<{ file: File | null }>>; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchoolId}/${section}`;
    return <form onSubmit={(event) => { event.preventDefault(); form.post(`${path}/import`, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() }); }} className="mb-6 flex flex-wrap items-end gap-3 rounded-md border border-border bg-white p-4 shadow-sm"><a className="text-sm font-semibold text-primary" href={`${path}/template`}>Download sample CSV</a><label className="text-sm font-semibold text-slateDark">Upload CSV<input type="file" accept=".csv,text/csv" className="mt-1 block text-sm" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></label><Button disabled={form.processing || !form.data.file}>Import</Button></form>;
}

function Input({ label, value, error, onChange }: { label: string; value: string; error?: string; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<input className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)} />{error && <span className="text-danger">{error}</span>}</label>;
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<div className="mt-1">{children}</div>{error && <span className="text-danger">{error}</span>}</label>;
}

function Select({ label, value, error, options, onChange }: { label: string; value: string; error?: string; options: { value: string; label: string }[]; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)}><option value="">Select</option>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>{error && <span className="text-danger">{error}</span>}</label>;
}

function groupStudentIds(group: Record<string, any>): string[] {
    const rows = Array.isArray(group.students) && group.students.length > 0 ? group.students : group.candidates;
    return Array.isArray(rows) ? rows.map((row) => String(row.id)) : [];
}
