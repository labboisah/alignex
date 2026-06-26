import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Organization, StatusOption } from './types';

type OrganizationFormData = {
    name: string;
    code: string;
    contact_person: string;
    email: string;
    phone: string;
    address: string;
    status: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const labelClass = 'text-sm font-semibold text-slateDark';

export function OrganizationForm({ organization, statuses, submitLabel }: { organization?: Organization; statuses: StatusOption[]; submitLabel: string }) {
    const { data, setData, post, patch, processing, errors } = useForm<OrganizationFormData>({
        name: organization?.name ?? '',
        code: organization?.code ?? '',
        contact_person: organization?.contact_person ?? '',
        email: organization?.email ?? '',
        phone: organization?.phone ?? '',
        address: organization?.address ?? '',
        status: organization?.status ?? 'active',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (organization) {
            patch(`/organizations/${organization.id}`);
            return;
        }

        post('/organizations');
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title="Organization Information"
                description="Capture the examination owner that will use AlignEx to manage and run exams."
                footer={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild type="button" variant="secondary">
                            <Link href={organization ? `/organizations/${organization.id}` : '/organizations'}>Cancel</Link>
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
                    <Field label="Code" error={errors.code}>
                        <input className={inputClass} value={data.code} onChange={(event) => setData('code', event.target.value)} required />
                    </Field>
                    <Field label="Contact Person" error={errors.contact_person}>
                        <input className={inputClass} value={data.contact_person} onChange={(event) => setData('contact_person', event.target.value)} required />
                    </Field>
                    <Field label="Email" error={errors.email}>
                        <input className={inputClass} type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} required />
                    </Field>
                    <Field label="Phone" error={errors.phone}>
                        <input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} />
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
                <Field label="Address" error={errors.address}>
                    <textarea className={inputClass} rows={4} value={data.address} onChange={(event) => setData('address', event.target.value)} />
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
