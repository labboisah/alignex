import { Loader2, RefreshCw, Send, TriangleAlert } from 'lucide-react';
import { Button } from '../ui/button';

export function SubmitConfirmDialog({
    open,
    candidateName,
    examTitle,
    answeredCount,
    unansweredCount,
    pendingCount,
    submitting,
    error,
    onClose,
    onConfirm,
    onRetrySync,
}: {
    open: boolean;
    candidateName: string;
    examTitle: string;
    answeredCount: number;
    unansweredCount: number;
    pendingCount: number;
    submitting: boolean;
    error: string | null;
    onClose: () => void;
    onConfirm: () => void;
    onRetrySync: () => void;
}) {
    if (!open) {
        return null;
    }

    const hasPendingAnswers = pendingCount > 0;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slateDark/50 p-4">
            <div className="w-full max-w-lg rounded-md border border-border bg-white p-6 shadow-xl">
                <div className="flex items-start gap-3">
                    <TriangleAlert className="mt-1 h-6 w-6 text-accentOrange" />
                    <div>
                        <h2 className="text-xl font-bold text-slateDark">Submit Exam</h2>
                        <p className="mt-2 text-sm font-semibold text-danger">This is final. You cannot change answers after submission.</p>
                    </div>
                </div>

                <div className="mt-5 grid gap-3 rounded-md border border-border bg-lightBackground p-4 text-sm font-semibold text-slate-700">
                    <SummaryRow label="Candidate" value={candidateName} />
                    <SummaryRow label="Exam" value={examTitle} />
                    <SummaryRow label="Answered" value={String(answeredCount)} />
                    <SummaryRow label="Unanswered" value={String(unansweredCount)} />
                    <SummaryRow label="Pending sync" value={String(pendingCount)} tone={hasPendingAnswers ? 'warning' : 'normal'} />
                </div>

                {hasPendingAnswers && (
                    <div className="mt-4 rounded-md border border-accentOrange/40 bg-accentOrange/10 p-3 text-sm font-semibold text-accentOrange">
                        Sync pending answers before final submission.
                    </div>
                )}

                {error && (
                    <div className="mt-4 rounded-md border border-danger/30 bg-danger/5 p-3 text-sm font-semibold text-danger">
                        {error}
                    </div>
                )}

                <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button disabled={submitting} onClick={onClose} type="button" variant="outline">
                        Cancel
                    </Button>
                    {hasPendingAnswers && (
                        <Button disabled={submitting} onClick={onRetrySync} type="button" variant="secondary">
                            <RefreshCw className="h-4 w-4" />
                            Retry Sync
                        </Button>
                    )}
                    <Button disabled={submitting} onClick={onConfirm} type="button" variant="danger">
                        {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                        {hasPendingAnswers ? 'Sync & Submit' : 'Submit Final Answer Sheet'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function SummaryRow({ label, value, tone = 'normal' }: { label: string; value: string; tone?: 'normal' | 'warning' }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-slate-500">{label}</span>
            <span className={tone === 'warning' ? 'text-accentOrange' : 'text-slateDark'}>{value}</span>
        </div>
    );
}
