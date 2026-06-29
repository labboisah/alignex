import { Head, Link, router } from '@inertiajs/react';
import { Award, BarChart3, BriefcaseBusiness, Monitor, Pencil, Shuffle, UserPlus, XCircle } from 'lucide-react';
import { PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Exam } from './types';

export default function ShowExam({ exam, can }: { exam: { data: Exam }; can: { update: boolean; cancel: boolean } }) {
    const record = exam.data;
    return (
        <PortalAppShell title={record.title}>
            <Head title={record.title} />
            <section className="mx-auto max-w-6xl">
                <PageHeader
                    eyebrow="Exam Details"
                    title={record.title}
                    description={`${record.exam_code} | ${record.exam_type_label ?? record.exam_type} | ${record.delivery_mode}`}
                    actions={
                        <>
                            <ProtectedAction allowed={can.update}><Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/edit`}><Pencil className="h-4 w-4" />Edit</Link></Button></ProtectedAction>
                            <Button asChild type="button" variant="secondary"><Link href={`/candidates/assignments?exam_id=${record.id}`}><UserPlus className="h-4 w-4" />Assign Candidates</Link></Button>
                            <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/papers`}><Shuffle className="h-4 w-4" />Generate Papers</Link></Button>
                            <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/monitor`}><Monitor className="h-4 w-4" />Monitor</Link></Button>
                            <Button asChild type="button" variant="secondary"><Link href={`/results/exams/${record.id}`}><BarChart3 className="h-4 w-4" />Results</Link></Button>
                            {(record.exam_type === 'professional' || record.exam_type === 'secondary') && <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/certification`}><Award className="h-4 w-4" />Certification</Link></Button>}
                            {record.exam_type === 'recruitment' && <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/recruitment`}><BriefcaseBusiness className="h-4 w-4" />Recruitment</Link></Button>}
                            <ProtectedAction allowed={can.cancel}><Button type="button" variant="danger" onClick={() => window.confirm('Cancel this exam?') && router.patch(`/exams/${record.id}/cancel`, {}, { preserveScroll: true })}><XCircle className="h-4 w-4" />Cancel Exam</Button></ProtectedAction>
                        </>
                    }
                />
                <div className="grid gap-4 md:grid-cols-4">
                    <Metric label="Status" value={record.status_label} badge={record.status} />
                    <Metric label="Total Marks" value={String(record.total_marks)} />
                    <Metric label="Pass Mark" value={String(record.pass_mark)} />
                    <Metric label="Duration" value={`${record.duration_minutes} minutes`} />
                </div>
                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slateDark">Subject Configuration</h2>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-xs uppercase text-slate-500"><tr><th className="py-2">Subject</th><th>Questions</th><th>Marks Each</th><th>Total</th><th>Duration</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {record.subjects?.map((subject) => <tr key={subject.subject_id}><td className="py-3 font-semibold">{subject.subject_name}</td><td>{subject.number_of_questions}</td><td>{subject.marks_per_question}</td><td>{subject.total_marks}</td><td>{subject.duration_minutes ?? 'Exam default'}</td></tr>)}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slateDark">Settings</h2>
                    <div className="mt-4 grid gap-3 md:grid-cols-3">
                        {Object.entries(record.settings ?? {}).map(([key, value]) => <div key={key} className="rounded-md border border-border p-3"><div className="text-xs font-semibold uppercase text-slate-500">{key.replaceAll('_', ' ')}</div><div className="mt-1 font-semibold text-slateDark">{String(value)}</div></div>)}
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function Metric({ label, value, badge }: { label: string; value: string; badge?: string }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="text-sm font-semibold text-slate-500">{label}</div><div className="mt-2">{badge ? <StatusBadge label={value} tone={badge === 'active' ? 'success' : badge === 'cancelled' ? 'danger' : 'neutral'} /> : <span className="text-lg font-bold text-slateDark">{value}</span>}</div></div>;
}
