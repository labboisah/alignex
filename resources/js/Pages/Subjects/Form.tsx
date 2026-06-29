import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { ScopeOption, StatusOption, Subject } from './types';

type SubjectFormData = {
    organization_id: string;
    school_id: string;
    center_id: string;
    name: string;
    code: string;
    description: string;
    status: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const labelClass = 'text-sm font-semibold text-slateDark';

export function SubjectForm({ subject, statuses, organizations = [], schools = [], centers = [], submitLabel }: { subject?: Subject; statuses: StatusOption[]; organizations?: ScopeOption[]; schools?: ScopeOption[]; centers?: ScopeOption[]; submitLabel: string }) {
    const { data, setData, post, patch, processing, errors } = useForm<SubjectFormData>({
        organization_id: subject?.organization_id ? String(subject.organization_id) : '',
        school_id: subject?.school_id ? String(subject.school_id) : '',
        center_id: subject?.center_id ? String(subject.center_id) : '',
        name: subject?.name ?? '',
        code: subject?.code ?? '',
        description: subject?.description ?? '',
        status: subject?.status ?? 'active',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        subject ? patch(`/subjects/${subject.id}`) : post('/subjects');
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title="Subject Information"
                description="Create an examinable subject within the active organization or school scope."
                footer={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild type="button" variant="secondary">
                            <Link href="/subjects">Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4" />
                            {submitLabel}
                        </Button>
                    </div>
                }
            >
                {!subject && (organizations.length > 0 || schools.length > 0 || centers.length > 0) && (
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Organization Scope" error={errors.organization_id}>
                            <select
                                className={inputClass}
                                value={data.organization_id}
                                onChange={(event) => {
                                    setData({
                                        ...data,
                                        organization_id: event.target.value,
                                        school_id: '',
                                        center_id: '',
                                    });
                                }}
                            >
                                <option value="">None</option>
                                {organizations.map((organization) => (
                                    <option key={organization.id} value={organization.id}>{organization.name}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="School Scope" error={errors.school_id}>
                            <select
                                className={inputClass}
                                value={data.school_id}
                                onChange={(event) => {
                                    setData({
                                        ...data,
                                        school_id: event.target.value,
                                        organization_id: '',
                                        center_id: '',
                                    });
                                }}
                            >
                                <option value="">None</option>
                                {schools.map((school) => (
                                    <option key={school.id} value={school.id}>{school.name}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Center Scope" error={errors.center_id}>
                            <select
                                className={inputClass}
                                value={data.center_id}
                                onChange={(event) => {
                                    setData({
                                        ...data,
                                        center_id: event.target.value,
                                        organization_id: '',
                                        school_id: '',
                                    });
                                }}
                            >
                                <option value="">None</option>
                                {centers.map((center) => (
                                    <option key={center.id} value={center.id}>{center.name}</option>
                                ))}
                            </select>
                        </Field>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Name" error={errors.name}>
                        <input className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Code" error={errors.code}>
                        <input className={inputClass} value={data.code} onChange={(event) => setData('code', event.target.value.toUpperCase())} required />
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                        </select>
                    </Field>
                </div>
                <Field label="Description" error={errors.description}>
                    <textarea className={inputClass} rows={4} value={data.description} onChange={(event) => setData('description', event.target.value)} />
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
