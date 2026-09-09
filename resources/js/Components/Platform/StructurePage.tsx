import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { DataTable, PageHeader, PortalAppShell } from '@/Components/Platform';
import { RecordEditor, statusField, type EditField } from '@/Components/Platform/RecordEditor';
import { Button } from '@/Components/ui/button';

export default function StructurePage({ title, path, owner, rows, fields, defaults, canManage = true }: {
    title: string; path: string; owner: string; rows: Record<string, any>[]; fields: EditField[]; defaults: Record<string, any>; canManage?: boolean;
}) {
    const [editing, setEditing] = useState<Record<string,any> | null>(null);
    const errors = usePage().props.errors as Record<string,string>;
    return <PortalAppShell title={title}><Head title={title} /><PageHeader title={title} description={owner} />
        {errors?.record && <p role="alert" className="mb-4 rounded border border-red-300 p-3 text-danger">{errors.record}</p>}
        {canManage && <RecordEditor key={editing?.id ?? 'new'} title={editing ? 'Edit '+title : 'New '+title} path={editing ? path+'/'+editing.id : path}
            method={editing ? 'patch' : 'post'} values={editing ? Object.fromEntries(Object.keys(defaults).map(key=>[key,editing[key] ?? defaults[key]])) : defaults}
            fields={[...fields,statusField]} onCancel={editing ? ()=>setEditing(null) : undefined} />}
        <DataTable rows={rows} emptyTitle={'No '+title.toLowerCase()} columns={[
            {key:'name',header:'Name'},{key:'code',header:'Code'},
            ...fields.filter(field => field.name.endsWith('_id') && field.options).map(field => ({key:field.name,header:field.label,render:(row:any)=>field.options?.find(option=>option.value===String(row[field.name]))?.label ?? 'N/A'})),
            {key:'status',header:'Status'},
            ...(canManage ? [{key:'actions',header:'Actions',render:(row:any)=><div className="flex gap-2"><Button size="sm" variant="secondary" onClick={()=>setEditing(row)}>Edit</Button>
                <Button size="sm" variant="danger" onClick={()=>{if(window.confirm('Delete this unused record? Linked records cannot be deleted.')) router.delete(path+'/'+row.id,{preserveScroll:true,onSuccess:()=>{if(editing?.id===row.id)setEditing(null);}});}}>Delete</Button></div>}] : [])
        ]} />
    </PortalAppShell>;
}
