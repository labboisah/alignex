import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2, UserCircle2, UserPlus } from 'lucide-react';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Candidate } from './types';

export default function ShowCandidate({ candidate }: { candidate: { data: Candidate } }) {
    const record = candidate.data;

    return (
        <PortalAppShell title={record.full_name}>
            <Head title={record.full_name} />
            <section className="mx-auto max-w-6xl">
                <PageHeader
                    eyebrow="Candidate Details"
                    title={record.full_name}
                    description={record.registration_number}
                    actions={
                        <>
                            <Button asChild type="button" variant="secondary"><Link href="/candidates"><ArrowLeft className="h-4 w-4" />Back</Link></Button>
                            <Button asChild type="button" variant="secondary"><Link href={`/candidates/assignments`}><UserPlus className="h-4 w-4" />Assign to Exam</Link></Button>
                            <Button asChild type="button" variant="secondary"><Link href={`/candidates/${record.id}/edit`}><Pencil className="h-4 w-4" />Edit</Link></Button>
                            <Button type="button" variant="danger" onClick={() => window.confirm('Delete this candidate?') && router.delete(`/candidates/${record.id}`)}><Trash2 className="h-4 w-4" />Delete</Button>
                        </>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[280px_1fr]">
                    <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                        {record.photo_url ? (
                            <img src={record.photo_url} alt="" className="aspect-square w-full rounded-md object-cover" />
                        ) : (
                            <div className="flex aspect-square w-full items-center justify-center rounded-md bg-slate-100 text-slate-500"><UserCircle2 className="h-20 w-20" /></div>
                        )}
                        <div className="mt-4 flex justify-center"><StatusBadge label={record.status_label} tone={record.status === 'active' ? 'success' : record.status === 'suspended' ? 'danger' : 'neutral'} /></div>
                    </div>

                    <div className="space-y-5">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Metric label="Registration Number" value={record.registration_number} />
                            <Metric label="Organization" value={record.organization_name ?? record.school_name ?? record.center_name ?? 'N/A'} />
                            <Metric label="Email" value={record.email ?? 'N/A'} />
                            <Metric label="Phone" value={record.phone ?? 'N/A'} />
                            <Metric label="Date of Birth" value={record.date_of_birth ?? 'N/A'} />
                            <Metric label="Assigned Exams" value={String(record.assigned_exams?.length ?? 0)} />
                        </div>

                        <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                            <h2 className="font-semibold text-slateDark">Assigned Exams</h2>
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-xs uppercase text-slate-500"><tr><th className="py-2">Exam</th><th>Code</th><th>Status</th></tr></thead>
                                    <tbody className="divide-y divide-border">
                                        {(record.assigned_exams ?? []).length === 0 && <tr><td className="py-4 text-slate-500" colSpan={3}>No exams assigned.</td></tr>}
                                        {(record.assigned_exams ?? []).map((exam) => <tr key={exam.id}><td className="py-3 font-semibold">{exam.title}</td><td>{exam.exam_code}</td><td>{exam.status ?? 'assigned'}</td></tr>)}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="text-sm font-semibold text-slate-500">{label}</div><div className="mt-2 font-bold text-slateDark">{value}</div></div>;
}
