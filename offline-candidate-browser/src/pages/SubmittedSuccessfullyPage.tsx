import { CheckCircle2 } from 'lucide-react';
import { AppLogo } from '../components/AppLogo';
import { AppShell } from '../components/AppShell';
import { PageCard } from '../components/PageCard';
import { Button } from '../components/ui/button';
import type { SubmissionSummary } from '../types/api';

export function SubmittedSuccessfullyPage({ summary, onDone }: { summary: SubmissionSummary | null; onDone: () => void }) {
    return (
        <AppShell>
            <PageCard className="max-w-xl">
                <div className="flex justify-center">
                    <AppLogo />
                </div>

                <div className="mt-6 text-center">
                    <CheckCircle2 className="mx-auto h-14 w-14 text-success" />
                    <h1 className="mt-4 text-2xl font-bold text-slateDark">Submitted Successfully</h1>
                    <p className="mt-2 text-base font-semibold text-slate-600">Your exam has been submitted successfully.</p>
                    {summary?.status === 'auto_submitted' && (
                        <p className="mt-2 rounded-md border border-accentOrange/40 bg-accentOrange/10 px-3 py-2 text-sm font-bold text-accentOrange">
                            Auto-submitted due to time expiry.
                        </p>
                    )}
                </div>

                <div className="mt-6 grid gap-3 rounded-md border border-border bg-lightBackground p-4 text-sm font-semibold text-slate-700">
                    <SummaryRow label="Candidate" value={summary?.candidate.full_name ?? '-'} />
                    <SummaryRow label="Registration No" value={summary?.candidate.registration_number ?? '-'} />
                    <SummaryRow label="Exam" value={summary?.exam.title ?? '-'} />
                    <SummaryRow label="Submitted Time" value={summary ? formatSubmittedTime(summary.submitted_at) : '-'} />
                    <SummaryRow label="Submission Type" value={summary?.status === 'auto_submitted' ? 'Auto-submitted due to time expiry' : 'Manual submission'} />
                    <SummaryRow label="Answered Questions" value={summary ? `${summary.answered_count} of ${summary.total_questions}` : '-'} />
                    {typeof summary?.score === 'number' && <SummaryRow label="Score" value={String(summary.score)} />}
                </div>

                <div className="mt-6 flex justify-center">
                    <Button size="lg" type="button" onClick={onDone}>
                        Done
                    </Button>
                </div>
            </PageCard>
        </AppShell>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-slate-500">{label}</span>
            <span className="text-right text-slateDark">{value}</span>
        </div>
    );
}

function formatSubmittedTime(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
}
