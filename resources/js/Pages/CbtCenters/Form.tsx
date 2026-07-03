import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { CbtCenter, OptionRow } from './types';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const labelClass = 'text-sm font-semibold text-slateDark';

export function CbtCenterForm({ center, organizations = [], statuses, submitLabel }: { center?: CbtCenter; organizations?: OptionRow[]; statuses: { value: string; label: string }[]; submitLabel: string }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        organization_id: center?.organization_id ? String(center.organization_id) : '',
        name: center?.name ?? '',
        code: center?.code ?? '',
        location: center?.location ?? '',
        capacity: String(center?.capacity ?? 0),
        contact_person: center?.contact_person ?? '',
        email: center?.email ?? '',
        phone: center?.phone ?? '',
        status: center?.status ?? 'active',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        center ? patch(`/cbt-centers/${center.id}`) : post('/cbt-centers');
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title="CBT Center Information"
                description="Create a standalone CBT delivery center for recruitment, assessment, certification, practice, and general exams."
                footer={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild type="button" variant="secondary"><Link href={center ? `/cbt-centers/${center.id}` : '/cbt-centers'}>Cancel</Link></Button>
                        <Button type="submit" disabled={processing}><Save className="h-4 w-4" />{submitLabel}</Button>
                    </div>
                }
            >
                <div className="grid gap-4 md:grid-cols-2">
                    {organizations.length > 0 && (
                        <Field label="Organization" error={errors.organization_id}>
                            <select className={inputClass} value={data.organization_id} onChange={(event) => setData('organization_id', event.target.value)}>
                                <option value="">Standalone center</option>
                                {organizations.map((organization) => <option key={organization.id} value={organization.id}>{organization.name}</option>)}
                            </select>
                        </Field>
                    )}
                    <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                    <Field label="Location" error={errors.location}><input required className={inputClass} value={data.location} onChange={(event) => setData('location', event.target.value)} /></Field>
                    <Field label="Capacity" error={errors.capacity}><input required type="number" min="0" className={inputClass} value={data.capacity} onChange={(event) => setData('capacity', event.target.value)} /></Field>
                    <Field label="Contact Person" error={errors.contact_person}><input required className={inputClass} value={data.contact_person} onChange={(event) => setData('contact_person', event.target.value)} /></Field>
                    <Field label="Email" error={errors.email}><input required type="email" className={inputClass} value={data.email} onChange={(event) => setData('email', event.target.value)} /></Field>
                    <Field label="Phone" error={errors.phone}><input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} /></Field>
                    <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>{statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</select></Field>
                </div>
            </FormSection>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="block"><span className={labelClass}>{label}</span>{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
