import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { ResultRow } from './types';

type MarkedAnswer = {
    question: string;
    subject: string;
    marks: string;
    score_awarded: string | null;
    selected_labels: string;
    correct_labels: string;
    explanation?: string | null;
    options: { label: string; option_text: string; selected: boolean; correct: boolean }[];
};

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
    weaknesses: PerformanceRow[];
    topic_mastery: PerformanceRow[];
    difficulty_performance: { difficulty: string; score_percentage: number; total_questions: number }[];
    recommended_practice_areas: PerformanceRow[];
};

export default function CandidateResultDetails({ result, answers, adaptive }: { result: ResultRow; answers: MarkedAnswer[]; adaptive: AdaptiveAnalysis }) {
    return (
        <PortalAppShell title={result.candidate_name}>
            <Head title={`${result.candidate_name} Result`} />
            <section className="mx-auto max-w-5xl">
                <PageHeader
                    eyebrow="Candidate Result"
                    title={result.candidate_name}
                    description={result.registration_number}
                    actions={<><Button asChild variant="secondary"><Link href={`/results/exams/${result.exam_id}`}><ArrowLeft className="h-4 w-4" />Back</Link></Button><Button asChild variant="secondary"><a href={`/results/attempts/${result.attempt_id}/marked-paper.pdf`}><Printer className="h-4 w-4" />Marked Paper</a></Button></>}
                />
                <div className="grid gap-4 md:grid-cols-4">
                    <Metric label="Score" value={`${result.score}/${result.total_marks}`} />
                    <Metric label="Percentage" value={`${result.percentage}%`} />
                    <Metric label="Grade" value={result.grade} />
                    <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="text-sm font-semibold text-slate-500">Status</div><div className="mt-2"><StatusBadge label={result.status} tone={result.passed ? 'success' : 'danger'} /></div></div>
                    <Metric label="Duration Used" value={result.duration_used} />
                    <Metric label="Suspicious Events" value={result.suspicious_event_count} />
                    <Metric label="Verification Hash" value={result.result_hash} wide />
                </div>
                <section className="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
                    <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Candidate Weakness Report</h2>
                        <p className="mt-1 text-sm text-slate-500">Measured weak areas from submitted answers.</p>
                        <PracticeAreas rows={adaptive.weaknesses} emptyText="No weak mastery areas recorded." />
                    </div>
                    <DifficultyChart rows={adaptive.difficulty_performance} />
                </section>
                <TopicMastery rows={adaptive.topic_mastery} title="Topic Mastery" />
                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slateDark">Marked Question Paper</h2>
                    <div className="mt-4 space-y-4">
                        {answers.map((answer, index) => (
                            <div key={index} className="rounded-md border border-border p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="text-xs font-semibold uppercase text-primary">{answer.subject}</div>
                                        <h3 className="mt-1 font-bold text-slateDark">Question {index + 1}</h3>
                                    </div>
                                    <StatusBadge label={`${answer.score_awarded ?? '0.00'}/${answer.marks}`} tone={(Number(answer.score_awarded ?? 0) > 0) ? 'success' : 'danger'} />
                                </div>
                                <p className="mt-3 text-sm leading-6 text-slate-700">{answer.question}</p>
                                <div className="mt-3 space-y-2">
                                    {answer.options.map((option) => (
                                        <div key={option.label} className={`rounded-md border p-3 text-sm ${option.correct ? 'border-green-200 bg-green-50' : option.selected ? 'border-red-200 bg-red-50' : 'border-border bg-white'}`}>
                                            <span className="font-bold">{option.label}.</span> {option.option_text}
                                            {option.selected && <span className="ml-2 font-semibold text-primary">Selected</span>}
                                            {option.correct && <span className="ml-2 font-semibold text-success">Correct</span>}
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-3 grid gap-2 text-sm text-slate-600 md:grid-cols-2">
                                    <div><span className="font-semibold text-slateDark">Candidate Answer:</span> {answer.selected_labels || 'Not answered'}</div>
                                    <div><span className="font-semibold text-slateDark">Correct Answer:</span> {answer.correct_labels || 'N/A'}</div>
                                </div>
                                {answer.explanation && <p className="mt-3 text-sm text-slate-600"><span className="font-semibold text-slateDark">Explanation:</span> {answer.explanation}</p>}
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function Metric({ label, value, wide = false }: { label: string; value: string | number; wide?: boolean }) {
    return <div className={`rounded-md border border-border bg-white p-4 shadow-sm ${wide ? 'md:col-span-2' : ''}`}><div className="text-sm font-semibold text-slate-500">{label}</div><div className="mt-2 break-all text-lg font-bold text-slateDark">{value}</div></div>;
}

export function TopicMastery({ rows, title }: { rows: PerformanceRow[]; title: string }) {
    return (
        <section className="mt-6 overflow-x-auto rounded-md border border-border bg-white p-5 shadow-sm">
            <h2 className="font-semibold text-slateDark">{title}</h2>
            <table className="mt-4 w-full text-left text-sm">
                <thead className="text-xs uppercase text-slate-500">
                    <tr><th className="py-2">Subject</th><th>Topic</th><th>Difficulty</th><th>Correct</th><th>Score</th><th>Mastery</th></tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {rows.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-slate-500">No performance profile available.</td></tr>}
                    {rows.map((row, index) => (
                        <tr key={`${row.subject}-${row.topic}-${row.difficulty}-${index}`}>
                            <td className="py-3 font-semibold">{row.subject}</td>
                            <td>{row.topic}</td>
                            <td>{row.difficulty}</td>
                            <td>{row.correct_answers}/{row.total_questions}</td>
                            <td>{row.score_percentage}%</td>
                            <td><StatusBadge label={row.mastery_level} tone={masteryTone(row.mastery_level)} /></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}

export function DifficultyChart({ rows }: { rows: { difficulty: string; score_percentage: number; total_questions: number }[] }) {
    return (
        <div className="rounded-md border border-border bg-white p-5 shadow-sm">
            <h2 className="font-semibold text-slateDark">Difficulty Performance</h2>
            <p className="mt-1 text-sm text-slate-500">Average score by difficulty.</p>
            <div className="mt-4 h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={rows}>
                        <XAxis dataKey="difficulty" />
                        <YAxis domain={[0, 100]} />
                        <Tooltip />
                        <Bar dataKey="score_percentage" fill="#0F7A3A" radius={[4, 4, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export function PracticeAreas({ rows, emptyText }: { rows: PerformanceRow[]; emptyText: string }) {
    return (
        <div className="mt-4 space-y-3">
            {rows.length === 0 && <div className="rounded-md border border-border p-4 text-sm text-slate-500">{emptyText}</div>}
            {rows.map((row, index) => (
                <div key={`${row.subject}-${row.topic}-${index}`} className="rounded-md border border-border p-3">
                    <div className="flex items-center justify-between gap-3">
                        <div className="min-w-0">
                            <div className="truncate text-sm font-semibold text-slateDark">{row.topic}</div>
                            <div className="text-xs text-slate-500">{row.subject} | {row.difficulty}</div>
                        </div>
                        <StatusBadge label={`${row.score_percentage}%`} tone={masteryTone(row.mastery_level)} />
                    </div>
                </div>
            ))}
        </div>
    );
}

function masteryTone(level: string): 'success' | 'danger' | 'warning' | 'neutral' {
    if (level === 'strong') return 'success';
    if (level === 'average') return 'warning';
    if (level === 'weak') return 'danger';
    return 'neutral';
}
