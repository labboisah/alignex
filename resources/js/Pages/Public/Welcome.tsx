import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    BarChart3,
    BookOpenCheck,
    Building2,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    Eye,
    FileCheck2,
    GraduationCap,
    Laptop,
    LockKeyhole,
    MonitorDot,
    RadioTower,
    ShieldCheck,
    Signal,
    TabletSmartphone,
    Users,
    WifiOff,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { StatusBadge } from '@/Components/Platform';

type LandingIcon = keyof typeof iconMap;
type LandingCard = { title: string; body: string; icon: LandingIcon };
type LandingStep = { title: string; body: string };
type LandingStat = { value: string; label: string };
type LandingMetric = { value: string; label: string };
type LandingContent = {
    hero?: {
        eyebrow?: string;
        title?: string;
        description?: string;
        badges?: string[];
    };
    solutions?: LandingCard[];
    features?: LandingCard[];
    workflow?: LandingStep[];
    stats?: LandingStat[];
    metrics?: LandingMetric[];
    activity?: {
        label: string;
        bars: number[];
    };
    candidateMockup?: {
        question_label: string;
        title: string;
        timer_label: string;
    };
    operations?: LandingMetric[];
};

const iconMap = {
    Activity,
    BarChart3,
    FileCheck2,
    GraduationCap,
    MonitorDot,
    TabletSmartphone,
    Users,
};

const defaultSolutions: LandingCard[] = [
    {
        title: 'Secondary Schools',
        body: 'Plan terminal exams, mock tests, entrance assessments, and subject-based CBT sessions from one organized workspace.',
        icon: 'GraduationCap',
    },
    {
        title: 'Professional Exams',
        body: 'Run certification exams with candidate assignment, timed delivery, supervisor review, and controlled result release.',
        icon: 'FileCheck2',
    },
    {
        title: 'Recruitment Exams',
        body: 'Screen applicants with secure exams, candidate tracking, anti-cheating records, and export-ready reports.',
        icon: 'Users',
    },
];

const defaultFeatures: LandingCard[] = [
    { title: 'Flexible exam delivery', body: 'Deliver exams online or through offline center servers where internet access is limited or controlled.', icon: 'TabletSmartphone' },
    { title: 'Live candidate monitoring', body: 'Supervisors can track logins, progress, answer saving, submissions, and exam events in real time.', icon: 'MonitorDot' },
    { title: 'Question paper control', body: 'Generate candidate papers before delivery and verify imported papers before offline exams begin.', icon: 'Activity' },
    { title: 'Results and reports', body: 'Manage scoring, moderation, release decisions, exports, and operational reports from the portal.', icon: 'BarChart3' },
];

const defaultWorkflow: LandingStep[] = [
    { title: 'Prepare', body: 'Create exam structures, question banks, candidates, schedules, and delivery rules.' },
    { title: 'Generate', body: 'Generate candidate papers and confirm each assigned candidate has a complete paper.' },
    { title: 'Deliver', body: 'Run the exam online or import it into an offline center server for local delivery.' },
    { title: 'Release', body: 'Review submissions, finalize results, publish outcomes, and export reports.' },
];

const defaultStats: LandingStat[] = [
    { value: '3', label: 'Exam markets' },
    { value: '6', label: 'Candidate exam routes' },
    { value: '24/7', label: 'Online readiness' },
    { value: '100%', label: 'Answer-key isolation' },
];

const defaultMetrics: LandingMetric[] = [
    { label: 'Active Exams', value: '0' },
    { label: 'Candidates', value: '0' },
    { label: 'Question Banks', value: '0' },
];

const defaultOperations: LandingMetric[] = [
    { label: 'Institutions', value: '0' },
    { label: 'Subjects', value: '0' },
    { label: 'Questions', value: '0' },
    { label: 'Scheduled Exams', value: '0' },
];

export default function PublicWelcome({ landing = {} }: { landing?: LandingContent }) {
    const hero = {
        eyebrow: landing.hero?.eyebrow ?? 'Trusted CBT operations',
        title: landing.hero?.title ?? 'Examination delivery built for schools, centers, and professional bodies',
        description: landing.hero?.description ?? 'AlignEx helps teams prepare question banks, assign candidates, deliver secure exams, monitor live sessions, manage offline centers, and release results with confidence.',
        badges: landing.hero?.badges ?? ['Online and offline delivery', 'Live supervision', 'Controlled result release'],
    };
    const solutions = landing.solutions ?? defaultSolutions;
    const features = landing.features ?? defaultFeatures;
    const workflow = landing.workflow ?? defaultWorkflow;
    const stats = landing.stats ?? defaultStats;
    const metrics = landing.metrics ?? defaultMetrics;
    const activity = landing.activity ?? { label: 'Submissions in the last 7 days', bars: [8, 8, 8, 8, 8, 8, 8] };
    const candidateMockup = landing.candidateMockup ?? { question_label: 'Question 1 of 1', title: 'No active exam yet', timer_label: 'Ready' };
    const operations = landing.operations ?? defaultOperations;

    return (
        <>
            <Head title="Secure CBT Examination Platform" />
            <main className="min-h-screen bg-surface text-slateDark">
                <Navbar />
                <HeroSection hero={hero} metrics={metrics} activity={activity} candidateMockup={candidateMockup} />
                <OperationsSection operations={operations} />
                <MockupSection metrics={metrics} />
                <SolutionsSection solutions={solutions} />
                <FeaturesSection features={features} />
                <SecuritySection />
                <WorkflowSection workflow={workflow} />
                <TrustSection stats={stats} />
                <FinalCta />
                <Footer />
            </main>
        </>
    );
}

function Navbar() {
    return (
        <header className="sticky top-0 z-30 border-b border-border bg-white/95 backdrop-blur">
            <nav className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
                <Link href="/" className="flex items-center gap-3">
                    <img src="/images/brand-logo.png" alt="AlignEx" className="h-12 w-auto max-w-[190px] object-contain" />
                </Link>
                <div className="hidden items-center gap-6 text-sm font-semibold text-slate-600 md:flex">
                    <a href="#overview" className="hover:text-primary">Overview</a>
                    <a href="#solutions" className="hover:text-primary">Solutions</a>
                    <a href="#delivery" className="hover:text-primary">Delivery</a>
                    <a href="#security" className="hover:text-primary">Security</a>
                    <a href="#workflow" className="hover:text-primary">Workflow</a>
                </div>
                <div className="flex items-center gap-2">
                    <Button asChild variant="ghost" className="hidden sm:inline-flex">
                        <Link href="/login">Login</Link>
                    </Button>
                    <Button asChild variant="ghost" className="hidden xl:inline-flex">
                        <Link href="/verify-certificate">Verify Certificate</Link>
                    </Button>
                    <Button asChild variant="secondary" className="hidden sm:inline-flex">
                        <Link href="/exam/login">Candidate Exam</Link>
                    </Button>
                    <Button asChild>
                        <Link href="/register-admin">Register</Link>
                    </Button>
                </div>
            </nav>
        </header>
    );
}

function HeroSection({ hero, metrics, activity, candidateMockup }: { hero: Required<NonNullable<LandingContent['hero']>>; metrics: LandingMetric[]; activity: NonNullable<LandingContent['activity']>; candidateMockup: NonNullable<LandingContent['candidateMockup']> }) {
    return (
        <section id="overview" className="border-b border-border bg-white">
            <div className="mx-auto grid max-w-7xl gap-10 px-6 py-16 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-20">
                <div className="flex flex-col justify-center">
                    <img src="/images/brand-logo.png" alt="AlignEx" className="mb-8 h-20 w-fit max-w-full object-contain" />
                    <div className="mb-5 inline-flex w-fit items-center gap-2 rounded-md border border-green-200 bg-green-50 px-3 py-1 text-sm font-semibold text-primary">
                        <ShieldCheck className="h-4 w-4" />
                        {hero.eyebrow}
                    </div>
                    <h1 className="max-w-4xl text-4xl font-bold leading-tight text-primaryDark sm:text-5xl lg:text-6xl">
                        {hero.title}
                    </h1>
                    <p className="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">
                        {hero.description}
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Button asChild className="h-11">
                            <Link href="/dashboard">Explore Platform <ArrowRight className="h-4 w-4" /></Link>
                        </Button>
                        <Button asChild variant="secondary" className="h-11">
                            <Link href="/exam/login">Write Exam</Link>
                        </Button>
                        <Button asChild variant="secondary" className="h-11">
                            <Link href="/verify-certificate">Verify Certificate</Link>
                        </Button>
                        <Button asChild variant="secondary" className="h-11">
                            <a href="#delivery">See Delivery Flow</a>
                        </Button>
                    </div>
                    <div className="mt-8 grid max-w-xl gap-3 sm:grid-cols-3">
                        {hero.badges.map((item) => (
                            <div key={item} className="flex items-center gap-2 text-sm font-semibold text-slate-600">
                                <CheckCircle2 className="h-4 w-4 text-success" />
                                {item}
                            </div>
                        ))}
                    </div>
                </div>
                <HeroMockup metrics={metrics} activity={activity} candidateMockup={candidateMockup} />
            </div>
        </section>
    );
}

function HeroMockup({ metrics, activity, candidateMockup }: { metrics: LandingMetric[]; activity: NonNullable<LandingContent['activity']>; candidateMockup: NonNullable<LandingContent['candidateMockup']> }) {
    return (
        <div className="relative min-h-[440px]" id="mockups">
            <div className="absolute left-0 top-0 w-[86%] rounded-lg border border-border bg-white shadow-xl">
                <MockupChrome title="Admin Dashboard" />
                <div className="grid gap-4 p-4 sm:grid-cols-3">
                    {metrics.slice(0, 3).map(({ label, value }) => (
                        <div key={label} className="rounded-md border border-border bg-slate-50 p-3">
                            <div className="text-xs font-semibold text-slate-500">{label}</div>
                            <div className="mt-2 text-2xl font-bold text-primaryDark">{value}</div>
                        </div>
                    ))}
                    <div className="col-span-full h-28 rounded-md border border-border bg-white p-3">
                        <div className="mb-3 flex items-center gap-2 text-xs font-semibold text-slate-500">
                            <BarChart3 className="h-4 w-4 text-accent" />
                            {activity.label}
                        </div>
                        <div className="flex h-16 items-end gap-2">
                            {activity.bars.map((height, index) => (
                                <span key={index} className="flex-1 rounded-t bg-primary" style={{ height: `${height}%` }} />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
            <div className="absolute bottom-6 right-0 w-[68%] rounded-lg border border-border bg-white shadow-xl">
                <MockupChrome title="Candidate Exam" compact />
                <div className="p-4">
                    <div className="flex items-center justify-between border-b border-border pb-3">
                        <div>
                            <div className="text-xs font-semibold text-primary">{candidateMockup.question_label}</div>
                            <div className="mt-1 text-sm font-bold">{candidateMockup.title}</div>
                        </div>
                        <StatusBadge label={candidateMockup.timer_label} tone="warning" />
                    </div>
                    <div className="mt-4 space-y-2">
                        <div className="h-3 w-full rounded bg-slate-200" />
                        <div className="h-3 w-4/5 rounded bg-slate-200" />
                        <div className="mt-4 grid gap-2">
                            {['A', 'B', 'C'].map((label, index) => (
                                <div key={label} className={`rounded-md border p-2 text-sm ${index === 1 ? 'border-primary bg-green-50 text-primary' : 'border-border text-slate-500'}`}>
                                    {label}. Answer option
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function OperationsSection({ operations }: { operations: LandingMetric[] }) {
    return (
        <section className="border-b border-border bg-white">
            <div className="mx-auto max-w-7xl px-6 py-8 lg:px-8">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    {operations.map(({ label, value }) => (
                        <div key={label} className="rounded-md border border-border bg-surface p-4">
                            <div className="text-2xl font-bold text-primaryDark">{value}</div>
                            <div className="mt-1 text-xs font-semibold uppercase text-slate-500">{label}</div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function MockupSection({ metrics }: { metrics: LandingMetric[] }) {
    const examCount = metrics.find((item) => item.label === 'Active Exams')?.value ?? '0';
    const bankCount = metrics.find((item) => item.label === 'Question Banks')?.value ?? '0';

    return (
        <section className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <SectionHeading
                eyebrow="Platform experience"
                title="Focused workspaces for every examination role"
                description="AlignEx keeps administration, candidate delivery, supervision, and result handling clear so teams can work quickly under exam pressure."
            />
            <div className="grid gap-5 lg:grid-cols-4">
                <MiniMockup title="Admin dashboard" icon={Laptop} accent="bg-primary">
                    <div className="grid grid-cols-2 gap-2">
                        <MetricPill label="Active" value={examCount} />
                        <MetricPill label="Banks" value={bankCount} />
                    </div>
                    <div className="mt-3 h-20 rounded-md bg-slate-100 p-2">
                        <div className="h-full rounded bg-white">
                            <div className="flex h-full items-end gap-1 p-2">
                                {[35, 70, 55, 90, 62].map((height, index) => (
                                    <span key={index} className="flex-1 rounded-t bg-primary" style={{ height: `${height}%` }} />
                                ))}
                            </div>
                        </div>
                    </div>
                </MiniMockup>
                <MiniMockup title="Candidate exam screen" icon={ClipboardCheck} accent="bg-accent">
                    <StatusBadge label="Autosaved" tone="success" />
                    <div className="mt-3 space-y-2">
                        <div className="h-2 rounded bg-slate-200" />
                        <div className="h-2 w-5/6 rounded bg-slate-200" />
                        <div className="rounded-md border border-primary bg-green-50 p-2 text-xs font-semibold text-primary">Selected option</div>
                    </div>
                </MiniMockup>
                <MiniMockup title="Supervisor monitor" icon={Eye} accent="bg-info">
                    <div className="space-y-2">
                        {['Online', 'Warning', 'Submitted'].map((item, index) => (
                            <div key={item} className="flex items-center justify-between rounded-md border border-border p-2 text-xs">
                                <span>Candidate {index + 1}</span>
                                <StatusBadge label={item} tone={index === 1 ? 'warning' : 'success'} />
                            </div>
                        ))}
                    </div>
                </MiniMockup>
                <MiniMockup title="Result analytics" icon={BarChart3} accent="bg-brown">
                    <div className="flex h-28 items-end gap-2">
                        {[45, 60, 76, 52, 88, 70].map((height, index) => (
                            <span key={index} className="flex-1 rounded-t bg-accent" style={{ height: `${height}%` }} />
                        ))}
                    </div>
                </MiniMockup>
            </div>
        </section>
    );
}

function SolutionsSection({ solutions }: { solutions: LandingCard[] }) {
    return (
        <section id="solutions" className="border-y border-border bg-white">
            <div className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
                <SectionHeading eyebrow="Solutions" title="One CBT foundation for many exam programs" />
                <div className="grid gap-5 md:grid-cols-3">
                    {solutions.map(({ title, body, icon }) => {
                        const Icon = iconMap[icon] ?? GraduationCap;

                        return <div key={title} className="rounded-md border border-border bg-white p-6 shadow-sm">
                            <Icon className="h-7 w-7 text-primary" />
                            <h3 className="mt-5 text-lg font-bold">{title}</h3>
                            <p className="mt-3 text-sm leading-7 text-slate-600">{body}</p>
                        </div>;
                    })}
                </div>
            </div>
        </section>
    );
}

function FeaturesSection({ features }: { features: LandingCard[] }) {
    return (
        <section id="delivery" className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <SectionHeading
                eyebrow="Delivery"
                title="Built for exam teams, supervisors, and candidates"
                description="The platform keeps exam preparation, delivery, monitoring, and reporting organized without distracting candidates during the exam."
            />
            <div className="grid gap-5 md:grid-cols-2">
                {features.map(({ title, body, icon }) => {
                    const Icon = iconMap[icon] ?? Activity;

                    return <div key={title} className="flex gap-4 rounded-md border border-border bg-white p-5 shadow-sm">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-green-50 text-primary">
                            <Icon className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="font-bold">{title}</h3>
                            <p className="mt-2 text-sm leading-7 text-slate-600">{body}</p>
                        </div>
                    </div>;
                })}
            </div>
        </section>
    );
}

function SecuritySection() {
    const items = [
        ['Answer-key isolation', 'Correct answers must never be sent to the candidate frontend.'],
        ['Role-controlled access', 'Public pages, admin actions, supervisor controls, and candidate exam screens are separated by responsibility.'],
        ['Exam-sensitive logs', 'Login, answer saves, submissions, focus changes, and supervisor interventions are logged.'],
        ['Server authority', 'Timing, submission, disqualification, scoring, and result release stay server-controlled.'],
    ];

    return (
        <section id="security" className="border-y border-border bg-slateDark text-white">
            <div className="mx-auto grid max-w-7xl gap-10 px-6 py-16 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div>
                    <p className="text-sm font-semibold uppercase text-accent">Security</p>
                    <h2 className="mt-2 text-3xl font-bold">Exam integrity is designed into the platform boundary.</h2>
                    <p className="mt-4 text-sm leading-7 text-slate-300">
                        AlignEx treats candidate clients as untrusted, keeps answer keys server-side, and records sensitive activity for supervisor and audit review.
                    </p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    {items.map(([title, body]) => (
                        <div key={title} className="rounded-md border border-white/10 bg-white/5 p-5">
                            <LockKeyhole className="h-5 w-5 text-accent" />
                            <h3 className="mt-4 font-bold">{title}</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-300">{body}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function WorkflowSection({ workflow }: { workflow: LandingStep[] }) {
    return (
        <section id="workflow" className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <SectionHeading eyebrow="Workflow" title="From question bank to released results" />
            <div className="grid gap-5 md:grid-cols-4">
                {workflow.map(({ title, body }, index) => (
                    <div key={title} className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <div className="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-sm font-bold text-white">{index + 1}</div>
                        <h3 className="mt-5 font-bold">{title}</h3>
                        <p className="mt-2 text-sm leading-7 text-slate-600">{body}</p>
                    </div>
                ))}
            </div>
        </section>
    );
}

function TrustSection({ stats }: { stats: LandingStat[] }) {
    return (
        <section className="border-y border-border bg-white">
            <div className="mx-auto max-w-7xl px-6 py-14 lg:px-8">
                <div className="grid gap-6 md:grid-cols-[0.9fr_1.1fr] md:items-center">
                    <div>
                        <p className="text-sm font-semibold uppercase text-primary">Trust signals</p>
                        <h2 className="mt-2 text-3xl font-bold">Prepared for high-stakes examination operations.</h2>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-4">
                        {stats.map(({ value, label }) => (
                            <div key={label} className="rounded-md border border-border bg-surface p-4 text-center">
                                <div className="text-2xl font-bold text-primaryDark">{value}</div>
                                <div className="mt-1 text-xs font-semibold uppercase text-slate-500">{label}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}

function FinalCta() {
    const demoUsers = [
        { role: 'Organization Admin', email: 'org.admin@alignex.test' },
        { role: 'Secondary School Admin', email: 'secondary.admin@alignex.test' },
        { role: 'Professional School Admin', email: 'professional.admin@alignex.test' },
        { role: 'CBT Center Admin', email: 'cbt.admin@alignex.test' },
    ];

    return (
        <section className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div className="rounded-lg bg-primaryDark px-6 py-10 text-white sm:px-10">
                <div className="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-start">
                    <div>
                        <h2 className="text-3xl font-bold">Start building a secure CBT operation with AlignEx.</h2>
                        <p className="mt-3 max-w-3xl text-sm leading-7 text-green-50">
                        Configure organizations, question banks, candidate delivery, monitoring, offline centers, anti-cheating review, and reporting in one exam operations platform.
                        </p>
                    </div>
                    <div className="w-full max-w-md">
                        <div className="flex flex-wrap gap-3">
                            <Button asChild className="bg-white text-primaryDark hover:bg-green-50">
                                <Link href="/dashboard">Open Demo</Link>
                            </Button>
                            <Button asChild variant="secondary" className="border-white/30 bg-transparent text-white hover:bg-white/10">
                                <Link href="/register-admin">Register Institution</Link>
                            </Button>
                        </div>
                        <div className="mt-4 rounded-md border border-white/15 bg-white/10 p-4 text-sm text-green-50">
                            <div className="font-semibold text-white">Demo login details</div>
                            <div className="mt-1 text-xs">Use password <span className="font-bold text-white">password</span> for these demo users.</div>
                            <div className="mt-3 grid gap-2">
                                {demoUsers.map((user) => (
                                    <div key={user.email} className="rounded-md bg-white/10 p-2">
                                        <div className="text-xs font-semibold uppercase text-green-100">{user.role}</div>
                                        <div className="mt-0.5 break-all font-mono text-xs text-white">{user.email}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

function Footer() {
    return (
        <footer className="border-t border-border bg-white">
            <div className="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div className="font-semibold text-primaryDark">AlignEx</div>
                <div className="flex flex-wrap gap-4">
                    <a href="#solutions" className="hover:text-primary">Solutions</a>
                    <a href="#delivery" className="hover:text-primary">Delivery</a>
                    <a href="#security" className="hover:text-primary">Security</a>
                    <Link href="/pricing" className="hover:text-primary">Pricing</Link>
                    <Link href="/login" className="hover:text-primary">Login</Link>
                    <Link href="/exam/login" className="hover:text-primary">Candidate Exam</Link>
                    <Link href="/verify-certificate" className="hover:text-primary">Verify Certificate</Link>
                </div>
            </div>
        </footer>
    );
}

function SectionHeading({ eyebrow, title, description }: { eyebrow: string; title: string; description?: string }) {
    return (
        <div className="mb-8 max-w-3xl">
            <p className="text-sm font-semibold uppercase text-primary">{eyebrow}</p>
            <h2 className="mt-2 text-3xl font-bold text-slateDark">{title}</h2>
            {description && <p className="mt-3 text-sm leading-7 text-slate-600">{description}</p>}
        </div>
    );
}

function MockupChrome({ title, compact = false }: { title: string; compact?: boolean }) {
    return (
        <div className={`flex items-center justify-between border-b border-border px-4 ${compact ? 'py-2' : 'py-3'}`}>
            <div className="flex items-center gap-2">
                <span className="h-2.5 w-2.5 rounded-full bg-danger" />
                <span className="h-2.5 w-2.5 rounded-full bg-warning" />
                <span className="h-2.5 w-2.5 rounded-full bg-success" />
            </div>
            <div className="text-xs font-semibold text-slate-500">{title}</div>
        </div>
    );
}

function MiniMockup({ title, icon: Icon, accent, children }: { title: string; icon: typeof Laptop; accent: string; children: React.ReactNode }) {
    return (
        <div className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="mb-4 flex items-center gap-3">
                <div className={`flex h-9 w-9 items-center justify-center rounded-md text-white ${accent}`}>
                    <Icon className="h-5 w-5" />
                </div>
                <h3 className="font-bold">{title}</h3>
            </div>
            <div className="rounded-md border border-border bg-surface p-3">{children}</div>
        </div>
    );
}

function MetricPill({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md bg-white p-2">
            <div className="text-xs text-slate-500">{label}</div>
            <div className="font-bold text-primaryDark">{value}</div>
        </div>
    );
}
