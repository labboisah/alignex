import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    BookOpen,
    Building2,
    CheckCircle2,
    ClipboardList,
    Download,
    FileQuestion,
    GraduationCap,
    MonitorCheck,
    PlayCircle,
    School,
    ShieldCheck,
    UserCheck,
    Users,
} from 'lucide-react';
import { ReactNode } from 'react';
import { Button } from '@/Components/ui/button';

type Step = { title: string; body: string; href?: string };
type Section = { title: string; summary: string; icon: ReactNode; steps: Step[]; video: VideoSlot };
type VideoSlot = { title: string; placement: string; script: string };

const completeFlow: Step[] = [
    { title: 'Create institution structure', body: 'Set up the organization, secondary school, professional school, CBT center, classes, courses, modules, batches, groups, and users for the exam owner.' },
    { title: 'Add question structure', body: 'Create subjects or course/module question banks, then add questions with options, marks, difficulty, explanation, and approval status.' },
    { title: 'Register candidates', body: 'Register students, trainees, applicants, or CBT candidates. Group them with student groups, training batches, candidate groups, or direct exam assignment.' },
    { title: 'Create exam or assessment', body: 'Choose the correct exam type, owner context, participant source, question banks, timing, pass mark, mode, delivery settings, and security settings.' },
    { title: 'Generate papers', body: 'Open the saved exam and generate candidate papers. The system uses selected question banks and paper rules to prepare each candidate paper.' },
    { title: 'Candidate writes exam', body: 'Candidate logs into /exam/login, reads instructions, writes the exam, and submits. Server state controls timing and submission.' },
    { title: 'Monitor activity', body: 'Supervisors monitor status, incidents, focus changes, fullscreen events, tab events, resets, and submission state during the exam.' },
    { title: 'Review and export results', body: 'Open Results, review submissions, candidate marked papers, dashboards, and export CSV or PDF result files.' },
];

const sections: Section[] = [
    {
        title: 'Super Admin',
        summary: 'Owns platform setup, organizations, access control, and global oversight.',
        icon: <ShieldCheck className="h-5 w-5" />,
        video: {
            title: 'Platform Setup Walkthrough',
            placement: 'Place a 3-5 minute video here showing organization creation, role permissions, and switching into each institution context.',
            script: 'Show login, dashboard, organizations, access controls, admin registrations, then where admins continue the exam setup flow.',
        },
        steps: [
            { title: 'Create or approve organizations', body: 'Use Organizations and Admin Registrations to onboard schools, centers, and exam teams.', href: '/register-admin' },
            { title: 'Configure roles and permissions', body: 'Use Access Controls in the portal to decide which roles can manage schools, question banks, exams, monitoring, reports, and settings.' },
            { title: 'Create institution admins', body: 'Assign secondary school admins, professional school admins, CBT center admins, examiners, and supervisors to their correct scope.' },
            { title: 'Audit platform health', body: 'Use dashboards, reports, and context switching to review exam readiness across institutions.' },
        ],
    },
    {
        title: 'Secondary School Admin',
        summary: 'Creates academic structure, teachers, students, terminal exams, assessments, and school results.',
        icon: <School className="h-5 w-5" />,
        video: {
            title: 'Secondary School Exam Setup',
            placement: 'Place a video after this card showing academic session, class, student group, subject bank, exam creation, paper generation, and result export.',
            script: 'Record a full term exam setup: create session, term, class, student group, subject, question bank, questions, exam, generate papers, view results.',
        },
        steps: [
            { title: 'Set academic calendar', body: 'Create academic sessions and terms so terminal exams can be attached to the correct period.' },
            { title: 'Create class structure', body: 'Create classes, arms, students, and student groups. Student groups are used when assigning candidates to exams.' },
            { title: 'Register teachers', body: 'Create teachers and assign the subjects they can manage. Teachers only see their assigned subjects and assessments.' },
            { title: 'Create subjects and banks', body: 'Create school subjects, question banks, and questions. Keep approved questions ready before generating papers.' },
            { title: 'Create terminal exam or assessment', body: 'Use Exams for term/terminal exams. Use Assessments for class tests or internal assessments.' },
            { title: 'Generate papers', body: 'Open the exam details page and generate papers before candidates write.' },
            { title: 'Monitor and export results', body: 'Use Monitor during the exam, then Results to review submissions and export CSV/PDF.' },
        ],
    },
    {
        title: 'Teacher',
        summary: 'Creates question banks, questions, assessments, and views results for assigned subjects only.',
        icon: <GraduationCap className="h-5 w-5" />,
        video: {
            title: 'Teacher Assessment Walkthrough',
            placement: 'Place a short video here showing teacher dashboard, question bank creation, question entry, assessment setup, and result download.',
            script: 'Show that the teacher sidebar has no large dropdowns and only displays assigned subject data.',
        },
        steps: [
            { title: 'Open teacher dashboard', body: 'Review assigned subjects, classes, student groups, recent students, assessments, and result activity.' },
            { title: 'Create question bank', body: 'Open Question Bank and create a bank under an assigned subject.' },
            { title: 'Create questions', body: 'Open Questions and add objective questions with options, correct answer, marks, difficulty, and status.' },
            { title: 'Create assessment only', body: 'Teachers cannot create terminal exams. Use Assessments to select candidates/student groups and question banks.' },
            { title: 'Monitor assessment', body: 'Open the assessment details and monitor submissions where allowed.' },
            { title: 'Download results', body: 'Open Results, select the assessment, and export result CSV/PDF.' },
        ],
    },
    {
        title: 'Professional School Admin',
        summary: 'Manages programmes, courses, modules, batches, facilitators, professional exams, certification, and results.',
        icon: <Users className="h-5 w-5" />,
        video: {
            title: 'Professional School Full Workflow',
            placement: 'Place a comprehensive professional school video here: programme to certificates.',
            script: 'Show programme, course, module, batch, trainee, facilitator assignment, question banks, exam creation, result review, and certificate verification.',
        },
        steps: [
            { title: 'Create programmes, courses, modules', body: 'Professional exams use programme/course/module structure. Modules are the main unit for course question banks.' },
            { title: 'Create training batches and trainees', body: 'Training batches connect trainees to the professional school exam flow.' },
            { title: 'Register facilitators', body: 'Open Facilitators and assign courses. Facilitators automatically work with modules under their assigned courses.' },
            { title: 'Create course/module question banks', body: 'Open Question Banks under the professional school and attach banks to courses and modules.' },
            { title: 'Create exams or assessments', body: 'Professional school admins can create traditional, adaptive, professional, certification, practice, and assessment exams.' },
            { title: 'Review results and certificates', body: 'Use Results for scores and professional/certification pages for certificate workflows where enabled.' },
        ],
    },
    {
        title: 'Facilitator',
        summary: 'Manages assigned professional courses, modules, question banks, questions, assessments, and results.',
        icon: <BookOpen className="h-5 w-5" />,
        video: {
            title: 'Facilitator Course Assessment Guide',
            placement: 'Place a facilitator video here showing assigned course scope, question bank creation without subject selection, questions, assessment, and result export.',
            script: 'Emphasize that subjects are auto-created from selected modules and all displayed data is limited to assigned courses.',
        },
        steps: [
            { title: 'Review assigned courses', body: 'Facilitator dashboard shows assigned courses, related modules, trainees, question banks, assessments, and results.' },
            { title: 'Create module question bank', body: 'Open Question Bank, choose course and module, enter bank details, and save. Subject is handled automatically.' },
            { title: 'Add questions', body: 'Create, update, delete, or import questions in assigned course/module banks.' },
            { title: 'Create assessment', body: 'Open Assessments and create course/module assessments only, using assigned question banks and trainee selection.' },
            { title: 'View and download results', body: 'Open Results to view only assigned-course assessments and export result files.' },
        ],
    },
    {
        title: 'CBT Center Admin',
        summary: 'Manages center candidates, candidate groups, question banks, CBT exams, monitoring, and results.',
        icon: <Building2 className="h-5 w-5" />,
        video: {
            title: 'CBT Center Exam Operation',
            placement: 'Place a center admin video here showing candidate import, candidate groups, center question bank, exam creation, monitor, and result export.',
            script: 'Show how center-based exams differ from school/professional exams, especially candidate groups and monitoring.',
        },
        steps: [
            { title: 'Create candidates', body: 'Register or import center candidates with registration number, contact details, status, and identity fields where required.' },
            { title: 'Create candidate groups', body: 'Group candidates for easy assignment to CBT exams.' },
            { title: 'Create question banks and questions', body: 'Create center question banks and add approved questions.' },
            { title: 'Create CBT exam', body: 'Choose question bank, candidate groups, timing, mode, delivery, and monitoring settings.' },
            { title: 'Operate live exam', body: 'Generate papers, monitor attempts, handle approved resets, and end exam where authorized.' },
            { title: 'Export result files', body: 'Open Results and download CSV/PDF summaries.' },
        ],
    },
    {
        title: 'Exam Manager / Examiner',
        summary: 'Creates and operates organization-level recruitment, assessment, certification, professional, practice, and general exams.',
        icon: <ClipboardList className="h-5 w-5" />,
        video: {
            title: 'Exam Manager End-To-End Setup',
            placement: 'Place an examiner video here showing candidate group creation, question bank setup, exam wizard, paper generation, monitor, and results.',
            script: 'Cover the exam wizard one step at a time: basic information, participants, question selection, settings, and review.',
        },
        steps: [
            { title: 'Prepare participants', body: 'Create candidates and candidate groups, or confirm the school/center/professional participant source is ready.' },
            { title: 'Prepare question banks', body: 'Create question banks and approved questions that match the selected exam owner context.' },
            { title: 'Use exam wizard', body: 'Set title, code, category, mode, delivery, dates, duration, pass mark, participant source, paper rows, and security settings.' },
            { title: 'Generate papers', body: 'Generate candidate papers from selected banks before the exam starts.' },
            { title: 'Monitor and refresh', body: 'Use monitor and participant refresh when the participant source changes before paper generation.' },
            { title: 'Analyze results', body: 'Use Results and Reports to review submissions, pass/fail, marked papers, and exports.' },
        ],
    },
    {
        title: 'Supervisor / Proctor',
        summary: 'Monitors live exams, reviews incidents, and performs permitted interventions.',
        icon: <MonitorCheck className="h-5 w-5" />,
        video: {
            title: 'Live Monitoring Guide',
            placement: 'Place a supervisor video beside this card showing live rows, event feed, suspicious activity, and reset/end actions.',
            script: 'Explain what each monitor status means and when to escalate incidents to exam managers.',
        },
        steps: [
            { title: 'Open assigned monitor', body: 'Open the monitor from the exam details page or supervisor navigation.' },
            { title: 'Watch candidate state', body: 'Track login, started, active, submitted, auto-submitted, disconnected, and disqualified states.' },
            { title: 'Review anti-cheating events', body: 'Check tab switch, focus loss, fullscreen exit, copy/paste, reconnect, and webcam-related events.' },
            { title: 'Intervene carefully', body: 'Use reset or end actions only when policy allows. Server state remains authoritative.' },
            { title: 'Hand off to results', body: 'After exam completion, result reviewers work from the Results module.' },
        ],
    },
    {
        title: 'Result Reviewer',
        summary: 'Reviews exam outcomes, marked papers, adaptive analysis, and exports.',
        icon: <BarChart3 className="h-5 w-5" />,
        video: {
            title: 'Results And Export Walkthrough',
            placement: 'Place a results video here showing results dashboard, exam result page, candidate marked paper, CSV export, and PDF export.',
            script: 'Show where CSV/PDF buttons are and explain who should receive each exported file.',
        },
        steps: [
            { title: 'Open Results', body: 'Results shows exams with submitted attempts and summary metrics.' },
            { title: 'Open exam results', body: 'Review submitted count, pass/fail counts, score distribution, and adaptive analysis where available.' },
            { title: 'Open candidate marked paper', body: 'Review selected answers, correct answers, marks, explanations, and proctoring events where your role allows it.' },
            { title: 'Export CSV', body: 'Use CSV for spreadsheet processing, filtering, ranking, and external reporting.' },
            { title: 'Export PDF', body: 'Use PDF summaries and marked paper PDF for printable or archival result records.' },
        ],
    },
    {
        title: 'Candidate',
        summary: 'Logs in, reads instructions, writes exam, submits, and checks released results.',
        icon: <UserCheck className="h-5 w-5" />,
        video: {
            title: 'Candidate Exam Experience',
            placement: 'Place a candidate-facing video here showing login, instructions, answering questions, navigation, autosave, submit, and result check.',
            script: 'Keep this video short and simple. Avoid showing answer keys, scoring rubrics, or correct-answer management screens.',
        },
        steps: [
            { title: 'Open exam link', body: 'Go to /exam/login and enter the assigned credentials or access code.' },
            { title: 'Read instructions', body: 'Confirm timing, exam rules, fullscreen/webcam requirements, allowed navigation, and submission policy.' },
            { title: 'Answer questions', body: 'Select answers carefully. Answers are saved through secure APIs while the server controls time and final state.' },
            { title: 'Submit exam', body: 'Submit before the end time. If time expires, the server can auto-submit according to exam rules.' },
            { title: 'Check released result', body: 'Use candidate result or verification pages only when the institution has released results.' },
        ],
    },
];

const videoPlan = [
    '1. Public overview video: what AlignEx is, roles, and the exam-to-result journey.',
    '2. Admin setup video: organizations, school/center setup, users, roles, and permissions.',
    '3. Question bank video: subjects/courses/modules, banks, question entry, imports, and approval status.',
    '4. Exam wizard video: participants, paper rules, timing, pass mark, security settings, and review.',
    '5. Candidate writing video: login, instructions, answering, autosave, submission, and result check.',
    '6. Monitoring video: supervisor dashboard, incidents, resets, and exam ending.',
    '7. Results video: dashboards, marked papers, CSV/PDF exports, and certificate verification.',
];

export default function PublicDocumentation() {
    return (
        <>
            <Head title="AlignEx Documentation" />
            <main className="min-h-screen bg-surface text-slateDark">
                <PublicNav />
                <Hero />
                <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
                    <SectionHeading eyebrow="Start here" title="Complete Workflow From Setup To Result" body="Every exam type in AlignEx follows the same operational path, with role-specific differences for schools, professional institutions, CBT centers, examiners, teachers, facilitators, supervisors, candidates, and result reviewers." />
                    <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {completeFlow.map((step, index) => <StepCard key={step.title} index={index + 1} step={step} />)}
                    </div>
                </section>
                <VideoStrategy />
                <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
                    <SectionHeading eyebrow="Role guides" title="How Each User Type Works" body="Use these guides to train users. Each card includes the user responsibility, workflow steps, and the best place to insert a training video." />
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        {sections.map((section) => <GuideCard key={section.title} section={section} />)}
                    </div>
                </section>
                <ResultDownloadGuide />
                <Footer />
            </main>
        </>
    );
}

function PublicNav() {
    return (
        <header className="sticky top-0 z-30 border-b border-border bg-white/95 backdrop-blur">
            <nav className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
                <Link href="/" className="flex items-center gap-3">
                    <img src="/images/brand-logo.png" alt="AlignEx" className="h-12 w-auto max-w-[190px] object-contain" />
                </Link>
                <div className="hidden items-center gap-6 text-sm font-semibold text-slate-600 md:flex">
                    <a href="#videos" className="hover:text-primary">Videos</a>
                    <a href="#roles" className="hover:text-primary">Roles</a>
                    <a href="#results" className="hover:text-primary">Results</a>
                    <Link href="/exam/login" className="hover:text-primary">Candidate Exam</Link>
                </div>
                <div className="flex items-center gap-2">
                    <Button asChild variant="ghost" className="hidden sm:inline-flex"><Link href="/login">Login</Link></Button>
                    <Button asChild><Link href="/register-admin">Register</Link></Button>
                </div>
            </nav>
        </header>
    );
}

function Hero() {
    return (
        <section className="border-b border-border bg-white">
            <div className="mx-auto grid max-w-7xl gap-8 px-6 py-14 lg:grid-cols-[1fr_0.8fr] lg:px-8">
                <div>
                    <div className="mb-5 inline-flex rounded-md border border-green-200 bg-green-50 px-3 py-1 text-sm font-semibold text-primary">
                        Public user guide
                    </div>
                    <h1 className="max-w-4xl text-4xl font-bold leading-tight text-primaryDark lg:text-6xl">
                        AlignEx Documentation For Exam Creation, Delivery, Monitoring, And Results
                    </h1>
                    <p className="mt-6 max-w-3xl text-base leading-8 text-slate-600">
                        This guide explains how every user type works in AlignEx, from platform setup and question bank creation to exam delivery, candidate submission, monitoring, result review, export, and certificate verification. It is public so admins can share it with new schools, facilitators, teachers, supervisors, and candidates before account creation.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Button asChild><a href="#roles">Browse Role Guides <ArrowRight className="h-4 w-4" /></a></Button>
                        <Button asChild variant="secondary"><a href="#videos">Video Placement Plan</a></Button>
                        <Button asChild variant="secondary"><Link href="/candidate-result">Candidate Result</Link></Button>
                    </div>
                </div>
                <div className="rounded-md border border-border bg-slate-50 p-5 shadow-sm">
                    <h2 className="font-semibold text-slateDark">Best Training Order</h2>
                    <div className="mt-4 space-y-3">
                        {['Watch platform overview', 'Prepare institution structure', 'Create question banks and questions', 'Create and configure exam', 'Generate papers and monitor', 'Review and export results'].map((item, index) => (
                            <div key={item} className="flex gap-3 rounded-md border border-border bg-white p-3">
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{index + 1}</span>
                                <span className="text-sm font-semibold text-slate-700">{item}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}

function VideoStrategy() {
    return (
        <section id="videos" className="border-y border-border bg-white">
            <div className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
                <SectionHeading eyebrow="Video placement" title="Where Training Videos Should Go" body="Use these placements when you record tutorials. Each video can be uploaded later as MP4, embedded from YouTube/Vimeo, or connected to an internal training library." />
                <div className="mt-6 grid gap-4 lg:grid-cols-2">
                    <VideoBox title="Primary overview video" body="Place this near the top of this documentation page. It should introduce AlignEx, user roles, security expectations, and the complete exam-to-result lifecycle." />
                    <div className="rounded-md border border-border bg-slate-50 p-5">
                        <h3 className="font-semibold text-slateDark">Recommended Video Series</h3>
                        <ul className="mt-4 space-y-2 text-sm leading-6 text-slate-600">
                            {videoPlan.map((item) => <li key={item} className="flex gap-2"><CheckCircle2 className="mt-1 h-4 w-4 shrink-0 text-success" />{item}</li>)}
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    );
}

function GuideCard({ section }: { section: Section }) {
    return (
        <article id={section.title === 'Super Admin' ? 'roles' : undefined} className="rounded-md border border-border bg-white p-5 shadow-sm">
            <div className="flex items-start gap-3">
                <div className="rounded-md bg-green-50 p-2 text-primary">{section.icon}</div>
                <div>
                    <h2 className="text-lg font-semibold text-slateDark">{section.title}</h2>
                    <p className="mt-1 text-sm leading-6 text-slate-600">{section.summary}</p>
                </div>
            </div>
            <VideoBox title={section.video.title} body={`${section.video.placement} Suggested script: ${section.video.script}`} compact />
            <div className="mt-5 space-y-3">
                {section.steps.map((step, index) => <StepCard key={step.title} index={index + 1} step={step} compact />)}
            </div>
        </article>
    );
}

function ResultDownloadGuide() {
    return (
        <section id="results" className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
            <SectionHeading eyebrow="Results" title="How To Get Exam Results" body="Result access depends on role, exam owner, assigned subject/course scope, and release settings." />
            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <InfoCard icon={<BarChart3 className="h-5 w-5" />} title="Admin/Reviewer Results" body="Open Results, choose the exam, review submitted attempts, inspect candidate rows, and export CSV or PDF summaries." />
                <InfoCard icon={<Download className="h-5 w-5" />} title="Marked Paper Downloads" body="Where permitted, open a candidate attempt and download the marked paper PDF for question-by-question review." />
                <InfoCard icon={<UserCheck className="h-5 w-5" />} title="Candidate Result Check" body="Candidates use Candidate Result or Verify Result only when the institution has enabled result release." />
            </div>
            <div className="mt-6 rounded-md border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                Never expose correct answers, answer keys, scoring rubrics, or correctness flags to candidates before or during the exam. Admin and reviewer result views are protected by role permissions.
            </div>
        </section>
    );
}

function SectionHeading({ eyebrow, title, body }: { eyebrow: string; title: string; body: string }) {
    return (
        <div>
            <div className="text-sm font-bold uppercase tracking-wide text-primary">{eyebrow}</div>
            <h2 className="mt-2 text-2xl font-bold text-primaryDark lg:text-3xl">{title}</h2>
            <p className="mt-3 max-w-4xl text-sm leading-7 text-slate-600">{body}</p>
        </div>
    );
}

function StepCard({ index, step, compact = false }: { index: number; step: Step; compact?: boolean }) {
    return (
        <div className={`rounded-md border border-border bg-slate-50 ${compact ? 'p-3' : 'p-4'}`}>
            <div className="flex gap-3">
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

function VideoBox({ title, body, compact = false }: { title: string; body: string; compact?: boolean }) {
    return (
        <div className={`mt-5 rounded-md border border-dashed border-primary/40 bg-green-50 ${compact ? 'p-3' : 'p-5'}`}>
            <div className="flex gap-3">
                <PlayCircle className="mt-1 h-5 w-5 shrink-0 text-primary" />
                <div>
                    <h3 className="font-semibold text-primaryDark">{title}</h3>
                    <p className="mt-1 text-sm leading-6 text-slate-700">{body}</p>
                    <div className="mt-3 rounded-md border border-green-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">
                        Video embed placeholder: replace with iframe, MP4 player, or training-library component.
                    </div>
                </div>
            </div>
        </div>
    );
}

function InfoCard({ icon, title, body }: { icon: ReactNode; title: string; body: string }) {
    return (
        <div className="rounded-md border border-border bg-white p-5 shadow-sm">
            <div className="rounded-md bg-green-50 p-2 text-primary w-fit">{icon}</div>
            <h3 className="mt-4 font-semibold text-slateDark">{title}</h3>
            <p className="mt-2 text-sm leading-6 text-slate-600">{body}</p>
        </div>
    );
}

function Footer() {
    return (
        <footer className="border-t border-border bg-white">
            <div className="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-slate-500 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div>AlignEx documentation for secure CBT operations.</div>
                <div className="flex flex-wrap gap-4 font-semibold">
                    <Link href="/" className="hover:text-primary">Home</Link>
                    <Link href="/login" className="hover:text-primary">Login</Link>
                    <Link href="/exam/login" className="hover:text-primary">Candidate Exam</Link>
                    <Link href="/verify-result" className="hover:text-primary">Verify Result</Link>
                    <Link href="/verify-certificate" className="hover:text-primary">Verify Certificate</Link>
                </div>
            </div>
        </footer>
    );
}
