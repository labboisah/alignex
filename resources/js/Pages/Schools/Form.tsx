import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { School, StatusOption } from './types';

type SchoolFormData = {
    name: string;
    code: string;
    location: string;
    capacity: string;
    contact_person: string;
    phone: string;
    email: string;
    status: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const labelClass = 'text-sm font-semibold text-slateDark';

export function SchoolForm({ school, statuses, submitLabel }: { school?: School; statuses: StatusOption[]; submitLabel: string }) {
    const { data, setData, post, patch, processing, errors } = useForm<SchoolFormData>({
        name: school?.name ?? '',
        code: school?.code ?? '',
        location: school?.location ?? '',
        capacity: String(school?.capacity ?? ''),
        contact_person: school?.contact_person ?? '',
        phone: school?.phone ?? '',
        email: school?.email ?? '',
        status: school?.status ?? 'active',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (school) {
            patch(`/schools/${school.id}`);
            return;
        }

        post('/schools');
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title="School Information"
                description="Capture the school profile, contact details, capacity, and operating status."
                footer={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild type="button" variant="secondary">
                            <Link href={school ? `/schools/${school.id}` : '/schools'}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4" />
                            {submitLabel}
                        </Button>
                    </div>
                }
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Name" error={errors.name}>
                        <input className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Capacity" error={errors.capacity}>
                        <input className={inputClass} type="number" min="1" value={data.capacity} onChange={(event) => setData('capacity', event.target.value)} required />
                    </Field>
                    <Field label="Contact Person" error={errors.contact_person}>
                        <input className={inputClass} value={data.contact_person} onChange={(event) => setData('contact_person', event.target.value)} required />
                    </Field>
                    <Field label="Phone" error={errors.phone}>
                        <input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} />
                    </Field>
                    <Field label="Email" error={errors.email}>
                        <input className={inputClass} type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} required />
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>
                <Field label="Location" error={errors.location}>
                    <textarea className={inputClass} rows={4} value={data.location} onChange={(event) => setData('location', event.target.value)} required />
                </Field>
            </FormSection>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className={labelClass}>{label}</span>
            {children}
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}
