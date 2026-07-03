import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Option = { id: string; name: string; code: string; is_active?: boolean };

export default function Terms({ secondarySchool, sessions, terms, basePath }: { secondarySchool: { id: number; name: string }; sessions: Option[]; terms: Array<Record<string, unknown>>; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchool.id}/terms`;
    const structureBase = basePath ? '/secondary-school/terms' : `/secondary-schools/${secondarySchool.id}/terms`;
    const { data, setData, post, processing, errors, reset } = useForm({ academic_session_id: sessions.find((row) => row.is_active)?.id ?? sessions[0]?.id ?? '', name: 'First Term', code: '', start_date: '', end_date: '', status: 'active', is_active: false as boolean });
    const importForm = useForm<{ file: File | null }>({ file: null });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(path, { preserveScroll: true, onSuccess: () => reset('code', 'start_date', 'end_date') });
    };
    const edit = (row: Record<string, unknown>) => {
        const name = window.prompt('Term name', String(row.name ?? 'First Term'));
        if (name === null) return;
        router.patch(`${path}/${row.id}`, {
            academic_session_id: String(row.academic_session_id ?? sessions[0]?.id ?? ''),
            name,
            code: String(row.code ?? ''),
            start_date: String(row.starts_on ?? row.start_date ?? ''),
            end_date: String(row.ends_on ?? row.end_date ?? ''),
            status: String(row.status ?? 'active'),
            is_active: Boolean(row.is_active),
        }, { preserveScroll: true });
    };
    const destroy = (row: Record<string, unknown>) => {
        if (window.confirm(`Delete ${String(row.name)}?`)) {
            router.delete(`${path}/${row.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Terms">
            <Head title="Terms" />
            <PageHeader eyebrow={secondarySchool.name} title="Terms" description="Add First, Second, and Third Term under the correct academic session." />
            <ImportBox secondarySchoolId={secondarySchool.id} section="terms" form={importForm} basePath={basePath ? structureBase : undefined} />
            <form onSubmit={submit} className="mb-6 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm md:grid-cols-7">
                <Select label="Session" value={data.academic_session_id} error={errors.academic_session_id} options={sessions.map((row) => ({ value: row.id, label: row.name }))} onChange={(value) => setData('academic_session_id', value)} />
                <Select label="Term" value={data.name} error={errors.name} options={['First Term', 'Second Term', 'Third Term'].map((term) => ({ value: term, label: term }))} onChange={(value) => setData('name', value)} />
                <Input label="Start Date" type="date" value={data.start_date} error={errors.start_date} onChange={(value) => setData('start_date', value)} />
                <Input label="End Date" type="date" value={data.end_date} error={errors.end_date} onChange={(value) => setData('end_date', value)} />
                <label className="flex items-center gap-2 text-sm font-semibold text-slateDark"><input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />Active</label>
                <Button disabled={processing || !data.academic_session_id}>Save Term</Button>
            </form>
            <DataTable rows={terms} emptyTitle="No terms" columns={[
                { key: 'name', header: 'Name' },
                { key: 'status', header: 'Status', render: (row) => <StatusBadge label={String(row.status)} tone={row.status === 'active' ? 'success' : 'neutral'} /> },
                { key: 'is_active', header: 'Active', render: (row) => row.is_active ? 'Yes' : 'No' },
                { key: 'actions', header: 'Actions', render: (row) => <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => edit(row)}>Edit</Button><Button size="sm" variant="danger" onClick={() => destroy(row)}>Delete</Button></div> },
            ]} />
        </PortalAppShell>
    );
}

function ImportBox({ secondarySchoolId, section, form, basePath }: { secondarySchoolId: number; section: string; form: ReturnType<typeof useForm<{ file: File | null }>>; basePath?: string }) {
    const path = basePath ?? `/secondary-schools/${secondarySchoolId}/${section}`;
    return <form onSubmit={(event) => { event.preventDefault(); form.post(`${path}/import`, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() }); }} className="mb-6 flex flex-wrap items-end gap-3 rounded-md border border-border bg-white p-4 shadow-sm"><a className="text-sm font-semibold text-primary" href={`${path}/template`}>Download sample CSV</a><label className="text-sm font-semibold text-slateDark">Upload CSV<input type="file" accept=".csv,text/csv" className="mt-1 block text-sm" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></label><Button disabled={form.processing || !form.data.file}>Import</Button></form>;
}

function Input({ label, value, error, onChange, type = 'text' }: { label: string; value: string; error?: string; type?: string; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<input type={type} className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)} />{error && <span className="text-danger">{error}</span>}</label>;
}

function Select({ label, value, error, options, onChange }: { label: string; value: string; error?: string; options: { value: string; label: string }[]; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={value} onChange={(event) => onChange(event.target.value)}>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>{error && <span className="text-danger">{error}</span>}</label>;
}
