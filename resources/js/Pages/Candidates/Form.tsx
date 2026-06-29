import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Candidate, ScopeOption, StatusOption } from './types';

type CandidateFormData = {
    organization_id: string;
    school_id: string;
    center_id: string;
    full_name: string;
    registration_number: string;
    email: string;
    phone: string;
    date_of_birth: string;
    photo: File | null;
    status: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const labelClass = 'text-sm font-semibold text-slateDark';

export function CandidateForm({ candidate, organizations = [], schools = [], centers = [], statuses, submitLabel }: { candidate?: Candidate; organizations?: ScopeOption[]; schools?: ScopeOption[]; centers?: ScopeOption[]; statuses: StatusOption[]; submitLabel: string }) {
    const { data, setData, post, processing, errors, transform } = useForm<CandidateFormData>({
        organization_id: candidate?.organization_id ? String(candidate.organization_id) : '',
        school_id: candidate?.school_id ? String(candidate.school_id) : '',
        center_id: candidate?.center_id ? String(candidate.center_id) : '',
        full_name: candidate?.full_name ?? '',
        registration_number: candidate?.registration_number ?? '',
        email: candidate?.email ?? '',
        phone: candidate?.phone ?? '',
        date_of_birth: candidate?.date_of_birth ?? '',
        photo: null,
        status: candidate?.status ?? 'active',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        transform((current) => candidate ? { ...current, _method: 'patch' } : current);
        post(candidate ? `/candidates/${candidate.id}` : '/candidates', { forceFormData: true });
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title="Candidate Information"
                description="Create and maintain candidate identity, contact details, and registration number."
                footer={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild type="button" variant="secondary"><Link href={candidate ? `/candidates/${candidate.id}` : '/candidates'}><ArrowLeft className="h-4 w-4" />Back</Link></Button>
                        <Button type="submit" disabled={processing}><Save className="h-4 w-4" />{submitLabel}</Button>
                    </div>
                }
            >
                {!candidate && (organizations.length > 0 || schools.length > 0 || centers.length > 0) && (
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Organization Scope" error={errors.organization_id}>
                            <select className={inputClass} value={data.organization_id} onChange={(event) => setData({ ...data, organization_id: event.target.value, school_id: '', center_id: '' })}>
                                <option value="">None</option>
                                {organizations.map((organization) => <option key={organization.id} value={organization.id}>{organization.name}</option>)}
                            </select>
                        </Field>
                        <Field label="School Scope" error={errors.school_id}>
                            <select className={inputClass} value={data.school_id} onChange={(event) => setData({ ...data, school_id: event.target.value, organization_id: '', center_id: '' })}>
                                <option value="">None</option>
                                {schools.map((school) => <option key={school.id} value={school.id}>{school.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Center Scope" error={errors.center_id}>
                            <select className={inputClass} value={data.center_id} onChange={(event) => setData({ ...data, center_id: event.target.value, organization_id: '', school_id: '' })}>
                                <option value="">None</option>
                                {centers.map((center) => <option key={center.id} value={center.id}>{center.name}</option>)}
                            </select>
                        </Field>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Full Name" error={errors.full_name}><input className={inputClass} value={data.full_name} onChange={(event) => setData('full_name', event.target.value)} required /></Field>
                    <Field label="Registration Number" error={errors.registration_number}><input className={inputClass} value={data.registration_number} onChange={(event) => setData('registration_number', event.target.value.toUpperCase())} required /></Field>
                    <Field label="Status" error={errors.status}><select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>{statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</select></Field>
                    <Field label="Email" error={errors.email}><input className={inputClass} type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} /></Field>
                    <Field label="Phone" error={errors.phone}><input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} /></Field>
                    <Field label="Date of Birth" error={errors.date_of_birth}><input className={inputClass} type="date" value={data.date_of_birth} onChange={(event) => setData('date_of_birth', event.target.value)} /></Field>
                    <Field label="Photo" error={errors.photo}>
                        <input className="mt-1 block w-full rounded-md border border-border text-sm file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-semibold" type="file" accept="image/*" onChange={(event) => setData('photo', event.target.files?.[0] ?? null)} />
                    </Field>
                </div>
            </FormSection>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="block"><span className={labelClass}>{label}</span>{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
