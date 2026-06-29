import { Head, Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { type ReactNode } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { ResultsDashboard } from './types';

type ExamRow = {
    id: string;
    title: string;
    exam_code: string;
    status: string;
    submitted_attempts_count: number;
    total_marks: string;
    pass_mark: string;
};

export default function ResultsIndex({ exams, dashboard }: { exams: ExamRow[]; dashboard: ResultsDashboard }) {
    return (
        <PortalAppShell title="Results">
            <Head title="Results" />
            <section className="mx-auto max-w-7xl">
                <PageHeader eyebrow="Result Management" title="Results Dashboard" description="Review exam outcomes, exports, verification, and score trends." />
                <Summary dashboard={dashboard} />
                <Charts dashboard={dashboard} />
                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slateDark">Exam Results</h2>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr><th className="py-2">Exam</th><th>Code</th><th>Status</th><th>Submitted</th><th>Total Marks</th><th>Pass Mark</th><th>Actions</th></tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {exams.map((exam) => (
                                    <tr key={exam.id}>
                                        <td className="py-3 font-semibold">{exam.title}</td>
                                        <td>{exam.exam_code}</td>
                                        <td><StatusBadge label={exam.status} tone={exam.status === 'active' ? 'success' : 'neutral'} /></td>
                                        <td>{exam.submitted_attempts_count}</td>
                                        <td>{exam.total_marks}</td>
                                        <td>{exam.pass_mark}</td>
                                        <td><Button asChild size="sm" variant="secondary"><Link href={`/results/exams/${exam.id}`}><Eye className="h-4 w-4" />View</Link></Button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

export function Summary({ dashboard }: { dashboard: ResultsDashboard }) {
    return (
        <div className="grid gap-4 md:grid-cols-4">
            <Metric label="Submitted" value={dashboard.summary.total} />
            <Metric label="Passed" value={dashboard.summary.passed} tone="text-success" />
            <Metric label="Failed" value={dashboard.summary.failed} tone="text-danger" />
            <Metric label="Average" value={`${dashboard.summary.average_percentage}%`} />
        </div>
    );
}

export function Charts({ dashboard }: { dashboard: ResultsDashboard }) {
    return (
        <div className="mt-6 grid gap-4 lg:grid-cols-3">
            <ChartCard title="Pass/Fail">
                <PieChart>
                    <Pie data={dashboard.pass_fail} dataKey="value" nameKey="name" outerRadius={72}>
                        {dashboard.pass_fail.map((entry) => <Cell key={entry.name} fill={entry.name === 'Pass' ? '#16A34A' : '#DC2626'} />)}
                    </Pie>
                    <Tooltip />
                </PieChart>
            </ChartCard>
            <ChartCard title="Score Distribution">
                <BarChart data={dashboard.score_distribution}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="range" />
                    <YAxis allowDecimals={false} />
                    <Tooltip />
                    <Bar dataKey="count" fill="#0F7A3A" radius={[4, 4, 0, 0]} />
                </BarChart>
            </ChartCard>
            <ChartCard title="Average by Subject">
                <BarChart data={dashboard.average_by_subject}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="subject" />
                    <YAxis />
                    <Tooltip />
                    <Bar dataKey="average" fill="#2563EB" radius={[4, 4, 0, 0]} />
                </BarChart>
            </ChartCard>
        </div>
    );
}

function Metric({ label, value, tone = 'text-slateDark' }: { label: string; value: string | number; tone?: string }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="text-sm font-semibold text-slate-500">{label}</div><div className={`mt-2 text-2xl font-bold ${tone}`}>{value}</div></div>;
}

function ChartCard({ title, children }: { title: string; children: ReactNode }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><h3 className="mb-3 font-semibold text-slateDark">{title}</h3><div className="h-64"><ResponsiveContainer width="100%" height="100%">{children}</ResponsiveContainer></div></div>;
}
