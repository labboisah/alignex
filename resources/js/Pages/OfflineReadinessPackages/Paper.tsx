import { Head } from '@inertiajs/react';
import { ArrowLeft, FileCheck2 } from 'lucide-react';
import { PortalAppShell, PageHeader, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type PaperOption = { label: string; text: string };
type PaperQuestion = { id: string; body: string; marks: number; options: PaperOption[] };
type PaperSubject = { id: string; name: string; code?: string | null; questions: PaperQuestion[] };
type PackagePaper = {
    id: number;
    code: string;
    version: string;
    capacity_profile: number;
    candidate_count: number;
    question_count: number;
    status: string;
    checksum_sha256: string;
    paper_rows: { subject_id: string; question_bank_id: string; question_count: number }[];
    payload: { subjects?: PaperSubject[] };
    created_at: string | null;
    updated_at: string | null;
};

export default function OfflineReadinessPackagePaper({ package: paper }: { package: PackagePaper }) {
    const subjects = paper.payload.subjects ?? [];

    return <PortalAppShell title="Generated Question Paper">
        <Head title={`Generated Paper - ${paper.code}`} />
        <section className="mx-auto max-w-5xl">
            <PageHeader eyebrow="Autoboot" title="Generated Question Paper" description={`${paper.code} v${paper.version}`} actions={<Button asChild variant="secondary"><a href="/offline-readiness-packages"><ArrowLeft className="h-4 w-4" /> Back to Packages</a></Button>} />
            <div className="mb-6 grid gap-3 md:grid-cols-5">
                <Metric label="Capacity" value={`${paper.capacity_profile}`} />
                <Metric label="Candidates" value={`${paper.candidate_count}`} />
                <Metric label="Questions" value={`${paper.question_count}`} />
                <Metric label="Subjects" value={`${subjects.length}`} />
                <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="text-sm font-semibold text-slate-500">Status</div><div className="mt-2"><StatusBadge label={paper.status} tone={paper.status === 'active' ? 'success' : 'neutral'} /></div></div>
            </div>
            <div className="mb-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm leading-6 text-green-950"><div className="flex gap-3"><FileCheck2 className="h-5 w-5 shrink-0" /><div><strong>Readiness paper snapshot</strong><p>This is a read-only synthetic paper. Correct answers and scoring keys are intentionally excluded.</p><p className="mt-1 break-all text-xs">Checksum: {paper.checksum_sha256}</p></div></div></div>
            <div className="space-y-6">{subjects.map((subject, subjectIndex) => <section key={subject.id} className="rounded-md border border-border bg-white p-5 shadow-sm"><div className="flex items-center justify-between border-b border-border pb-3"><h2 className="text-lg font-bold text-slateDark">{subjectIndex + 1}. {subject.name}</h2><span className="text-sm text-slate-500">{subject.questions.length} questions</span></div><div className="mt-5 space-y-6">{subject.questions.map((question, questionIndex) => <article key={question.id} className="border-b border-border pb-5 last:border-0 last:pb-0"><div className="flex gap-3"><span className="font-semibold text-primary">{questionIndex + 1}.</span><div className="min-w-0 flex-1"><p className="font-semibold leading-6 text-slateDark">{question.body}</p><div className="mt-3 grid gap-2 sm:grid-cols-2">{question.options.map(option => <div key={option.label} className="rounded-md border border-border bg-slate-50 px-3 py-2 text-sm text-slate-700"><span className="mr-2 font-bold text-primary">{option.label}.</span>{option.text}</div>)}</div></div></div></article>)}</div></section>)}</div>
        </section>
    </PortalAppShell>;
}

function Metric({ label, value }: { label: string; value: string }) { return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><div className="text-sm font-semibold text-slate-500">{label}</div><div className="mt-2 text-lg font-bold text-slateDark">{value}</div></div>; }
