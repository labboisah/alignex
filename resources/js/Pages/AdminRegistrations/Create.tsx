import { Head, Link, useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { AppLogo } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { EntityType } from './types';

type FormData = {
    entity_type: 'organization' | 'school' | 'center';
    admin_name: string;
    admin_email: string;
    password: string;
    password_confirmation: string;
    entity_name: string;
    entity_code: string;
    location: string;
    capacity: string;
    contact_person: string;
    phone: string;
    entity_email: string;
    address: string;
    legal_registration_number: string;
    website: string;
    years_in_operation: string;
    operating_scope: string;
    accreditation_body: string;
    accreditation_number: string;
    facility_summary: string;
    exam_experience: string;
    expected_candidates: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function CreateAdminRegistration({ entityTypes }: { entityTypes: EntityType[] }) {
    const { data, setData, post, processing, errors, reset } = useForm<FormData>({
        entity_type: 'organization',
        admin_name: '',
        admin_email: '',
        password: '',
        password_confirmation: '',
        entity_name: '',
        entity_code: '',
        location: '',
        capacity: '',
        contact_person: '',
        phone: '',
        entity_email: '',
        address: '',
        legal_registration_number: '',
        website: '',
        years_in_operation: '',
        operating_scope: '',
        accreditation_body: '',
        accreditation_number: '',
        facility_summary: '',
        exam_experience: '',
        expected_candidates: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/register-admin', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const label = typeLabel(data.entity_type);
    const requiresFacilityFields = data.entity_type !== 'organization';

    return (
        <main className="min-h-screen bg-surface text-slateDark">
            <Head title="Admin Registration" />
            <div className="mx-auto max-w-5xl px-4 py-8">
                <div className="mb-8 flex items-center justify-between">
                    <AppLogo />
                    <Button asChild type="button" variant="secondary">
                        <Link href="/login">Login</Link>
                    </Button>
                </div>

                <div className="mb-6">
                    <p className="text-sm font-semibold uppercase text-primary">Registration</p>
                    <h1 className="mt-1 text-3xl font-bold text-primaryDark">Register as an AlignEx administrator</h1>
                    <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Submit your details and admin login email. A super admin will review the request before your login becomes active.
                    </p>
                </div>

                <form onSubmit={submit} className="grid gap-5">
                    <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Registration Type</h2>
                        <div className="mt-4 grid gap-3 md:grid-cols-3">
                            {entityTypes.map((type) => (
                                <label key={type.value} className="cursor-pointer rounded-md border border-border p-4 hover:bg-slate-50">
                                    <input
                                        type="radio"
                                        className="text-primary focus:ring-primary"
                                        checked={data.entity_type === type.value}
                                        onChange={() => setData('entity_type', type.value)}
                                    />
                                    <span className="ml-2 font-semibold">{type.label}</span>
                                    <span className="mt-2 block text-sm leading-6 text-slate-600">{type.description}</span>
                                </label>
                            ))}
                        </div>
                        {errors.entity_type && <ErrorText message={errors.entity_type} />}
                    </section>

                    <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Administrator Login</h2>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <Field label="Admin Name" error={errors.admin_name}>
                                <input className={inputClass} value={data.admin_name} onChange={(event) => setData('admin_name', event.target.value)} required />
                            </Field>
                            <Field label="Admin Email" error={errors.admin_email}>
                                <input className={inputClass} type="email" value={data.admin_email} onChange={(event) => setData('admin_email', event.target.value)} required />
                            </Field>
                            <Field label="Password" error={errors.password}>
                                <input className={inputClass} type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} required />
                            </Field>
                            <Field label="Confirm Password" error={errors.password_confirmation}>
                                <input className={inputClass} type="password" value={data.password_confirmation} onChange={(event) => setData('password_confirmation', event.target.value)} required />
                            </Field>
                        </div>
                    </section>

                    <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">{label} Details</h2>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <Field label={`${label} Name`} error={errors.entity_name}>
                                <input className={inputClass} value={data.entity_name} onChange={(event) => setData('entity_name', event.target.value)} required />
                            </Field>
                            <Field label={`${label} Code`} error={errors.entity_code}>
                                <input className={inputClass} value={data.entity_code} onChange={(event) => setData('entity_code', event.target.value)} required />
                            </Field>
                            <Field label="Contact Person" error={errors.contact_person}>
                                <input className={inputClass} value={data.contact_person} onChange={(event) => setData('contact_person', event.target.value)} required />
                            </Field>
                            <Field label={`${label} Email`} error={errors.entity_email}>
                                <input className={inputClass} type="email" value={data.entity_email} onChange={(event) => setData('entity_email', event.target.value)} required />
                            </Field>
                            <Field label="Phone" error={errors.phone}>
                                <input className={inputClass} value={data.phone} onChange={(event) => setData('phone', event.target.value)} />
                            </Field>
                            {requiresFacilityFields && (
                                <Field label="Capacity" error={errors.capacity}>
                                    <input className={inputClass} type="number" min="1" value={data.capacity} onChange={(event) => setData('capacity', event.target.value)} required />
                                </Field>
                            )}
                        </div>
                        {requiresFacilityFields ? (
                            <Field label="Location" error={errors.location}>
                                <textarea className={inputClass} rows={4} value={data.location} onChange={(event) => setData('location', event.target.value)} required />
                            </Field>
                        ) : (
                            <Field label="Address" error={errors.address}>
                                <textarea className={inputClass} rows={4} value={data.address} onChange={(event) => setData('address', event.target.value)} required />
                            </Field>
                        )}
                    </section>

                    <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Accreditation Details</h2>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <Field label="Legal Registration Number" error={errors.legal_registration_number}>
                                <input className={inputClass} value={data.legal_registration_number} onChange={(event) => setData('legal_registration_number', event.target.value)} />
                            </Field>
                            <Field label="Website" error={errors.website}>
                                <input className={inputClass} type="url" value={data.website} onChange={(event) => setData('website', event.target.value)} />
                            </Field>
                            <Field label="Years in Operation" error={errors.years_in_operation}>
                                <input className={inputClass} type="number" min="0" value={data.years_in_operation} onChange={(event) => setData('years_in_operation', event.target.value)} />
                            </Field>
                            <Field label="Operating Scope" error={errors.operating_scope}>
                                <input className={inputClass} value={data.operating_scope} onChange={(event) => setData('operating_scope', event.target.value)} placeholder="Local, state, national, online" />
                            </Field>
                            <Field label="Accreditation Body" error={errors.accreditation_body}>
                                <input className={inputClass} value={data.accreditation_body} onChange={(event) => setData('accreditation_body', event.target.value)} />
                            </Field>
                            <Field label="Accreditation Number" error={errors.accreditation_number}>
                                <input className={inputClass} value={data.accreditation_number} onChange={(event) => setData('accreditation_number', event.target.value)} />
                            </Field>
                            <Field label="Expected Candidates" error={errors.expected_candidates}>
                                <input className={inputClass} type="number" min="1" value={data.expected_candidates} onChange={(event) => setData('expected_candidates', event.target.value)} />
                            </Field>
                        </div>
                        {requiresFacilityFields && (
                            <Field label="Facility and Equipment Summary" error={errors.facility_summary}>
                                <textarea className={inputClass} rows={4} value={data.facility_summary} onChange={(event) => setData('facility_summary', event.target.value)} required />
                            </Field>
                        )}
                        <Field label="Exam Delivery Experience" error={errors.exam_experience}>
                            <textarea className={inputClass} rows={4} value={data.exam_experience} onChange={(event) => setData('exam_experience', event.target.value)} />
                        </Field>
                    </section>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            <Send className="h-4 w-4" />
                            Submit for Review
                        </Button>
                    </div>
                </form>
            </div>
        </main>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="text-sm font-semibold text-slateDark">{label}</span>
            {children}
            {error && <ErrorText message={error} />}
        </label>
    );
}

function ErrorText({ message }: { message?: string }) {
    return message ? <span className="mt-1 block text-sm text-danger">{message}</span> : null;
}

function typeLabel(type: FormData['entity_type']) {
    if (type === 'school') {
        return 'School';
    }

    if (type === 'center') {
        return 'Center';
    }

    return 'Organization';
}
