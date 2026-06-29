import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileCheck2, Shuffle, TriangleAlert } from 'lucide-react';
import { AlertBanner, DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Exam } from '@/Pages/Exams/types';

type SubjectPreview = {
    subject_id: string;
    subject_name: string;
    required_questions: number;
    available_questions: number;
    status_counts?: Record<string, number>;
    difficulty_distribution: Record<string, number>;
    insufficient_difficulties: { difficulty: string; required: number; available: number }[];
};

type Preview = {
    assigned_candidates: number;
    generated_papers: number;
    required_questions: number;
    available_questions: number;
    subjects: SubjectPreview[];
    warnings: SubjectPreview[];
    shuffle_questions: boolean;
    shuffle_options: boolean;
    can_generate: boolean;
};

type GeneratedPaper = {
    attempt_id: string;
    candidate_name: string;
    registration_number: string;
    paper_generated: boolean;
    questions_count: number;
    generated_at?: string | null;
    status: string;
};

export default function ExamPaperShow({ exam, preview, generatedPapers }: { exam: { data: Exam }; preview: Preview; generatedPapers: GeneratedPaper[] }) {
    const record = exam.data;
    const hasWarnings = preview.warnings.length > 0;

    return (
        <PortalAppShell title="Generate Papers">
            <Head title={`Generate Papers - ${record.title}`} />
            <section className="mx-auto max-w-6xl">
                <PageHeader
                    eyebrow="Exam Papers"
                    title="Generate Candidate Papers"
                    description={`${record.title} (${record.exam_code})`}
                    actions={
                        <>
                            <Button asChild type="button" variant="secondary"><Link href={`/exams/${record.id}`}><ArrowLeft className="h-4 w-4" />Back</Link></Button>
                            <Button
                                type="button"
                                disabled={!preview.can_generate || hasWarnings || preview.assigned_candidates < 1}
                                onClick={() => window.confirm('Generate permanent candidate papers for this exam?') && router.post(`/exams/${record.id}/papers/generate`, {}, { preserveScroll: true })}
                            >
                                <FileCheck2 className="h-4 w-4" />
                                Generate Papers
                            </Button>
                        </>
                    }
                />

                {!preview.can_generate && <AlertBanner className="mb-5" tone="danger" title="Generation is locked" message="Papers cannot be generated or regenerated after the exam or a candidate attempt has started." />}
                {hasWarnings && <AlertBanner className="mb-5" tone="warning" title="Insufficient questions" message="Resolve the subject or difficulty warnings before generating papers." />}
                {preview.assigned_candidates < 1 && <AlertBanner className="mb-5" tone="warning" title="No assigned candidates" message="Assign candidates to this exam before generating papers." />}

                <div className="grid gap-4 md:grid-cols-4">
                    <Metric label="Assigned Candidates" value={String(preview.assigned_candidates)} />
                    <Metric label="Subjects" value={String(preview.subjects.length)} />
                    <Metric label="Required Questions" value={String(preview.required_questions)} />
                    <Metric label="Usable Questions" value={String(preview.available_questions)} />
                </div>

                <div className="mt-5 grid gap-4 md:grid-cols-2">
                    <Setting label="Shuffle Questions" enabled={preview.shuffle_questions} />
                    <Setting label="Shuffle Options" enabled={preview.shuffle_options} />
                </div>

                <section className="mt-6">
                    <h2 className="mb-3 font-semibold text-slateDark">Preview Summary</h2>
                    <DataTable<SubjectPreview>
                        rows={preview.subjects}
                        emptyTitle="No subject configuration found"
                        columns={[
                            { key: 'subject_name', header: 'Subject', render: (subject) => <span className="font-semibold">{subject.subject_name}</span> },
                            { key: 'required_questions', header: 'Required Questions', render: (subject) => String(subject.required_questions) },
                            { key: 'available_questions', header: 'Usable Questions', render: (subject) => String(subject.available_questions) },
                            { key: 'status_counts', header: 'Question Statuses', render: (subject) => statusText(subject.status_counts ?? {}) },
                            { key: 'difficulty_distribution', header: 'Difficulty Selection', render: (subject) => difficultyText(subject.difficulty_distribution) },
                            {
                                key: 'insufficient_difficulties',
                                header: 'Warnings',
                                render: (subject) => subject.available_questions < subject.required_questions || subject.insufficient_difficulties.length > 0
                                    ? <span className="inline-flex items-center gap-1 text-danger"><TriangleAlert className="h-4 w-4" />Insufficient</span>
                                    : 'OK',
                            },
                        ]}
                    />
                </section>

                <section className="mt-6">
                    <h2 className="mb-3 font-semibold text-slateDark">Generated Papers Summary</h2>
                    <DataTable<GeneratedPaper>
                        rows={generatedPapers}
                        emptyTitle="No generated papers yet"
                        columns={[
                            { key: 'candidate_name', header: 'Candidate Name', render: (paper) => <span className="font-semibold">{paper.candidate_name}</span> },
                            { key: 'registration_number', header: 'Registration Number' },
                            { key: 'paper_generated', header: 'Paper Generated', render: (paper) => paper.paper_generated ? 'Yes' : 'No' },
                            { key: 'questions_count', header: 'Questions Count', render: (paper) => String(paper.questions_count) },
                            { key: 'generated_at', header: 'Generated At', render: (paper) => paper.generated_at ?? 'N/A' },
                            { key: 'status', header: 'Status', render: (paper) => <StatusBadge label={paper.status.replaceAll('_', ' ')} tone={paper.status === 'not_started' ? 'neutral' : 'info'} /> },
                        ]}
                    />
                </section>
            </section>
        </PortalAppShell>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="text-sm font-semibold text-slate-500">{label}</div><div className="mt-2 text-lg font-bold text-slateDark">{value}</div></div>;
}

function Setting({ label, enabled }: { label: string; enabled: boolean }) {
    return <div className="flex items-center justify-between rounded-md border border-border bg-white p-4 shadow-sm"><div className="font-semibold text-slateDark">{label}</div><div className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600"><Shuffle className="h-4 w-4" />{enabled ? 'Enabled' : 'Disabled'}</div></div>;
}

function difficultyText(distribution: Record<string, number>) {
    const entries = Object.entries(distribution ?? {});

    if (entries.length === 0) {
        return 'Any difficulty';
    }

    return entries.map(([difficulty, count]) => `${difficulty}: ${count}`).join(', ');
}

function statusText(statuses: Record<string, number>) {
    const entries = Object.entries(statuses ?? {});

    if (entries.length === 0) {
        return 'No questions';
    }

    return entries.map(([status, count]) => `${status.replaceAll('_', ' ')}: ${count}`).join(', ');
}
