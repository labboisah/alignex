import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpenCheck, Download, GraduationCap, Save, Users } from 'lucide-react';
import { FormEvent, useMemo, type ReactNode } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Session = { id: string; name: string; code: string; status: string; terms_count: number; terms: { id: string; name: string; code: string; status: string }[] };
type ClassLevel = { id: string; name: string; code: string; level_order: number; status: string; groups_count: number; groups: { id: string; name: string; code: string; status: string; candidates_count?: number }[] };
type Exam = { id: string; title: string; exam_code: string; settings?: Record<string, unknown> };
type Option = { id: string; name: string; code?: string; registration_number?: string };
type ResultRow = Record<string, unknown> & { id: string; candidate_id: string; candidate_name: string; registration_number: string; subject: string; ca_score: number; exam_score: number; total_score: number; grade: string; teacher_comment?: string | null };
type WeaknessRow = Record<string, unknown> & { candidate_name: string; registration_number: string; subject: string; topic: string; score_percentage: number; mastery_level: string };
type Dashboard = { secondary_exams: number; students: number; classes: number; assessments_recorded: number; average_total: number };

export default function SecondarySchoolIndex({
    dashboard,
    sessions,
    classes,
    exams,
    candidates,
    subjects,
    selected_exam_id,
    result_sheet,
    weaknesses,
}: {
    dashboard: Dashboard;
    sessions: Session[];
    classes: ClassLevel[];
    exams: Exam[];
    candidates: Option[];
    subjects: Option[];
    selected_exam_id?: string | null;
    result_sheet: ResultRow[];
    weaknesses: WeaknessRow[];
}) {
    const selectedExam = exams.find((exam) => exam.id === selected_exam_id) ?? exams[0] ?? null;

    return (
        <PortalAppShell title="Secondary School">
            <Head title="Secondary School" />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="School Exams"
                    title="Secondary School Workspace"
                    description="Manage academic sessions, terms, classes, groups, continuous assessment, report cards, and weakness reports."
                    actions={<Button asChild type="button" variant="secondary"><Link href="/dashboard"><ArrowLeft className="h-4 w-4" />Back</Link></Button>}
                />

                <div className="grid gap-4 md:grid-cols-5">
                    <Metric label="Secondary Exams" value={dashboard.secondary_exams} icon={BookOpenCheck} />
                    <Metric label="Students" value={dashboard.students} icon={Users} />
                    <Metric label="Classes" value={dashboard.classes} icon={GraduationCap} />
                    <Metric label="CA Records" value={dashboard.assessments_recorded} icon={Save} />
                    <Metric label="Average Total" value={dashboard.average_total} icon={BookOpenCheck} />
                </div>

                <div className="mt-6 grid gap-5 xl:grid-cols-[24rem_minmax(0,1fr)]">
                    <div className="space-y-5">
                        <SessionPanel sessions={sessions} />
                        <TermPanel sessions={sessions} />
                        <ClassPanel classes={classes} />
                        <GroupPanel classes={classes} candidates={candidates} />
                    </div>
                    <div className="space-y-5">
                        <ExamSelector exams={exams} selectedExam={selectedExam} />
                        {selectedExam && <CaSetupPanel exam={selectedExam} sessions={sessions} classes={classes} />}
                        {selectedExam && <AssessmentPanel exam={selectedExam} candidates={candidates} subjects={subjects} />}
                        {selectedExam && <ResultSheet exam={selectedExam} rows={result_sheet} />}
                        <WeaknessReport rows={weaknesses} />
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function SessionPanel({ sessions }: { sessions: Session[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', starts_on: '', ends_on: '', status: 'active' });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/secondary-school/sessions', { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <Panel title="Academic Sessions">
            <form onSubmit={submit} className="space-y-3">
                <Input label="Name" value={data.name} error={errors.name} onChange={(value) => setData('name', value)} />
                <Input label="Code" value={data.code} error={errors.code} onChange={(value) => setData('code', value)} />
                <div className="grid gap-3 sm:grid-cols-2">
                    <Input label="Starts" type="date" value={data.starts_on} error={errors.starts_on} onChange={(value) => setData('starts_on', value)} />
                    <Input label="Ends" type="date" value={data.ends_on} error={errors.ends_on} onChange={(value) => setData('ends_on', value)} />
                </div>
                <Button type="submit" disabled={processing} className="w-full"><Save className="h-4 w-4" />Save Session</Button>
            </form>
            <List items={sessions.map((session) => `${session.name} (${session.code}) - ${session.terms_count} terms`)} />
        </Panel>
    );
}

function TermPanel({ sessions }: { sessions: Session[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ academic_session_id: sessions[0]?.id ?? '', name: '', code: '', starts_on: '', ends_on: '', status: 'active' });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/secondary-school/terms', { preserveScroll: true, onSuccess: () => reset('name', 'code', 'starts_on', 'ends_on') });
    };

    return (
        <Panel title="Terms">
            <form onSubmit={submit} className="space-y-3">
                <Select label="Session" value={data.academic_session_id} error={errors.academic_session_id} options={sessions.map((row) => ({ value: row.id, label: row.name }))} onChange={(value) => setData('academic_session_id', value)} />
                <Input label="Name" value={data.name} error={errors.name} onChange={(value) => setData('name', value)} />
                <Input label="Code" value={data.code} error={errors.code} onChange={(value) => setData('code', value)} />
                <Button type="submit" disabled={processing || !data.academic_session_id} className="w-full"><Save className="h-4 w-4" />Save Term</Button>
            </form>
            <List items={sessions.flatMap((session) => session.terms.map((term) => `${session.name}: ${term.name}`))} />
        </Panel>
    );
}

function ClassPanel({ classes }: { classes: ClassLevel[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', level_order: '1', status: 'active' });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/secondary-school/classes', { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <Panel title="Classes/Levels">
            <form onSubmit={submit} className="space-y-3">
                <Input label="Name" value={data.name} error={errors.name} onChange={(value) => setData('name', value)} />
                <Input label="Code" value={data.code} error={errors.code} onChange={(value) => setData('code', value)} />
                <Input label="Order" type="number" value={data.level_order} error={errors.level_order} onChange={(value) => setData('level_order', value)} />
                <Button type="submit" disabled={processing} className="w-full"><Save className="h-4 w-4" />Save Class</Button>
            </form>
            <List items={classes.map((row) => `${row.name} (${row.code}) - ${row.groups_count} groups`)} />
        </Panel>
    );
}

function GroupPanel({ classes, candidates }: { classes: ClassLevel[]; candidates: Option[] }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ school_class_id: string; name: string; code: string; status: string; candidate_ids: string[] }>({ school_class_id: classes[0]?.id ?? '', name: '', code: '', status: 'active', candidate_ids: [] });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/secondary-school/groups', { preserveScroll: true, onSuccess: () => reset('name', 'code', 'candidate_ids') });
    };

    return (
        <Panel title="Student Groups">
            <form onSubmit={submit} className="space-y-3">
                <Select label="Class" value={data.school_class_id} error={errors.school_class_id} options={classes.map((row) => ({ value: row.id, label: row.name }))} onChange={(value) => setData('school_class_id', value)} />
                <Input label="Name" value={data.name} error={errors.name} onChange={(value) => setData('name', value)} />
                <Input label="Code" value={data.code} error={errors.code} onChange={(value) => setData('code', value)} />
                <label className="block text-sm font-semibold text-slateDark">
                    Students
                    <select multiple className="mt-1 block min-h-28 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.candidate_ids} onChange={(event) => setData('candidate_ids', Array.from(event.target.selectedOptions).map((option) => option.value))}>
                        {candidates.map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name} ({candidate.registration_number})</option>)}
                    </select>
                </label>
                <Button type="submit" disabled={processing || !data.school_class_id} className="w-full"><Save className="h-4 w-4" />Save Group</Button>
            </form>
            <List items={classes.flatMap((row) => row.groups.map((group) => `${row.name}: ${group.name} (${group.candidates_count ?? 0})`))} />
        </Panel>
    );
}

function ExamSelector({ exams, selectedExam }: { exams: Exam[]; selectedExam: Exam | null }) {
    return (
        <Panel title="Teacher Dashboard">
            <label className="block text-sm font-semibold text-slateDark">
                Secondary Exam
                <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={selectedExam?.id ?? ''} onChange={(event) => router.visit(`/secondary-school?exam_id=${event.target.value}`, { preserveScroll: true })}>
                    {exams.map((exam) => <option key={exam.id} value={exam.id}>{exam.title} ({exam.exam_code})</option>)}
                </select>
            </label>
        </Panel>
    );
}

function CaSetupPanel({ exam, sessions, classes }: { exam: Exam; sessions: Session[]; classes: ClassLevel[] }) {
    const allTerms = sessions.flatMap((session) => session.terms.map((term) => ({ ...term, session_name: session.name })));
    const allGroups = classes.flatMap((row) => row.groups.map((group) => ({ ...group, class_name: row.name })));
    const settings = exam.settings ?? {};
    const { data, setData, patch, processing, errors } = useForm({
        academic_session_id: String(settings.secondary_academic_session_id ?? sessions[0]?.id ?? ''),
        academic_term_id: String(settings.secondary_academic_term_id ?? allTerms[0]?.id ?? ''),
        school_class_id: String(settings.secondary_school_class_id ?? classes[0]?.id ?? ''),
        student_group_id: String(settings.secondary_student_group_id ?? allGroups[0]?.id ?? ''),
        ca_max_score: String(settings.secondary_ca_max_score ?? 30),
        exam_max_score: String(settings.secondary_exam_max_score ?? 70),
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(`/secondary-school/exams/${exam.id}/ca-setup`, { preserveScroll: true });
    };

    return (
        <Panel title="Continuous Assessment Setup">
            <form onSubmit={submit} className="grid gap-3 md:grid-cols-3">
                <Select label="Session" value={data.academic_session_id} error={errors.academic_session_id} options={sessions.map((row) => ({ value: row.id, label: row.name }))} onChange={(value) => setData('academic_session_id', value)} />
                <Select label="Term" value={data.academic_term_id} error={errors.academic_term_id} options={allTerms.map((row) => ({ value: row.id, label: `${row.session_name} - ${row.name}` }))} onChange={(value) => setData('academic_term_id', value)} />
                <Select label="Class" value={data.school_class_id} error={errors.school_class_id} options={classes.map((row) => ({ value: row.id, label: row.name }))} onChange={(value) => setData('school_class_id', value)} />
                <Select label="Group" value={data.student_group_id} error={errors.student_group_id} options={allGroups.map((row) => ({ value: row.id, label: `${row.class_name} - ${row.name}` }))} onChange={(value) => setData('student_group_id', value)} />
                <Input label="CA Max" type="number" value={data.ca_max_score} error={errors.ca_max_score} onChange={(value) => setData('ca_max_score', value)} />
                <Input label="Exam Max" type="number" value={data.exam_max_score} error={errors.exam_max_score} onChange={(value) => setData('exam_max_score', value)} />
                <Button type="submit" disabled={processing} className="md:col-span-3"><Save className="h-4 w-4" />Save CA Setup</Button>
            </form>
        </Panel>
    );
}

function AssessmentPanel({ exam, candidates, subjects }: { exam: Exam; candidates: Option[]; subjects: Option[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ candidate_id: candidates[0]?.id ?? '', subject_id: subjects[0]?.id ?? '', ca_score: '', teacher_comment: '' });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(`/secondary-school/exams/${exam.id}/assessments`, { preserveScroll: true, onSuccess: () => reset('ca_score', 'teacher_comment') });
    };

    return (
        <Panel title="Record Continuous Assessment">
            <form onSubmit={submit} className="grid gap-3 md:grid-cols-4">
                <Select label="Student" value={data.candidate_id} error={errors.candidate_id} options={candidates.map((row) => ({ value: row.id, label: `${row.name} (${row.registration_number})` }))} onChange={(value) => setData('candidate_id', value)} />
                <Select label="Subject" value={data.subject_id} error={errors.subject_id} options={subjects.map((row) => ({ value: row.id, label: row.name }))} onChange={(value) => setData('subject_id', value)} />
                <Input label="CA Score" type="number" value={data.ca_score} error={errors.ca_score} onChange={(value) => setData('ca_score', value)} />
                <Input label="Comment" value={data.teacher_comment} error={errors.teacher_comment} onChange={(value) => setData('teacher_comment', value)} />
                <Button type="submit" disabled={processing || !data.candidate_id || !data.subject_id} className="md:col-span-4"><Save className="h-4 w-4" />Record CA</Button>
            </form>
        </Panel>
    );
}

function ResultSheet({ exam, rows }: { exam: Exam; rows: ResultRow[] }) {
    const reportableCandidates = useMemo(() => Array.from(new Map(rows.map((row) => [row.registration_number, row])).values()), [rows]);
    return (
        <section>
            <h2 className="mb-3 text-lg font-semibold text-slateDark">Subject Result Sheet</h2>
            <DataTable<ResultRow>
                rows={rows}
                emptyTitle="No CA/result records"
                columns={[
                    { key: 'candidate_name', header: 'Student', render: (row) => <span className="font-semibold text-slateDark">{row.candidate_name}</span> },
                    { key: 'registration_number', header: 'Registration Number' },
                    { key: 'subject', header: 'Subject' },
                    { key: 'ca_score', header: 'CA' },
                    { key: 'exam_score', header: 'Exam' },
                    { key: 'total_score', header: 'Total' },
                    { key: 'grade', header: 'Grade', render: (row) => <StatusBadge label={row.grade} tone={row.grade === 'F' ? 'danger' : row.grade === 'A' ? 'success' : 'warning'} /> },
                    { key: 'teacher_comment', header: 'Comment', render: (row) => row.teacher_comment ?? 'N/A' },
                ]}
            />
            {reportableCandidates.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {reportableCandidates.map((row) => (
                        <Button key={row.registration_number} asChild type="button" variant="secondary">
                            <a href={`/secondary-school/exams/${exam.id}/candidates/${row.candidate_id}/report-card.pdf`}><Download className="h-4 w-4" />{row.candidate_name}</a>
                        </Button>
                    ))}
                </div>
            )}
        </section>
    );
}

function WeaknessReport({ rows }: { rows: WeaknessRow[] }) {
    return (
        <section>
            <h2 className="mb-3 text-lg font-semibold text-slateDark">Student Weakness Report</h2>
            <DataTable<WeaknessRow>
                rows={rows}
                emptyTitle="No weak mastery areas"
                columns={[
                    { key: 'candidate_name', header: 'Student', render: (row) => <span className="font-semibold text-slateDark">{row.candidate_name}</span> },
                    { key: 'registration_number', header: 'Registration Number' },
                    { key: 'subject', header: 'Subject' },
                    { key: 'topic', header: 'Topic' },
                    { key: 'score_percentage', header: 'Score', render: (row) => `${row.score_percentage}%` },
                    { key: 'mastery_level', header: 'Mastery', render: (row) => <StatusBadge label={row.mastery_level} tone="danger" /> },
                ]}
            />
        </section>
    );
}

function Metric({ label, value, icon: Icon }: { label: string; value: string | number; icon: typeof BookOpenCheck }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><Icon className="h-5 w-5 text-primary" /><div className="mt-3 text-sm font-semibold text-slate-500">{label}</div><div className="mt-1 text-2xl font-bold text-slateDark">{value}</div></div>;
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return <section className="rounded-md border border-border bg-white p-4 shadow-sm"><h2 className="mb-4 font-semibold text-slateDark">{title}</h2>{children}</section>;
}

function List({ items }: { items: string[] }) {
    return <div className="mt-4 max-h-36 space-y-1 overflow-auto text-sm text-slate-600">{items.length === 0 ? <div>None yet</div> : items.map((item) => <div key={item} className="rounded-md bg-slate-50 px-3 py-2">{item}</div>)}</div>;
}

function Input({ label, value, error, type = 'text', onChange }: { label: string; value: string; error?: string; type?: string; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<input className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" type={type} value={value} onChange={(event) => onChange(event.target.value)} />{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}

function Select({ label, value, error, options, onChange }: { label: string; value: string; error?: string; options: { value: string; label: string }[]; onChange: (value: string) => void }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}<select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={value} onChange={(event) => onChange(event.target.value)}><option value="">Select</option>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
