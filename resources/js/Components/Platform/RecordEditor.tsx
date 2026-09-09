import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

export type EditField = { name: string; label: string; type?: string; required?: boolean; options?: { value: string; label: string }[] };
export function RecordEditor({ title, path, values, fields, onCancel, method = 'patch' }: {
    title: string; path: string; values: Record<string, any>; fields: EditField[]; onCancel?: () => void; method?: 'post' | 'patch';
}) {
    const form = useForm(values);
    return <form aria-label={title} onSubmit={e => { e.preventDefault(); form.submit(method, path, { preserveScroll: true, onSuccess: () => { onCancel?.(); if (method === 'post') form.reset(); } }); }}
        className="mb-6 space-y-4 rounded-lg border bg-white p-5">
        <h2 className="text-lg font-semibold">{title}</h2>
        {form.errors.record && <p role="alert" className="text-danger">{form.errors.record}</p>}
        <div className="grid gap-4 md:grid-cols-2">
            {fields.map(field => <label key={field.name} className="block text-sm font-semibold">{field.label}
                {field.type === 'checkbox' ? <input className="ml-3" type="checkbox" checked={Boolean(form.data[field.name])} onChange={e => form.setData(field.name, e.target.checked)} /> :
                field.type === 'multiple' ? <select multiple className="mt-1 block w-full rounded border-border" value={(form.data[field.name] || []).map(String)}
                    onChange={e => form.setData(field.name, Array.from(e.target.selectedOptions, option => option.value))}>
                    {field.options?.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select> : field.options ? <select aria-label={field.label} required={field.required} className="mt-1 block w-full rounded border-border" value={form.data[field.name] ?? ''} onChange={e => form.setData(field.name, e.target.value)}>
                    <option value="">Select</option>{field.options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select> : field.type === 'textarea' ? <textarea className="mt-1 block w-full rounded border-border" value={form.data[field.name] ?? ''} onChange={e => form.setData(field.name, e.target.value)} /> :
                <input required={field.required} type={field.type || 'text'} className="mt-1 block w-full rounded border-border" value={form.data[field.name] ?? ''} onChange={e => form.setData(field.name, e.target.value)} />}
                {form.errors[field.name] && <span role="alert" className="block text-danger">{form.errors[field.name]}</span>}
            </label>)}
        </div>
        <div className="flex gap-3"><Button disabled={form.processing}>{form.processing ? 'Saving…' : 'Save changes'}</Button>
            {onCancel && <Button type="button" variant="secondary" disabled={form.processing} onClick={onCancel}>Cancel</Button>}</div>
    </form>;
}
export const statusField: EditField = { name: 'status', label: 'Status', required: true, options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] };
