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

const solutions = [
    {
        title: 'Secondary Schools',
        body: 'Run term exams, mock tests, entrance assessments, and multi-subject CBT sessions with structured subjects and topics.',
        icon: GraduationCap,
    },
    {
        title: 'Professional Exams',
        body: 'Deliver certification assessments with timed sections, question banks, supervisor monitoring, and controlled result release.',
        icon: FileCheck2,
    },
    {
        title: 'Recruitment Exams',
        body: 'Screen applicants at scale with secure online tests, candidate identity controls, and report-ready analytics.',
        icon: Users,
    },
];

const features = [
    ['Hybrid delivery', 'Online delivery now, with offline center-based examination planned for controlled venues.', TabletSmartphone],
    ['Real-time monitoring', 'Supervisors can track candidate status, incidents, timing, and live exam activity.', MonitorDot],
    ['Adaptive-ready', 'Architecture leaves room for future FastAPI-based adaptive question selection.', Activity],
    ['Result management', 'Support scoring, moderation, release workflows, reports, and exports.', BarChart3],
];

const workflow = [
    ['Prepare', 'Create subjects, topics, question banks, candidates, and exam settings.'],
    ['Deliver', 'Candidates write in a focused exam interface while answers autosave through secure APIs.'],
    ['Monitor', 'Supervisors review live sessions, warnings, and anti-cheating events in real time.'],
    ['Release', 'Scores are reviewed, approved, released, and exported through controlled result workflows.'],
];

const stats = [
    ['3', 'Exam markets'],
    ['6', 'Candidate exam routes'],
    ['24/7', 'Online readiness'],
    ['100%', 'Answer-key isolation'],
];

export default function PublicWelcome() {
    return (
        <>
            <Head title="Secure CBT Examination Platform" />
            <main className="min-h-screen bg-surface text-slateDark">
                <Navbar />
                <HeroSection />
                <MockupSection />
                <SolutionsSection />
                <FeaturesSection />
                <SecuritySection />
                <WorkflowSection />
                <TrustSection />
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
                    <span className="flex h-10 w-10 items-center justify-center rounded-md bg-primary text-white">
                        <GraduationCap className="h-6 w-6" />
                    </span>
                    <span>
                        <span className="block text-lg font-bold text-primaryDark">AlignEx</span>
                        <span className="block text-xs font-semibold uppercase text-slate-500">CBT Platform</span>
                    </span>
                </Link>
                <div className="hidden items-center gap-6 text-sm font-semibold text-slate-600 md:flex">
                    <a href="#solutions" className="hover:text-primary">Solutions</a>
                    <a href="#features" className="hover:text-primary">Features</a>
                    <a href="#security" className="hover:text-primary">Security</a>
                    <a href="#workflow" className="hover:text-primary">Workflow</a>
                </div>
                <div className="flex items-center gap-2">
                    <Button asChild variant="ghost" className="hidden sm:inline-flex">
                        <Link href="/login">Login</Link>
                    </Button>
                    <Button asChild>
                        <Link href="/dashboard">View Demo</Link>
                    </Button>
                </div>
            </nav>
        </header>
    );
}

function HeroSection() {
    return (
        <section className="border-b border-border bg-white">
            <div className="mx-auto grid max-w-7xl gap-10 px-6 py-16 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-20">
                <div className="flex flex-col justify-center">
                    <div className="mb-5 inline-flex w-fit items-center gap-2 rounded-md border border-green-200 bg-green-50 px-3 py-1 text-sm font-semibold text-primary">
                        <ShieldCheck className="h-4 w-4" />
                        Secure CBT operations
                    </div>
                    <h1 className="max-w-4xl text-4xl font-bold leading-tight text-primaryDark sm:text-5xl lg:text-6xl">
                        Secure Online and Offline CBT Examination Platform
                    </h1>
                    <p className="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">
                        AlignEx helps institutions deliver secondary school exams, professional certification exams, and recruitment exams with support for adaptive assessment, online and future offline delivery, real-time supervisor monitoring, anti-cheating controls, result management, and reports.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Button asChild className="h-11">
                            <Link href="/dashboard">Explore Platform <ArrowRight className="h-4 w-4" /></Link>
                        </Button>
                        <Button asChild variant="secondary" className="h-11">
                            <a href="#mockups">View Mockups</a>
                        </Button>
                    </div>
                    <div className="mt-8 grid max-w-xl gap-3 sm:grid-cols-3">
                        {['Laravel + Inertia', 'React + TypeScript', 'Reverb-ready'].map((item) => (
                            <div key={item} className="flex items-center gap-2 text-sm font-semibold text-slate-600">
                                <CheckCircle2 className="h-4 w-4 text-success" />
                                {item}
                            </div>
                        ))}
                    </div>
                </div>
                <HeroMockup />
            </div>
        </section>
    );
}

function HeroMockup() {
    return (
        <div className="relative min-h-[440px]" id="mockups">
            <div className="absolute left-0 top-0 w-[86%] rounded-lg border border-border bg-white shadow-xl">
                <MockupChrome title="Admin Dashboard" />
                <div className="grid gap-4 p-4 sm:grid-cols-3">
                    {[
                        ['Active Exams', '18'],
                        ['Candidates', '2,460'],
                        ['Question Banks', '74'],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-md border border-border bg-slate-50 p-3">
                            <div className="text-xs font-semibold text-slate-500">{label}</div>
                            <div className="mt-2 text-2xl font-bold text-primaryDark">{value}</div>
                        </div>
                    ))}
                    <div className="col-span-full h-28 rounded-md border border-border bg-white p-3">
                        <div className="mb-3 flex items-center gap-2 text-xs font-semibold text-slate-500">
                            <BarChart3 className="h-4 w-4 text-accent" />
                            Exam activity
                        </div>
                        <div className="flex h-16 items-end gap-2">
                            {[40, 70, 54, 92, 64, 80, 48].map((height, index) => (
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
                            <div className="text-xs font-semibold text-primary">Question 12 of 60</div>
                            <div className="mt-1 text-sm font-bold">Biology Certification</div>
                        </div>
                        <StatusBadge label="42:18" tone="warning" />
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

function MockupSection() {
    return (
        <section className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <SectionHeading
                eyebrow="Product mockups"
                title="Purpose-built screens for every examination role"
                description="AlignEx separates admin operations, candidate delivery, supervisor monitoring, and result analytics into focused interfaces."
            />
            <div className="grid gap-5 lg:grid-cols-4">
                <MiniMockup title="Admin dashboard" icon={Laptop} accent="bg-primary">
                    <div className="grid grid-cols-2 gap-2">
                        <MetricPill label="Exams" value="18" />
                        <MetricPill label="Banks" value="74" />
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

function SolutionsSection() {
    return (
        <section id="solutions" className="border-y border-border bg-white">
            <div className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
                <SectionHeading eyebrow="Solutions" title="One CBT foundation for many exam programs" />
                <div className="grid gap-5 md:grid-cols-3">
                    {solutions.map(({ title, body, icon: Icon }) => (
                        <div key={title} className="rounded-md border border-border bg-white p-6 shadow-sm">
                            <Icon className="h-7 w-7 text-primary" />
                            <h3 className="mt-5 text-lg font-bold">{title}</h3>
                            <p className="mt-3 text-sm leading-7 text-slate-600">{body}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function FeaturesSection() {
    return (
        <section id="features" className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <SectionHeading
                eyebrow="Features"
                title="Built for exam teams, supervisors, and candidates"
                description="The platform architecture keeps exam operations organized while protecting candidate delivery from administrative complexity."
            />
            <div className="grid gap-5 md:grid-cols-2">
                {features.map(([title, body, Icon]) => (
                    <div key={title as string} className="flex gap-4 rounded-md border border-border bg-white p-5 shadow-sm">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-green-50 text-primary">
                            <Icon className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="font-bold">{title}</h3>
                            <p className="mt-2 text-sm leading-7 text-slate-600">{body}</p>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function SecuritySection() {
    const items = [
        ['Answer-key isolation', 'Correct answers must never be sent to the candidate frontend.'],
        ['Policy-controlled access', 'Laravel policies and middleware guard public, admin, and supervisor actions.'],
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

function WorkflowSection() {
    return (
        <section id="workflow" className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <SectionHeading eyebrow="Workflow" title="From question bank to released results" />
            <div className="grid gap-5 md:grid-cols-4">
                {workflow.map(([title, body], index) => (
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

function TrustSection() {
    return (
        <section className="border-y border-border bg-white">
            <div className="mx-auto max-w-7xl px-6 py-14 lg:px-8">
                <div className="grid gap-6 md:grid-cols-[0.9fr_1.1fr] md:items-center">
                    <div>
                        <p className="text-sm font-semibold uppercase text-primary">Trust signals</p>
                        <h2 className="mt-2 text-3xl font-bold">Prepared for high-stakes examination operations.</h2>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-4">
                        {stats.map(([value, label]) => (
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
    return (
        <section className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div className="rounded-lg bg-primaryDark px-6 py-10 text-white sm:px-10">
                <div className="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <h2 className="text-3xl font-bold">Start building a secure CBT operation with AlignEx.</h2>
                        <p className="mt-3 max-w-3xl text-sm leading-7 text-green-50">
                            Use the Laravel and Inertia foundation to add organizations, question banks, candidate delivery, monitoring, anti-cheating, and reporting in safe ordered modules.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button asChild className="bg-white text-primaryDark hover:bg-green-50">
                            <Link href="/dashboard">Open Demo</Link>
                        </Button>
                        <Button asChild variant="secondary" className="border-white/30 bg-transparent text-white hover:bg-white/10">
                            <Link href="/ui-preview">View UI Kit</Link>
                        </Button>
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
                    <a href="#security" className="hover:text-primary">Security</a>
                    <Link href="/login" className="hover:text-primary">Login</Link>
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
