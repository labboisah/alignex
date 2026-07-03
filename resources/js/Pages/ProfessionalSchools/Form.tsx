import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Option = { id?: number; value?: string; name?: string; label?: string; code?: string };
type ProfessionalSchool = Record<string, unknown> & {
    id: number;
    organization_id?: number | null;
    name: string;
    code: string;
    contact_person: string;
    email: string;
    phone?: string | null;
    address?: string | null;
    status: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export function ProfessionalSchoolForm({ professionalSchool, organizations, statuses }: { professionalSchool?: ProfessionalSchool; organizations: Option[]; statuses: Option[] }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        organization_id: String(professionalSchool?.organization_id ?? ''),
        name: professionalSchool?.name ?? '',
        code: professionalSchool?.code ?? '',
        contact_person: professionalSchool?.contact_person ?? '',
        email: professionalSchool?.email ?? '',
        phone: professionalSchool?.phone ?? '',
        address: professionalSchool?.address ?? '',
        status: professionalSchool?.status ?? 'active',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        professionalSchool ? patch(`/professional-schools/${professionalSchool.id}`) : post('/professional-schools');
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title="Professional School Profile"
                description="Register a training, certification, bootcamp, academy, or vocational exam owner."
                footer={<div className="flex justify-end gap-2"><Button asChild variant="secondary"><Link href={professionalSchool ? `/professional-schools/${professionalSchool.id}` : '/professional-schools'}>Cancel</Link></Button><Button disabled={processing}><Save className="h-4 w-4" />Save</Button></div>}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Organization" error={errors.organization_id}>
                        <select className={inputClass} value={data.organization_id} onChange={(event) => setData('organization_id', event.target.value)}>
                            <option value="">Standalone</option>
                            {organizations.map((organization) => <option key={organization.id} value={organization.id}>{organization.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                    <Field label="Contact Person" error={errors.contact_person}><input required className={inputClass} value={data.contact_person} onChange={(event) => setData('contact_person', event.target.value)} /></Field>
                    <Field label="Email" error={errors.email}><input required type="email" className={inputClass} value={data.email} onChange={(event) => setData('email', event.target.value)} /></Field>
                    <Field label="Phone" error={errors.phone}><input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} /></Field>
                </div>
                <Field label="Address" error={errors.address}><textarea rows={4} className={inputClass} value={data.address} onChange={(event) => setData('address', event.target.value)} /></Field>
            </FormSection>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
