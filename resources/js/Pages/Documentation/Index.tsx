import { Head, Link } from '@inertiajs/react';
import { BarChart3, BookOpen, CheckCircle2, ClipboardList, FileQuestion, GraduationCap, MonitorCheck, School, ShieldCheck, UserCheck, Users } from 'lucide-react';
import { ReactNode } from 'react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Step = {
    title: string;
    body: string;
    href?: string;
};

type Guide = {
    title: string;
    audience: string;
    icon: ReactNode;
    steps: Step[];
};

const coreFlow: Step[] = [
    { title: 'Prepare your institution setup', body: 'Confirm the school, center, programme, course, class, group, batch, or candidate group records needed for the exam context.' },
    { title: 'Create question sources', body: 'Create subjects or course/module question banks, then add approved questions with correct options and marks.' },
    { title: 'Create the exam or assessment', body: 'Open Exams or Assessments, select participants, select question banks, set timing, pass mark, delivery mode, and supervision settings.' },
    { title: 'Generate candidate papers', body: 'After saving the exam, open the exam details and generate papers so each candidate receives a secure paper from the selected banks.' },
    { title: 'Monitor and submit', body: 'Use Monitor during live exams. Candidates write through the exam portal and submit before the server-controlled end time.' },
    { title: 'Review and export results', body: 'Open Results, review submissions, open individual marked papers where permitted, then export CSV or PDF summaries.' },
];

const guides: Guide[] = [
    {
        title: 'Secondary School Admin',
        audience: 'For terminal exams and school assessments.',
        icon: <School className="h-5 w-5" />,
        steps: [
            { title: 'Set up academics', body: 'Create academic sessions, terms, classes, arms, students, student groups, teachers, and assigned subjects.', href: '/secondary-school/academic-sessions' },
            { title: 'Prepare questions', body: 'Create subjects, question banks, and questions for the school subjects.', href: '/question-bank' },
            { title: 'Create terminal exam or assessment', body: 'Use Exams for terminal exams or Assessments for class/internal assessments. Select the student group and subject rows.', href: '/exams/create' },
            { title: 'Generate papers and monitor', body: 'Open the saved exam, generate papers, then use Monitor during the exam window.' },
            { title: 'Get results', body: 'Open Results, select the exam, review submitted attempts, and export CSV/PDF.', href: '/results' },
        ],
    },
    {
        title: 'Teacher',
        audience: 'For assigned subjects only.',
        icon: <GraduationCap className="h-5 w-5" />,
        steps: [
            { title: 'Review assigned scope', body: 'Dashboard, Subjects, Question Bank, Questions, Assessments, and Results show only your assigned school subjects.', href: '/dashboard' },
            { title: 'Create subject question banks', body: 'Create or update question banks only for assigned subjects.', href: '/question-bank' },
            { title: 'Add questions', body: 'Add, update, delete, or import questions in your assigned subject banks.', href: '/questions' },
            { title: 'Create assessment', body: 'Create assessments only. Choose student group, question bank selection, timing, and paper rules.', href: '/exams/create?category=assessment' },
            { title: 'View results', body: 'Open Results to review submissions and export result files for your assigned-subject assessments.', href: '/results' },
        ],
    },
    {
        title: 'Professional School Admin',
        audience: 'For professional, certification, practice, and assessment exams.',
        icon: <Users className="h-5 w-5" />,
        steps: [
            { title: 'Set up training structure', body: 'Create programmes, courses, modules, training batches, candidates/trainees, and facilitators.', href: '/professional-schools' },
            { title: 'Assign facilitators', body: 'Open Facilitators and assign one or more courses. Modules are managed through the selected courses.' },
            { title: 'Create course/module banks', body: 'Create question banks under the professional school course and module structure.', href: '/professional-schools' },
            { title: 'Create exams', body: 'Create traditional, adaptive, certification, practice, or assessment exams with course/module paper rows.', href: '/exams/create' },
            { title: 'Results and certificates', body: 'Review Results after submission. Use certification/professional settings where certificate workflows apply.', href: '/results' },
        ],
    },
    {
        title: 'Facilitator',
        audience: 'For assigned professional courses only.',
        icon: <BookOpen className="h-5 w-5" />,
        steps: [
            { title: 'Review assigned courses', body: 'Dashboard, Courses, Modules, Question Bank, Questions, Assessments, and Results are scoped to assigned courses.', href: '/dashboard' },
            { title: 'Create question banks', body: 'Create course/module question banks. The subject is created automatically from the selected module.' },
            { title: 'Add course questions', body: 'Create, update, delete, or import questions inside assigned course/module banks.' },
            { title: 'Create assessment', body: 'Facilitators create assessments only. Select course/module banks, trainees, timing, and assessment settings.', href: '/exams/create?category=assessment' },
            { title: 'Download results', body: 'Open Results to view and export submitted assessment results for assigned courses.', href: '/results' },
        ],
    },
    {
        title: 'CBT Center Admin',
        audience: 'For center-based CBT exams and candidate groups.',
        icon: <MonitorCheck className="h-5 w-5" />,
        steps: [
            { title: 'Register candidates', body: 'Create candidates, import candidates, and organize candidate groups for the center.' },
            { title: 'Create CBT question bank', body: 'Create center question banks and add questions for the CBT exam.', href: '/questions' },
            { title: 'Create exam', body: 'Create the CBT exam, select question bank/candidate groups, set timing and supervision rules.', href: '/exams/create' },
            { title: 'Monitor live exam', body: 'Use Monitor for live attempts, suspicious events, resets, and exam ending actions.' },
            { title: 'Export results', body: 'Open Results, review attempts, and download CSV or PDF result summaries.', href: '/results' },
        ],
    },
    {
        title: 'Exam Manager / Examiner',
        audience: 'For organization-level exams and broad exam operations.',
        icon: <ClipboardList className="h-5 w-5" />,
        steps: [
            { title: 'Prepare candidates and groups', body: 'Create candidates or candidate groups for the exam owner context.', href: '/candidate-groups' },
            { title: 'Prepare banks and questions', body: 'Create question banks and approved questions for the selected owner context.', href: '/question-bank' },
            { title: 'Configure exam', body: 'Create the exam, choose participant source, paper selection, timing, pass mark, and security settings.', href: '/exams/create' },
            { title: 'Operate exam', body: 'Generate papers before candidates write, monitor live attempts, and refresh participants when needed.' },
            { title: 'Release/report results', body: 'Use Results and Reports for review, export, and operational reporting.', href: '/results' },
        ],
    },
    {
        title: 'Supervisor / Proctor',
        audience: 'For live exam monitoring.',
        icon: <ShieldCheck className="h-5 w-5" />,
        steps: [
            { title: 'Open assigned exam monitor', body: 'Open the exam monitor from an exam details page or assigned monitoring area.' },
            { title: 'Watch live attempts', body: 'Review candidate status, submission state, tab events, focus loss, webcam/fullscreen incidents, and reconnect activity.' },
            { title: 'Intervene when needed', body: 'Apply allowed resets, incident reviews, or end-exam actions based on your policy permissions.' },
            { title: 'Hand over for results', body: 'After submission, result managers or admins review exported result records from Results.' },
        ],
    },
    {
        title: 'Result Reviewer',
        audience: 'For reviewing, downloading, and sharing outcomes.',
        icon: <BarChart3 className="h-5 w-5" />,
        steps: [
            { title: 'Open results dashboard', body: 'Go to Results to see exams with submitted attempts.', href: '/results' },
            { title: 'Review exam result', body: 'Open an exam to see total submissions, pass/fail counts, scores, grades, and adaptive analysis where available.' },
            { title: 'Review candidate result', body: 'Open a candidate attempt to view answer rows and marked paper details where your role allows it.' },
            { title: 'Export files', body: 'Use CSV for spreadsheet analysis and PDF for a printable result summary.' },
        ],
    },
    {
        title: 'Candidate',
        audience: 'For writing an assigned exam.',
        icon: <UserCheck className="h-5 w-5" />,
        steps: [
            { title: 'Login to exam portal', body: 'Open the candidate exam link and sign in with the assigned exam credentials or access code.' },
            { title: 'Read instructions', body: 'Confirm exam title, timing, rules, browser requirements, and submission instructions.' },
            { title: 'Write and submit', body: 'Answer questions, keep the browser in the required mode, and submit before time ends. The server controls timing and final submission.' },
            { title: 'View released result', body: 'If result release is enabled, use the candidate result page or public verification flow provided by the school/center.' },
        ],
    },
];

const quickLinks = [
    { label: 'Create Exam', href: '/exams/create' },
    { label: 'Question Banks', href: '/question-bank' },
    { label: 'Questions', href: '/questions' },
    { label: 'Results', href: '/results' },
    { label: 'Candidate Groups', href: '/candidate-groups' },
];

export default function DocumentationIndex() {
    return (
        <PortalAppShell title="Documentation">
            <Head title="Documentation" />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="User guide"
                    title="Documentation"
                    description="Role-based workflows for preparing questions, creating exams or assessments, monitoring submissions, and downloading results."
                    actions={<Button asChild type="button"><Link href="/dashboard">Back to Dashboard</Link></Button>}
                />

                <section className="mb-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="flex items-start gap-3">
                        <div className="rounded-md bg-green-50 p-2 text-primary"><CheckCircle2 className="h-5 w-5" /></div>
                        <div>
                            <h2 className="font-semibold text-slateDark">Complete Exam-To-Result Flow</h2>
                            <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {coreFlow.map((step, index) => <StepCard key={step.title} index={index + 1} step={step} />)}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="mb-6 grid gap-3 md:grid-cols-5">
                    {quickLinks.map((link) => (
                        <Link key={link.href} href={link.href} className="rounded-md border border-border bg-white px-4 py-3 text-sm font-semibold text-slateDark shadow-sm transition hover:border-primary hover:text-primary">
                            {link.label}
                        </Link>
                    ))}
                </section>

                <section className="grid gap-5 lg:grid-cols-2">
                    {guides.map((guide) => (
                        <article key={guide.title} className="rounded-md border border-border bg-white p-5 shadow-sm">
                            <div className="flex items-start gap-3">
                                <div className="rounded-md bg-slate-100 p-2 text-primary">{guide.icon}</div>
                                <div>
                                    <h2 className="text-lg font-semibold text-slateDark">{guide.title}</h2>
                                    <p className="mt-1 text-sm text-slate-600">{guide.audience}</p>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                {guide.steps.map((step, index) => <StepCard key={step.title} index={index + 1} step={step} compact />)}
                            </div>
                        </article>
                    ))}
                </section>

                <section className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="flex items-start gap-3">
                        <div className="rounded-md bg-amber-50 p-2 text-amber-600"><FileQuestion className="h-5 w-5" /></div>
                        <div>
                            <h2 className="font-semibold text-slateDark">Result Download Notes</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-600">
                                Results appear after candidates submit or are auto-submitted. Use CSV exports for analysis, PDF summaries for printable records, and marked paper PDFs for detailed candidate review where your role is allowed.
                            </p>
                        </div>
                    </div>
                </section>
            </section>
        </PortalAppShell>
    );
}

function StepCard({ index, step, compact = false }: { index: number; step: Step; compact?: boolean }) {
    return (
        <div className={`rounded-md border border-border bg-slate-50 ${compact ? 'p-3' : 'p-4'}`}>
            <div className="flex items-start gap-3">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{index}</span>
                <div>
                    <h3 className="font-semibold text-slateDark">{step.title}</h3>
                    <p className="mt-1 text-sm leading-6 text-slate-600">{step.body}</p>
                    {step.href && <Link href={step.href} className="mt-2 inline-flex text-sm font-semibold text-primary hover:underline">Open page</Link>}
                </div>
            </div>
        </div>
    );
}
