import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, ClipboardList, LucideIcon, Mail, MapPin, Pencil, Phone, Power, Users } from 'lucide-react';
import { ReactNode } from 'react';
import { PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Organization } from './types';

type Props = {
    organization: {
        data: Organization;
    };
    can: {
        update: boolean;
        deactivate: boolean;
    };
};

export default function ShowOrganization({ organization, can }: Props) {
    const record = organization.data;

    return (
        <PortalAppShell title={record.name}>
            <Head title={record.name} />
            <section className="mx-auto max-w-5xl">
                <PageHeader
                    eyebrow="Organization"
                    title={record.name}
                    description={record.description || 'Organization profile and platform delivery overview.'}
                    actions={
                        <>
                            <ProtectedAction allowed={can.update}>
                                <Button asChild type="button" variant="secondary">
                                    <Link href={`/organizations/${record.id}/edit`}>
                                        <Pencil className="h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>
                            </ProtectedAction>
                            <ProtectedAction allowed={can.deactivate && record.status !== 'inactive'}>
                                <Button
                                    type="button"
                                    variant="danger"
                                    onClick={() => router.patch(`/organizations/${record.id}/deactivate`, {}, { preserveScroll: true })}
                                >
                                    <Power className="h-4 w-4" />
                                    Deactivate
                                </Button>
                            </ProtectedAction>
                        </>
                    }
                />

                <div className="grid gap-4 md:grid-cols-2">
                    <Metric label="Code" value={record.code} />
                    <Metric label="Organization Type" value={record.organization_type_label || 'N/A'} />
                    <div className="rounded-md border border-border bg-white p-4 shadow-sm">
                        <div className="text-sm font-semibold text-slate-500">Status</div>
                        <div className="mt-3">
                            <StatusBadge label={record.status_label} tone={record.status === 'active' ? 'success' : 'neutral'} />
                        </div>
                    </div>
                </div>

                <div className="mt-6 grid gap-4 md:grid-cols-3">
                    <Metric label="Total Exams" value={String(record.exams_count ?? 0)} icon={ClipboardList} />
                    <Metric label="Total Candidates" value={String(record.candidates_count ?? 0)} icon={Users} />
                    <Metric label="Question Banks" value={String(record.question_banks_count ?? 0)} icon={BookOpen} />
                    <Metric label="Secondary Schools" value={String(record.secondary_schools_count ?? 0)} />
                    <Metric label="Professional Schools" value={String(record.professional_schools_count ?? 0)} />
                    <Metric label="CBT Centers" value={String(record.cbt_centers_count ?? 0)} />
                </div>

                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="text-base font-semibold text-slateDark">Contact Details</h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <Detail label="Contact Person" value={record.contact_person} />
                        <Detail icon={Mail} label="Email" value={record.email} />
                        <Detail icon={Phone} label="Phone" value={record.phone || 'N/A'} />
                        <Detail label="Website" value={record.website || 'N/A'} />
                        <Detail icon={MapPin} label="Address" value={record.address || 'N/A'} />
                    </div>
                </div>

                <div className="mt-6 grid gap-6 lg:grid-cols-2">
                    <Panel title="Organization-Level Exams" empty={!record.recent_exams?.length ? 'No organization exams yet.' : undefined}>
                        {record.recent_exams?.map((exam) => (
                            <Link key={exam.id} href={`/exams/${exam.id}`} className="flex items-center justify-between rounded-md border border-border p-3 hover:border-primary">
                                <div>
                                    <div className="font-semibold text-slateDark">{exam.title}</div>
                                    <div className="text-xs text-slate-500">
                                        {exam.code} · {exam.category || 'general'} · {exam.mode || 'traditional'}
                                    </div>
                                </div>
                                <StatusBadge label={exam.status.replaceAll('_', ' ')} tone={exam.status === 'active' ? 'success' : 'neutral'} />
                            </Link>
                        ))}
                    </Panel>
                    <Panel title="Recent Results" empty={!record.recent_results?.length ? 'No submitted results yet.' : undefined}>
                        {record.recent_results?.map((result) => (
                            <div key={result.id} className="rounded-md border border-border p-3">
                                <div className="font-semibold text-slateDark">{result.candidate_name || 'Candidate'}</div>
                                <div className="text-xs text-slate-500">
                                    {result.exam_title || 'Exam'} · Score: {result.score ?? 'N/A'}
                                </div>
                            </div>
                        ))}
                    </Panel>
                </div>

                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="text-base font-semibold text-slateDark">Quick Actions</h2>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Button asChild variant="secondary"><Link href={`/organizations/${record.id}/exams/create`}>Create Recruitment Exam</Link></Button>
                        <Button asChild variant="secondary"><Link href={`/organizations/${record.id}/candidates/create`}>Register Candidates</Link></Button>
                        <Button asChild variant="secondary"><Link href={`/organizations/${record.id}/question-bank`}>Manage Question Bank</Link></Button>
                        <Button asChild variant="secondary"><Link href={`/organizations/${record.id}/results`}>View Results</Link></Button>
                        <Button asChild variant="secondary"><Link href="/reports">Generate Reports</Link></Button>
                        <Button asChild variant="secondary"><Link href="/users">Manage Users</Link></Button>
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function Metric({ label, value, icon: Icon }: { label: string; value: string; icon?: LucideIcon }) {
    return (
        <div className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2 text-sm font-semibold text-slate-500">
                {Icon && <Icon className="h-4 w-4 text-primary" />}
                {label}
            </div>
            <div className="mt-2 text-xl font-bold text-slateDark">{value}</div>
        </div>
    );
}

function Detail({ label, value, icon: Icon }: { label: string; value: string; icon?: LucideIcon }) {
    return (
        <div className="flex gap-3 rounded-md border border-border p-4">
            {Icon && <Icon className="mt-0.5 h-4 w-4 text-primary" />}
            <div>
                <div className="text-sm font-semibold text-slate-500">{label}</div>
                <div className="mt-1 text-sm text-slateDark">{value}</div>
            </div>
        </div>
    );
}

function Panel({ title, empty, children }: { title: string; empty?: string; children: ReactNode }) {
    return (
        <section className="rounded-md border border-border bg-white p-5 shadow-sm">
            <h2 className="text-base font-semibold text-slateDark">{title}</h2>
            <div className="mt-4 space-y-3">
                {empty ? <div className="rounded-md border border-dashed border-border p-5 text-sm text-slate-500">{empty}</div> : children}
            </div>
        </section>
    );
}
