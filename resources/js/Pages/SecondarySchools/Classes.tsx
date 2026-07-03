import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

export default function Classes({ secondarySchool, classes, basePath }: { secondarySchool: { id: number; name: string }; classes: Array<Record<string, unknown>>; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchool.id}/classes`;
    const structureBase = basePath ? '/secondary-school/classes' : `/secondary-schools/${secondarySchool.id}/classes`;
    const classForm = useForm({ name: '', level: 'JSS 1', level_order: '', status: 'active' });
    const importForm = useForm<{ file: File | null }>({ file: null });
    const submitClass = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        classForm.post(path, { preserveScroll: true, onSuccess: () => classForm.reset() });
    };
    const edit = (row: Record<string, unknown>) => {
        const name = window.prompt('Class name', String(row.name ?? ''));
        if (name === null) return;
        const level = window.prompt('Level', String(row.level ?? 'JSS 1'));
        if (level === null) return;

        router.patch(`${path}/${row.id}`, {
            name,
            level,
            level_order: row.level_order ?? '',
            status: row.status ?? 'active',
        }, { preserveScroll: true });
    };
    const destroy = (row: Record<string, unknown>) => {
        if (window.confirm(`Delete ${String(row.name)}?`)) {
            router.delete(`${path}/${row.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Classes">
            <Head title="Classes" />
            <PageHeader eyebrow={secondarySchool.name} title="Classes" description="Add multiple classes under each level and use student groups for exam batches." />
            <ImportBox secondarySchoolId={secondarySchool.id} section="classes" form={importForm} basePath={basePath ? structureBase : undefined} />
            <form onSubmit={submitClass} className="mb-6 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm md:grid-cols-4">
                <Input label="Class Name" value={classForm.data.name} error={classForm.errors.name} onChange={(value) => classForm.setData('name', value)} />
                <Select label="Level" value={classForm.data.level} error={classForm.errors.level} options={['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3'].map((level) => ({ value: level, label: level }))} onChange={(value) => classForm.setData('level', value)} />
                <Input label="Display Order" value={classForm.data.level_order} error={classForm.errors.level_order} onChange={(value) => classForm.setData('level_order', value)} />
                <Button disabled={classForm.processing}>Save Class</Button>
            </form>
            <DataTable rows={classes} emptyTitle="No classes" columns={[
                { key: 'name', header: 'Class' },
                { key: 'level', header: 'Level' },
                { key: 'status', header: 'Status', render: (row) => <StatusBadge label={String(row.status)} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'students_count', header: 'Students' },
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

function Select({ label, value, error, options, onChange }: { label: string; value: string; error?: string; options: { value: string; label: string }[]; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)}><option value="">Select</option>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>{error && <span className="text-danger">{error}</span>}</label>;
}
