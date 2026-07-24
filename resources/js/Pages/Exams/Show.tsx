import { Head, Link, router, usePage } from '@inertiajs/react';
import { Award, BarChart3, BriefcaseBusiness, Monitor, Pencil, RefreshCw, Shuffle, Trash2, UserPlus, XCircle } from 'lucide-react';
import { PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Exam } from './types';

export default function ShowExam({ exam, can }: { exam: { data: Exam }; can: { update: boolean; cancel: boolean; delete?: boolean } }) {
    const auth = usePage().props.auth as { user?: { role?: string } };
    const isAssessmentRole = auth.user?.role === 'teacher' || auth.user?.role === 'facilitator';
    const noun = isAssessmentRole ? 'Assessment' : 'Exam';
    const record = exam.data;
    const isSecondary = record.owner_context === 'secondary_school' || record.secondary_school_id;
    const isProfessional = record.owner_context === 'professional_school' || record.professional_school_id;
    const paperLabel = isProfessional ? 'Module' : 'Subject';
    const paperLabelPlural = isProfessional ? 'Module Configuration' : 'Subject Configuration';
    return (
        <PortalAppShell title={record.title}>
            <Head title={record.title} />
            <section className="mx-auto max-w-6xl">
                <PageHeader
                    eyebrow={`${noun} Details`}
                    title={record.title}
                    description={`${record.exam_code} | ${record.exam_type_label ?? record.exam_type} | ${record.delivery_mode}`}
                    actions={
                        <>
                            <ProtectedAction allowed={can.update}><Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/edit`}><Pencil className="h-4 w-4" />Edit</Link></Button></ProtectedAction>
                            <Button asChild type="button" variant="secondary"><Link href={`/candidates/assignments?exam_id=${record.id}`}><UserPlus className="h-4 w-4" />{isSecondary ? 'Assign Students' : 'Assign Candidates'}</Link></Button>
                            <ProtectedAction allowed={can.update}><Button type="button" variant="secondary" onClick={() => window.confirm(`Refresh ${isSecondary ? 'students' : 'candidates'} from the selected ${refreshSourceLabel(record)}?`) && router.post(`/exams/${record.id}/participants/refresh`, {}, { preserveScroll: true })}><RefreshCw className="h-4 w-4" />Refresh {isSecondary ? 'Students' : 'Candidates'}</Button></ProtectedAction>
                            <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/papers`}><Shuffle className="h-4 w-4" />Generate Papers</Link></Button>
                            <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/monitor`}><Monitor className="h-4 w-4" />Monitor</Link></Button>
                            <Button asChild type="button" variant="secondary"><Link href={`/results/exams/${record.id}`}><BarChart3 className="h-4 w-4" />Results</Link></Button>
                            {!isAssessmentRole && (record.exam_type === 'professional' || record.exam_type === 'secondary') && <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/certification`}><Award className="h-4 w-4" />Certification</Link></Button>}
                            {!isAssessmentRole && record.exam_type === 'recruitment' && <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}/recruitment`}><BriefcaseBusiness className="h-4 w-4" />Recruitment</Link></Button>}
                            <ProtectedAction allowed={can.cancel}><Button type="button" variant="danger" onClick={() => window.confirm(`Cancel this ${noun.toLowerCase()}?`) && router.patch(`/exams/${record.id}/cancel`, {}, { preserveScroll: true })}><XCircle className="h-4 w-4" />Cancel {noun}</Button></ProtectedAction>
                            <ProtectedAction allowed={can.delete ?? false}><Button type="button" variant="danger" onClick={() => window.confirm(`Delete this ${noun.toLowerCase()}?`) && router.delete(`/exams/${record.id}`, { preserveScroll: true })}><Trash2 className="h-4 w-4" />Delete</Button></ProtectedAction>
                        </>
                    }
                />
                <div className="grid gap-4 md:grid-cols-4">
                    <Metric label="Status" value={record.status_label} badge={record.status} />
                    <Metric label="Owner Context" value={record.owner_context_label ?? 'Exam'} />
                    <Metric label="Category" value={record.exam_category ?? 'N/A'} />
                    <Metric label="Mode" value={record.exam_mode ?? record.mode} />
                    <Metric label="Total Marks" value={String(record.total_marks)} />
                    <Metric label="Pass Mark" value={String(record.pass_mark)} />
                    <Metric label="Duration" value={`${record.duration_minutes} minutes`} />
                    <Metric label="Subjects" value={String(record.subjects_count ?? record.subjects?.length ?? 0)} />
                    <Metric label="Participants" value={String(record.participants_count ?? 0)} />
                    <Metric label="Question Bank" value={record.question_bank_name ?? 'N/A'} />
                    <Metric label="Papers" value={record.paper_generation_status ?? '0 generated'} />
                    <Metric label="Submissions" value={record.submission_status ?? '0 submitted'} />
                </div>
                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slateDark">{paperLabelPlural}</h2>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-xs uppercase text-slate-500"><tr><th className="py-2">{paperLabel}</th><th>Questions</th><th>Marks Each</th><th>Total</th><th>Duration</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {record.subjects?.map((subject) => <tr key={subject.subject_id}><td className="py-3 font-semibold">{subject.subject_name}</td><td>{subject.number_of_questions}</td><td>{subject.marks_per_question}</td><td>{subject.total_marks}</td><td>{subject.duration_minutes ?? 'Exam default'}</td></tr>)}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slateDark">Results Summary</h2>
                    <div className="mt-4 grid gap-3 md:grid-cols-3">
                        <Metric label="Submitted" value={String(record.results_summary?.submitted ?? 0)} />
                        <Metric label="Passed" value={String(record.results_summary?.passed ?? 0)} />
                        <Metric label="Failed" value={String(record.results_summary?.failed ?? 0)} />
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

function refreshSourceLabel(record: Exam): string {
    if (record.owner_context === 'secondary_school' || record.secondary_school_id) return 'student group';
    if (record.owner_context === 'professional_school' || record.professional_school_id) return 'batch';
    if ((record.candidate_group_ids?.length ?? 0) > 0 || record.candidate_group_id) return 'candidate group';
    return 'saved participant list';
}

function Metric({ label, value, badge }: { label: string; value: string; badge?: string }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="text-sm font-semibold text-slate-500">{label}</div><div className="mt-2">{badge ? <StatusBadge label={value} tone={badge === 'active' ? 'success' : badge === 'cancelled' ? 'danger' : 'neutral'} /> : <span className="text-lg font-bold text-slateDark">{value}</span>}</div></div>;
}
