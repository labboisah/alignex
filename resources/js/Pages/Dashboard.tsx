import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BookOpen,
    Building2,
    CheckCircle2,
    ClipboardList,
    FileQuestion,
    GraduationCap,
    Library,
    MapPin,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { Bar, BarChart, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { DashboardCard, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Metric = {
    label: string;
    value: number;
    description: string;
    icon: keyof typeof iconMap;
};

type RecentExam = {
    id: string;
    title: string;
    code: string;
    status: string;
    starts_at?: string | null;
    ends_at?: string | null;
    candidates_count: number;
    attempts_count: number;
};

type WorkItem = {
    label: string;
    value: number;
    href: string;
    tone: 'success' | 'warning' | 'danger' | 'info' | 'neutral';
};

type NamedValue = {
    name: string;
    value: number;
};

const iconMap = {
    Activity,
    AlertTriangle,
    BookOpen,
    Building2,
    CheckCircle2,
    ClipboardList,
    FileQuestion,
    GraduationCap,
    Library,
    MapPin,
    ShieldCheck,
    Users,
};

const statusColors = ['#2563EB', '#F59E0B', '#16A34A', '#0F7A3A', '#DC2626'];

export default function Dashboard({
    role,
    metrics,
    exam_status,
    result_summary,
    organization_charts = {},
    recent_candidates = [],
    recent_results = [],
    recent_exams,
    work_queue,
    quick_actions,
}: {
    role: { name: string; label: string; scope: string };
    metrics: Metric[];
    exam_status: { name: string; value: number }[];
    result_summary: { submitted: number; passed: number; failed: number; average_score: number };
    organization_charts?: {
        exams_by_category?: NamedValue[];
        exams_by_mode?: NamedValue[];
        candidate_performance?: NamedValue[];
        pass_fail_summary?: NamedValue[];
        certification_status?: NamedValue[];
    };
    recent_candidates?: { id: string; name: string; registration_number: string; status: string }[];
    recent_results?: { id: string; candidate_name: string; exam_title?: string | null; score?: string | number | null; submitted_at?: string | null }[];
    recent_exams: RecentExam[];
    work_queue: WorkItem[];
    quick_actions: { label: string; href: string }[];
}) {
    const passFail = [
        { name: 'Passed', value: result_summary.passed },
        { name: 'Failed', value: result_summary.failed },
    ];

    return (
        <PortalAppShell title="Dashboard">
            <Head title="Dashboard" />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow={role.label}
                    title="Dashboard"
                    description={`${role.scope} operational overview for exams, candidates, question readiness, monitoring, and results.`}
                    actions={quick_actions.slice(0, 2).map((action) => (
                        <Button key={action.href} asChild variant="secondary">
                            <Link href={action.href}>{action.label}</Link>
                        </Button>
                    ))}
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {metrics.map((metric) => (
                        <DashboardCard
                            key={metric.label}
                            title={metric.label}
                            value={metric.value}
                            description={metric.description}
                            icon={iconMap[metric.icon] ?? Activity}
                        />
                    ))}
                </div>

                <div className="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold text-slateDark">Exam Status</h2>
                                <p className="mt-1 text-sm text-slate-500">Current exam pipeline in your scope.</p>
                            </div>
                            <Button asChild variant="secondary">
                                <Link href="/exams">View Exams</Link>
                            </Button>
                        </div>
                        <div className="mt-5 h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={exam_status}>
                                    <XAxis dataKey="name" tick={{ fontSize: 12 }} />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip />
                                    <Bar dataKey="value" radius={[4, 4, 0, 0]}>
                                        {exam_status.map((entry, index) => <Cell key={entry.name} fill={statusColors[index % statusColors.length]} />)}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Result Snapshot</h2>
                        <p className="mt-1 text-sm text-slate-500">Submitted attempts and outcome spread.</p>
                        <div className="mt-5 grid grid-cols-2 gap-3">
                            <MiniStat label="Submitted" value={result_summary.submitted} />
                            <MiniStat label="Avg. Score" value={result_summary.average_score} />
                        </div>
                        <div className="mt-5 h-48">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie data={passFail} dataKey="value" nameKey="name" innerRadius={44} outerRadius={72}>
                                        <Cell fill="#16A34A" />
                                        <Cell fill="#DC2626" />
                                    </Pie>
                                    <Tooltip />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                </div>

                {organization_charts.exams_by_category && (
                    <div className="mt-6 grid gap-6 xl:grid-cols-3">
                        <ChartPanel title="Exams by Category" data={organization_charts.exams_by_category} />
                        <ChartPanel title="Exams by Mode" data={organization_charts.exams_by_mode ?? []} />
                        <ChartPanel title="Certification Status" data={organization_charts.certification_status ?? []} />
                    </div>
                )}

                <div className="mt-6 grid gap-6 lg:grid-cols-[360px_1fr]">
                    <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Work Queue</h2>
                        <div className="mt-4 space-y-3">
                            {work_queue.map((item) => (
                                <Link key={item.label} href={item.href} className="flex items-center justify-between rounded-md border border-border p-3 transition hover:border-primary">
                                    <div>
                                        <div className="text-sm font-semibold text-slateDark">{item.label}</div>
                                        <div className="text-xs text-slate-500">Open related module</div>
                                    </div>
                                    <StatusBadge label={String(item.value)} tone={badgeTone(item.tone)} />
                                </Link>
                            ))}
                        </div>
                        {quick_actions.length > 0 && (
                            <div className="mt-5 border-t border-border pt-4">
                                <h3 className="text-sm font-semibold text-slateDark">Quick Actions</h3>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {quick_actions.map((action) => (
                                        <Button key={action.href} asChild size="sm" variant="secondary">
                                            <Link href={action.href}>{action.label}</Link>
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </section>

                    <section className="overflow-x-auto rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Recent Exams</h2>
                        <table className="mt-4 w-full text-left text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr><th className="py-2">Exam</th><th>Status</th><th>Candidates</th><th>Attempts</th><th>Start</th><th>Actions</th></tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {recent_exams.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-slate-500">No exams found in this dashboard scope.</td></tr>}
                                {recent_exams.map((exam) => (
                                    <tr key={exam.id}>
                                        <td className="py-3">
                                            <div className="font-semibold text-slateDark">{exam.title}</div>
                                            <div className="text-xs text-slate-500">{exam.code}</div>
                                        </td>
                                        <td><StatusBadge label={exam.status.replaceAll('_', ' ')} tone={statusTone(exam.status)} /></td>
                                        <td>{exam.candidates_count}</td>
                                        <td>{exam.attempts_count}</td>
                                        <td>{exam.starts_at ? new Date(exam.starts_at).toLocaleString() : 'N/A'}</td>
                                        <td>
                                            <div className="flex flex-wrap gap-2">
                                                <Button asChild size="sm" variant="secondary"><Link href={`/exams/${exam.id}`}>Details</Link></Button>
                                                {exam.status === 'active' && <Button asChild size="sm" variant="secondary"><Link href={`/exams/${exam.id}/monitor`}>Monitor</Link></Button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                </div>

                {(recent_candidates.length > 0 || recent_results.length > 0) && (
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                            <h2 className="font-semibold text-slateDark">Recent Candidates</h2>
                            <div className="mt-4 space-y-3">
                                {recent_candidates.map((candidate) => (
                                    <Link key={candidate.id} href={`/candidates/${candidate.id}`} className="flex items-center justify-between rounded-md border border-border p-3 hover:border-primary">
                                        <div>
                                            <div className="text-sm font-semibold text-slateDark">{candidate.name}</div>
                                            <div className="text-xs text-slate-500">{candidate.registration_number}</div>
                                        </div>
                                        <StatusBadge label={candidate.status} tone={candidate.status === 'active' ? 'success' : 'neutral'} />
                                    </Link>
                                ))}
                            </div>
                        </section>
                        <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                            <h2 className="font-semibold text-slateDark">Recent Results</h2>
                            <div className="mt-4 space-y-3">
                                {recent_results.map((result) => (
                                    <div key={result.id} className="rounded-md border border-border p-3">
                                        <div className="text-sm font-semibold text-slateDark">{result.candidate_name || 'Candidate'}</div>
                                        <div className="text-xs text-slate-500">{result.exam_title || 'Exam'} · Score: {result.score ?? 'N/A'}</div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                )}
            </section>
        </PortalAppShell>
    );
}

function ChartPanel({ title, data }: { title: string; data: NamedValue[] }) {
    return (
        <section className="rounded-md border border-border bg-white p-5 shadow-sm">
            <h2 className="font-semibold text-slateDark">{title}</h2>
            <div className="mt-4 h-56">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data}>
                        <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                        <YAxis allowDecimals={false} />
                        <Tooltip />
                        <Bar dataKey="value" fill="#0F7A3A" radius={[4, 4, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </section>
    );
}

function MiniStat({ label, value }: { label: string; value: string | number }) {
    return <div className="rounded-md border border-border p-3"><div className="text-xs font-semibold uppercase text-slate-500">{label}</div><div className="mt-1 text-xl font-bold text-slateDark">{value}</div></div>;
}

function badgeTone(tone: WorkItem['tone']): 'success' | 'danger' | 'warning' | 'neutral' {
    if (tone === 'success') return 'success';
    if (tone === 'danger') return 'danger';
    if (tone === 'warning') return 'warning';
    return 'neutral';
}

function statusTone(status: string): 'success' | 'danger' | 'warning' | 'neutral' {
    if (status === 'active' || status === 'completed') return 'success';
    if (status === 'cancelled') return 'danger';
    if (status === 'scheduled' || status === 'draft') return 'warning';
    return 'neutral';
}
