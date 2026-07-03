import { Head, Link, router, useForm } from '@inertiajs/react';
import { BookOpen, ClipboardList, Pencil, Plus, Upload, Users } from 'lucide-react';
import { FormEvent } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { CandidateRow, CbtCenter, ExamRow, OptionRow, QuestionBankRow } from './types';

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function Show({ center, candidates, questionBanks, recentExams, assignableExams, can }: { center: CbtCenter; candidates: CandidateRow[]; questionBanks: QuestionBankRow[]; recentExams: ExamRow[]; assignableExams: OptionRow[]; can: { update: boolean; manage: boolean } }) {
    const { data, setData, post, processing, errors } = useForm({ exam_id: '', status: 'assigned' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(`/cbt-centers/${center.id}/external-exams`, { preserveScroll: true });
    };

    return (
        <PortalAppShell title={center.name}>
            <Head title={center.name} />
            <PageHeader
                eyebrow="CBT Center"
                title={center.name}
                description={`${center.code} | ${center.location}`}
                actions={
                    <ProtectedAction allowed={can.update}>
                        <Button asChild variant="secondary"><Link href={`/cbt-centers/${center.id}/edit`}><Pencil className="h-4 w-4" />Edit</Link></Button>
                    </ProtectedAction>
                }
            />
            <div className="grid gap-4 md:grid-cols-4">
                <Metric label="Capacity" value={String(center.capacity)} />
                <Metric label="Candidates" value={String(center.candidates_count ?? candidates.length)} icon={Users} />
                <Metric label="Question Banks" value={String(center.question_banks_count ?? questionBanks.length)} icon={BookOpen} />
                <Metric label="Exams" value={String(center.exams_count ?? recentExams.length)} icon={ClipboardList} />
            </div>
            <div className="mt-5 flex flex-wrap gap-2">
                <Button asChild variant="secondary"><Link href={`/cbt-centers/${center.id}/candidates`}><Users className="h-4 w-4" />Candidates</Link></Button>
                <Button asChild variant="secondary"><Link href={`/cbt-centers/${center.id}/question-banks`}><BookOpen className="h-4 w-4" />Question Bank</Link></Button>
                <Button asChild variant="secondary"><Link href="/exams/create"><Plus className="h-4 w-4" />Create Exam</Link></Button>
                <Button asChild variant="secondary"><Link href="/results">Results</Link></Button>
            </div>
            {assignableExams.length > 0 && (
                <form onSubmit={submit} className="mt-6">
                    <FormSection title="External Exam Assignment" description="Assign an existing organization exam to this CBT center for center-based delivery." footer={<Button disabled={processing}><Upload className="h-4 w-4" />Assign Exam</Button>}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <label className="block text-sm font-semibold text-slateDark">Exam<select className={inputClass} value={data.exam_id} onChange={(event) => setData('exam_id', event.target.value)}><option value="">Choose exam</option>{assignableExams.map((exam) => <option key={exam.id} value={exam.id}>{exam.name} ({exam.code})</option>)}</select>{errors.exam_id && <span className="text-sm text-danger">{errors.exam_id}</span>}</label>
                            <label className="block text-sm font-semibold text-slateDark">Status<select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}><option value="assigned">Assigned</option><option value="active">Active</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label>
                        </div>
                    </FormSection>
                </form>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <DataTable rows={candidates} emptyTitle="No candidates" columns={[{ key: 'registration_number', header: 'Registration' }, { key: 'full_name', header: 'Name' }, { key: 'email', header: 'Email' }, { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> }]} />
                <DataTable rows={questionBanks} emptyTitle="No question banks" columns={[{ key: 'name', header: 'Name' }, { key: 'questions_count', header: 'Questions' }, { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> }]} />
            </div>
            <div className="mt-6">
                <DataTable rows={recentExams} emptyTitle="No center exams" columns={[{ key: 'title', header: 'Exam' }, { key: 'code', header: 'Code' }, { key: 'category', header: 'Category' }, { key: 'mode', header: 'Mode' }, { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} tone={row.status === 'active' ? 'success' : 'neutral'} /> }]} />
            </div>
        </PortalAppShell>
    );
}

function Metric({ label, value, icon: Icon }: { label: string; value: string; icon?: typeof Users }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="flex items-center gap-2 text-sm font-semibold text-slate-500">{Icon && <Icon className="h-4 w-4 text-primary" />}{label}</div><div className="mt-2 text-xl font-bold text-slateDark">{value}</div></div>;
}
