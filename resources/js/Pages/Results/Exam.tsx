import { Head, Link } from '@inertiajs/react';
import { Download, Eye, FileText, Printer } from 'lucide-react';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { DifficultyChart, PracticeAreas, TopicMastery } from './Candidate';
import { Charts, Summary } from './Index';
import { ResultRow, ResultsDashboard } from './types';

type PerformanceRow = {
    subject: string;
    topic: string;
    difficulty: string;
    total_questions: number;
    correct_answers: number;
    score_percentage: number;
    mastery_level: 'weak' | 'average' | 'strong';
};

type AdaptiveAnalysis = {
    topic_mastery: PerformanceRow[];
    difficulty_performance: { difficulty: string; score_percentage: number; total_questions: number }[];
    recommended_practice_areas: PerformanceRow[];
};

export default function ExamResults({ exam, rows, dashboard, adaptive_analysis }: { exam: { id: string; title: string; exam_code: string; total_marks: string; pass_mark: string }; rows: ResultRow[]; dashboard: ResultsDashboard; adaptive_analysis: AdaptiveAnalysis }) {
    return (
        <PortalAppShell title={exam.title}>
            <Head title={`${exam.title} Results`} />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Exam Results"
                    title={exam.title}
                    description={`${exam.exam_code} | Total marks ${exam.total_marks} | Pass mark ${exam.pass_mark}`}
                    actions={<><Button asChild variant="secondary"><a href={`/results/exams/${exam.id}/export.csv`}><Download className="h-4 w-4" />CSV</a></Button><Button asChild variant="secondary"><a href={`/results/exams/${exam.id}/summary.pdf`}><FileText className="h-4 w-4" />PDF Summary</a></Button></>}
                />
                <Summary dashboard={dashboard} />
                <Charts dashboard={dashboard} />
                <section className="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
                    <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Exam Adaptive Analysis</h2>
                        <p className="mt-1 text-sm text-slate-500">Aggregate mastery data from submitted candidate papers.</p>
                        <PracticeAreas rows={adaptive_analysis.recommended_practice_areas} emptyText="No recommended practice areas yet." />
                    </div>
                    <DifficultyChart rows={adaptive_analysis.difficulty_performance} />
                </section>
                <TopicMastery rows={adaptive_analysis.topic_mastery} title="Topic Mastery Summary" />
                <div className="mt-6 overflow-x-auto rounded-md border border-border bg-white p-5 shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="text-xs uppercase text-slate-500">
                            <tr><th className="py-2">Candidate Name</th><th>Registration Number</th><th>Score</th><th>Percentage</th><th>Grade</th><th>Pass/Fail</th><th>Submitted At</th><th>Duration Used</th><th>Suspicious Events</th><th>Actions</th></tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {rows.map((row) => (
                                <tr key={row.attempt_id}>
                                    <td className="py-3 font-semibold">{row.candidate_name}</td>
                                    <td>{row.registration_number}</td>
                                    <td>{row.score}/{row.total_marks}</td>
                                    <td>{row.percentage}%</td>
                                    <td>{row.grade}</td>
                                    <td><StatusBadge label={row.status} tone={row.passed ? 'success' : 'danger'} /></td>
                                    <td>{row.submitted_at ? new Date(row.submitted_at).toLocaleString() : 'N/A'}</td>
                                    <td>{row.duration_used}</td>
                                    <td>{row.suspicious_event_count}</td>
                                    <td>
                                        <div className="flex flex-wrap gap-2">
                                            <Button asChild size="sm" variant="secondary"><Link href={`/results/attempts/${row.attempt_id}`}><Eye className="h-4 w-4" />View</Link></Button>
                                            <Button asChild size="sm" variant="secondary"><a href={`/results/attempts/${row.attempt_id}/marked-paper.pdf`}><Printer className="h-4 w-4" />Marked</a></Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </PortalAppShell>
    );
}
