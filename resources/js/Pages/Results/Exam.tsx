import { Head, Link, useForm } from '@inertiajs/react';
import { Download, Eye, FileText, Printer } from 'lucide-react';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { ToastProvider } from '@/Components/ui/toast';
import { DifficultyChart, PracticeAreas, TopicMastery } from './Candidate';
import { Charts, Summary } from './Index';
import { ResultRow, ResultsDashboard } from './types';
import RetakeManager, { RetakeCandidate } from './RetakeManager';

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

export default function ExamResults({ exam, rows, dashboard, adaptive_analysis, can_release, results_released, offline_uploads, retake_candidates }: { retake_candidates: RetakeCandidate[] | null; exam: { id: string; title: string; exam_code: string; owner?: { type: string; name: string }; service_provider?: string; total_marks: string; pass_mark: string; duration_minutes: number }; rows: ResultRow[]; dashboard: ResultsDashboard; adaptive_analysis: AdaptiveAnalysis; can_release: boolean; results_released: boolean; offline_uploads: { id: string; candidate_number: string; local_score: string | null; official_score: string | null; legacy_package: boolean; created_at: string }[] }) {
    const release = useForm({ released: !results_released });
    return (
        <PortalAppShell title={exam.title}>
            <Head title={`${exam.title} Results`} />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Exam Results"
                    title={exam.title}
                    description={`${exam.owner?.name ?? 'AlignEx'} | ${exam.service_provider ?? 'Service provided by AlignEx CBT, Sokoto'} | ${exam.exam_code} | Total marks ${exam.total_marks} | Pass mark ${exam.pass_mark}`}
                    actions={<><Button asChild variant="secondary"><a href={`/results/exams/${exam.id}/export.csv`}><Download className="h-4 w-4" />CSV</a></Button><Button asChild variant="secondary"><a href={`/results/exams/${exam.id}/summary.pdf`}><FileText className="h-4 w-4" />PDF Summary</a></Button></>}
                />
                <div className="mb-5 rounded-md border border-border bg-white p-4">
                    <p className="font-semibold">Candidate results: {results_released ? 'Released' : 'Held for review'}</p>
                    <p className="my-2 text-sm text-slate-500">This controls the online result checker for all submitted attempts in this exam, including uploaded offline attempts.</p>
                    {can_release && <Button disabled={release.processing} onClick={() => { release.transform(() => ({ released: !results_released })); release.post(`/results/exams/${exam.id}/release`, { preserveScroll: true }); }}>{release.processing ? 'Saving...' : results_released ? 'Hold results' : 'Release results'}</Button>}
                    {release.errors.released && <p role="alert" className="text-danger">{release.errors.released}</p>}
                    {release.recentlySuccessful && <p role="status" className="mt-2 text-success">Result visibility updated.</p>}
                </div>
                {offline_uploads.length > 0 && <div className="mb-5 overflow-x-auto rounded border border-border bg-white p-4">
                    <h2 className="font-semibold">Offline upload reconciliation</h2>
                    <table className="mt-3 w-full text-left text-sm"><thead><tr><th>Candidate</th><th>Local score</th><th>Official score</th><th>Paper verification</th><th>Received</th></tr></thead><tbody>
                        {offline_uploads.map(upload => <tr key={upload.id}><td className="py-2">{upload.candidate_number}</td><td>{upload.local_score ?? 'N/A'}</td><td>{upload.official_score ?? 'N/A'}</td><td>{upload.legacy_package ? 'Legacy paper matched' : 'Signed paper verified'}</td><td>{new Date(upload.created_at).toLocaleString()}</td></tr>)}
                    </tbody></table>
                </div>}
                {retake_candidates !== null && <ToastProvider><RetakeManager candidates={retake_candidates} duration={exam.duration_minutes} /></ToastProvider>}
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
                            {rows.length === 0 && <tr><td colSpan={10} className="py-5 text-center text-slate-500">No completed results yet.</td></tr>}
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
