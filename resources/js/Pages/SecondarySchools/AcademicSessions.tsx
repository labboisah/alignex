import { useState as useEditState } from 'react';
import { RecordEditor, statusField } from '@/Components/Platform/RecordEditor';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

export default function AcademicSessions({ secondarySchool, sessions, basePath }: { secondarySchool: { id: number; name: string }; sessions: Array<Record<string, string | number | boolean>>; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchool.id}/academic-sessions`;
    const structureBase = basePath ? '/secondary-school/academic-sessions' : `/secondary-schools/${secondarySchool.id}/academic-sessions`;
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', start_date: '', end_date: '', status: 'active', is_active: false as boolean });
    const importForm = useForm<{ file: File | null }>({ file: null });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(path, { preserveScroll: true, onSuccess: () => reset() });
    };
    const [editing, setEditing] = useEditState<Record<string, any> | null>(null);
    const edit = (row: any) => setEditing(row);
    const destroy = (row: Record<string, string | number | boolean>) => {
        if (window.confirm(`Delete ${String(row.name)}?`)) {
            router.delete(`${path}/${row.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Academic Sessions">
            {editing && <RecordEditor key={String(editing.id)} title="Edit AcademicSessions" path={path + '/' + editing.id} values={{...editing,start_date:String(editing.starts_on ?? editing.start_date ?? '').slice(0,10),end_date:String(editing.ends_on ?? editing.end_date ?? '').slice(0,10)}} fields={[{name:'name',label:'Name',required:true},{name:'code',label:'Code'},{name:'start_date',label:'Start date',type:'date'},{name:'end_date',label:'End date',type:'date'},statusField,{name:'is_active',label:'Active session',type:'checkbox'}]} onCancel={() => setEditing(null)} />}
            <Head title="Academic Sessions" />
            <PageHeader eyebrow={secondarySchool.name} title="Academic Sessions" description="Add school years such as 2026/2027. Download the sample file if you want to upload many at once." />
            <ImportBox secondarySchoolId={secondarySchool.id} section="academic-sessions" form={importForm} basePath={basePath ? structureBase : undefined} />
            <Form onSubmit={submit}>
                <Input label="Name" value={data.name} error={errors.name} onChange={(value) => setData('name', value)} />
                <Input label="Start Date" type="date" value={data.start_date} error={errors.start_date} onChange={(value) => setData('start_date', value)} />
                <Input label="End Date" type="date" value={data.end_date} error={errors.end_date} onChange={(value) => setData('end_date', value)} />
                <label className="flex items-center gap-2 text-sm font-semibold text-slateDark"><input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />Active</label>
                <Button disabled={processing}>Save Session</Button>
            </Form>
            <DataTable rows={sessions} emptyTitle="No sessions" columns={[
                { key: 'name', header: 'Name' },
                { key: 'status', header: 'Status', render: (row) => <StatusBadge label={String(row.status)} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'is_active', header: 'Active', render: (row) => row.is_active ? 'Yes' : <Button size="sm" variant="secondary" onClick={() => router.patch(`${path}/${row.id}/active`)}>Set active</Button> },
                { key: 'terms_count', header: 'Terms' },
                { key: 'actions', header: 'Actions', render: (row) => <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => edit(row)}>Edit</Button><Button size="sm" variant="danger" onClick={() => destroy(row)}>Delete</Button></div> },
            ]} />
        </PortalAppShell>
    );
}

function ImportBox({ secondarySchoolId, section, form, basePath }: { secondarySchoolId: number; section: string; form: ReturnType<typeof useForm<{ file: File | null }>>; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchoolId}/${section}`;
    return <form onSubmit={(event) => { event.preventDefault(); form.post(`${path}/import`, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() }); }} className="mb-6 flex flex-wrap items-end gap-3 rounded-md border border-border bg-white p-4 shadow-sm"><a className="text-sm font-semibold text-primary" href={`${path}/template`}>Download sample CSV</a><label className="text-sm font-semibold text-slateDark">Upload CSV<input type="file" accept=".csv,text/csv" className="mt-1 block text-sm" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></label><Button disabled={form.processing || !form.data.file}>Import</Button></form>;
}

function Form({ onSubmit, children }: { onSubmit: (event: FormEvent<HTMLFormElement>) => void; children: ReactNode }) {
    return <form onSubmit={onSubmit} className="mb-6 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm md:grid-cols-6">{children}</form>;
}

function Input({ label, value, error, onChange, type = 'text' }: { label: string; value: string; error?: string; type?: string; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<input type={type} className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)} />{error && <span className="text-danger">{error}</span>}</label>;
}
