import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Edit, Power, XCircle } from 'lucide-react';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { AdminRegistration } from './types';

type Props = {
    registration: {
        data: AdminRegistration;
    };
};

export default function ShowAdminRegistration({ registration }: Props) {
    const record = registration.data;
    const { data, setData, patch, processing, errors } = useForm({ review_notes: '' });
    const pending = record.status === 'pending';
    const approved = record.status === 'approved';
    const label = record.entity_type_label;

    const review = (action: 'approve' | 'reject' | 'deactivate') => {
        patch(`/admin-registrations/${record.id}/${action}`, { preserveScroll: true });
    };

    return (
        <PortalAppShell title={record.entity_name}>
            <Head title={record.entity_name} />
            <section className="mx-auto max-w-5xl">
                <PageHeader
                    eyebrow="Application"
                    title={record.entity_name}
                    description={`Review submitted ${label.toLowerCase()} and administrator details before approving login access.`}
                    actions={pending && (
                        <Button asChild type="button" variant="secondary">
                            <Link href={`/admin-registrations/${record.id}/edit`}>
                                <Edit className="h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                    )}
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric label="Type" value={record.entity_type_label} />
                    <Metric label="Code" value={record.entity_code} />
                    <div className="rounded-md border border-border bg-white p-4 shadow-sm">
                        <div className="text-sm font-semibold text-slate-500">Status</div>
                        <div className="mt-3">
                            <StatusBadge label={record.status_label} tone={record.status === 'approved' ? 'success' : record.status === 'rejected' ? 'danger' : record.status === 'deactivated' ? 'neutral' : 'warning'} />
                        </div>
                    </div>
                </div>

                <div className="mt-5">
                    <Details title="Selected Plan" rows={[
                        ['Plan', record.pricing_plan?.name ?? 'N/A'],
                        ['Price', record.pricing_plan?.formatted_price ? `${record.pricing_plan.formatted_price} ${record.pricing_plan.billing_label ?? ''}` : 'N/A'],
                    ]} />
                </div>

                <div className="mt-6 grid gap-5 lg:grid-cols-2">
                    <Details title="Administrator" rows={[
                        ['Name', record.admin_name],
                        ['Email', record.admin_email],
                    ]} />
                    <Details title={`${label} Details`} rows={[
                        [`${label} Name`, record.entity_name],
                        [`${label} Email`, record.entity_email],
                        ['Contact Person', record.contact_person],
                        ['Phone', record.phone || 'N/A'],
                        ['Location', record.location || record.address || 'N/A'],
                        ['Capacity', record.capacity ? String(record.capacity) : 'N/A'],
                    ]} />
                </div>

                <div className="mt-5">
                    <Details title="Accreditation Details" rows={[
                        ['Legal Registration Number', record.legal_registration_number || 'N/A'],
                        ['Website', record.website || 'N/A'],
                        ['Years in Operation', record.years_in_operation !== null && record.years_in_operation !== undefined ? String(record.years_in_operation) : 'N/A'],
                        ['Operating Scope', record.operating_scope || 'N/A'],
                        ['Accreditation Body', record.accreditation_body || 'N/A'],
                        ['Accreditation Number', record.accreditation_number || 'N/A'],
                        ['Expected Candidates', record.expected_candidates ? String(record.expected_candidates) : 'N/A'],
                        ['Facility Summary', record.facility_summary || 'N/A'],
                        ['Exam Experience', record.exam_experience || 'N/A'],
                    ]} />
                </div>

                {record.review_notes && (
                    <div className="mt-5 rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Review Notes</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-600">{record.review_notes}</p>
                    </div>
                )}

                {pending && (
                    <form className="mt-5 rounded-md border border-border bg-white p-5 shadow-sm" onSubmit={(event) => event.preventDefault()}>
                        <label className="block">
                            <span className="text-sm font-semibold text-slateDark">Review Notes</span>
                            <textarea
                                className="mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                rows={4}
                                value={data.review_notes}
                                onChange={(event) => setData('review_notes', event.target.value)}
                            />
                            {errors.review_notes && <span className="mt-1 block text-sm text-danger">{errors.review_notes}</span>}
                        </label>
                        <div className="mt-4 flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="danger" disabled={processing} onClick={() => review('reject')}>
                                <XCircle className="h-4 w-4" />
                                Reject
                            </Button>
                            <Button type="button" disabled={processing} onClick={() => review('approve')}>
                                <CheckCircle2 className="h-4 w-4" />
                                Approve
                            </Button>
                        </div>
                    </form>
                )}

                {approved && (
                    <div className="mt-5 flex justify-end rounded-md border border-border bg-white p-5 shadow-sm">
                        <Button type="button" variant="danger" disabled={processing} onClick={() => review('deactivate')}>
                            <Power className="h-4 w-4" />
                            Deactivate
                        </Button>
                    </div>
                )}
            </section>
        </PortalAppShell>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="text-sm font-semibold text-slate-500">{label}</div>
            <div className="mt-2 text-lg font-bold text-slateDark">{value}</div>
        </div>
    );
}

function Details({ title, rows }: { title: string; rows: [string, string][] }) {
    return (
        <div className="rounded-md border border-border bg-white p-5 shadow-sm">
            <h2 className="font-semibold text-slateDark">{title}</h2>
            <div className="mt-4 divide-y divide-border">
                {rows.map(([label, value]) => (
                    <div key={label} className="grid gap-2 py-3 text-sm sm:grid-cols-[140px_1fr]">
                        <div className="font-semibold text-slate-500">{label}</div>
                        <div className="text-slateDark">{value}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}
