import { AlertTriangle, CheckCircle2, Clipboard, FolderOpen, Loader2, RefreshCw, Upload } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { cn } from '@/lib/utils';

type ExamStatus = 'ready' | 'active' | 'closed' | 'exported';

type ImportedExamSummary = {
    id: string;
    title: string;
    exam_code: string;
    organization_name: string;
    status: ExamStatus;
    duration_minutes: number;
    candidate_count: number;
    question_count: number;
    submitted_candidates: number;
};

type ExportSummary = {
    export_folder_path: string;
    json_filename: string;
    csv_filename: string;
    result_hash: string;
    exported_at: string;
};

type ToastState = {
    tone: 'success' | 'danger';
    message: string;
};

declare global {
    interface Window {
        alignex?: {
            openExportFolder?: (folderPath: string) => Promise<{ success: boolean; message?: string }>;
        };
    }
}

const apiBaseUrl = 'http://127.0.0.1:4080';

export function ResultsExportPage() {
    const [exams, setExams] = useState<ImportedExamSummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [exportingExamId, setExportingExamId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);
    const [summary, setSummary] = useState<ExportSummary | null>(null);
    const [toast, setToast] = useState<ToastState | null>(null);

    const closedExams = useMemo(() => exams.filter((exam) => exam.status === 'closed'), [exams]);

    function showToast(nextToast: ToastState) {
        setToast(nextToast);
        window.setTimeout(() => setToast(null), 4200);
    }

    async function refresh() {
        try {
            setRefreshing(true);
            setError(null);
            const response = await fetch(`${apiBaseUrl}/api/exams`);
            const data = await response.json() as { exams?: ImportedExamSummary[]; message?: string };

            if (!response.ok || !data.exams) {
                throw new Error(data.message ?? `Server returned ${response.status}`);
            }

            setExams(data.exams);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Could not load exams ready for export.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useEffect(() => {
        void refresh();
    }, []);

    async function exportExam(exam: ImportedExamSummary) {
        try {
            setExportingExamId(exam.id);
            setExportError(null);
            setSummary(null);
            const response = await fetch(`${apiBaseUrl}/api/results/export`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ exam_id: exam.id }),
            });
            const data = await response.json() as { summary?: ExportSummary; message?: string; code?: string };

            if (!response.ok || !data.summary) {
                throw new Error(data.message ?? `Export failed with status ${response.status}.`);
            }

            setSummary(data.summary);
            showToast({ tone: 'success', message: 'Result export completed successfully.' });
            await refresh();
        } catch (caught) {
            const message = caught instanceof Error ? caught.message : 'Unable to export results.';
            setExportError(message);
            showToast({ tone: 'danger', message });
        } finally {
            setExportingExamId(null);
        }
    }

    async function copyHash() {
        if (!summary) {
            return;
        }

        try {
            await navigator.clipboard.writeText(summary.result_hash);
            showToast({ tone: 'success', message: 'Result hash copied.' });
        } catch {
            showToast({ tone: 'danger', message: 'Could not copy result hash.' });
        }
    }

    async function openExportFolder() {
        if (!summary) {
            return;
        }

        if (!window.alignex?.openExportFolder) {
            showToast({ tone: 'danger', message: 'Open folder is only available in the Electron app.' });
            return;
        }

        const result = await window.alignex.openExportFolder(summary.export_folder_path);

        if (result.success) {
            showToast({ tone: 'success', message: 'Export folder opened.' });
            return;
        }

        showToast({ tone: 'danger', message: result.message ?? 'Could not open export folder.' });
    }

    return (
        <div className="space-y-6">
            {toast && <Toast tone={toast.tone} message={toast.message} />}

            <div className="flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slateDark">Results Export</h1>
                    <p className="mt-1 text-sm text-slate-500">Export closed exam results as signed local JSON and CSV files.</p>
                </div>
                <Button disabled={refreshing} onClick={() => void refresh()} variant="outline">
                    {refreshing ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                    Refresh
                </Button>
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                <Card>
                    <CardHeader>
                        <CardTitle>Closed Exams Ready for Export</CardTitle>
                        <CardDescription>Only exams with Closed status can be exported.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {loading && <StateRow icon={<Loader2 className="h-5 w-5 animate-spin text-info" />} text="Loading closed exams." />}
                        {error && !loading && <StateRow danger icon={<AlertTriangle className="h-5 w-5 text-danger" />} text={error} />}
                        {!loading && !error && closedExams.length === 0 && (
                            <StateRow icon={<CheckCircle2 className="h-5 w-5 text-slate-400" />} text="No closed exams are waiting for export." />
                        )}
                        {!loading && !error && closedExams.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[880px] border-collapse text-sm">
                                    <thead className="bg-lightBackground text-left text-xs uppercase text-slate-500">
                                        <tr>
                                            <TableHead>Exam</TableHead>
                                            <TableHead>Organization</TableHead>
                                            <TableHead>Candidates</TableHead>
                                            <TableHead>Submitted</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Action</TableHead>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {closedExams.map((exam) => (
                                            <tr key={exam.id} className="border-t border-border">
                                                <TableCell className="font-semibold text-slateDark">
                                                    <div>{exam.title}</div>
                                                    <div className="mt-1 text-xs font-medium text-slate-500">{exam.exam_code}</div>
                                                </TableCell>
                                                <TableCell>{exam.organization_name}</TableCell>
                                                <TableCell>{exam.candidate_count}</TableCell>
                                                <TableCell>{exam.submitted_candidates}</TableCell>
                                                <TableCell><Badge className="border-accentOrange/30 bg-accentOrange/10 text-accentOrange" variant="outline">Closed</Badge></TableCell>
                                                <TableCell>
                                                    <Button
                                                        disabled={exportingExamId !== null}
                                                        onClick={() => void exportExam(exam)}
                                                        size="sm"
                                                        variant="accent"
                                                    >
                                                        {exportingExamId === exam.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                                                        Export Result
                                                    </Button>
                                                </TableCell>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Latest Export</CardTitle>
                        <CardDescription>Files and hash from the most recent export in this session.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {exportError && (
                            <div className="rounded-md border border-danger/30 bg-danger/5 p-3 text-sm font-semibold text-danger">
                                {exportError}
                            </div>
                        )}
                        {!summary && !exportError && (
                            <div className="rounded-md border border-border bg-lightBackground p-4 text-sm text-slate-500">
                                Export a closed exam to see the generated file details here.
                            </div>
                        )}
                        {summary && (
                            <>
                                <SummaryItem label="Export Folder" value={summary.export_folder_path} mono />
                                <SummaryItem label="JSON Filename" value={summary.json_filename} />
                                <SummaryItem label="CSV Filename" value={summary.csv_filename} />
                                <SummaryItem label="Result Hash" value={summary.result_hash} mono />
                                <SummaryItem label="Exported Time" value={formatDateTime(summary.exported_at)} />
                                <div className="flex flex-wrap gap-3 pt-2">
                                    <Button onClick={() => void copyHash()} variant="outline">
                                        <Clipboard className="h-4 w-4" />
                                        Copy Hash
                                    </Button>
                                    <Button onClick={() => void openExportFolder()}>
                                        <FolderOpen className="h-4 w-4" />
                                        Open Export Folder
                                    </Button>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function StateRow({ icon, text, danger = false }: { icon: React.ReactNode; text: string; danger?: boolean }) {
    return (
        <div className={cn('flex items-center gap-3 p-5 text-sm text-slate-600', danger && 'text-danger')}>
            {icon}
            {text}
        </div>
    );
}

function TableHead({ children }: { children: React.ReactNode }) {
    return <th className="px-4 py-3 font-semibold">{children}</th>;
}

function TableCell({ children, className }: { children: React.ReactNode; className?: string }) {
    return <td className={cn('px-4 py-3 text-slate-600', className)}>{children}</td>;
}

function SummaryItem({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="min-w-0 rounded-md border border-border bg-lightBackground p-3">
            <div className="text-xs font-semibold uppercase text-slate-500">{label}</div>
            <div className={cn('mt-1 break-words text-sm font-semibold text-slateDark', mono && 'font-mono')}>{value}</div>
        </div>
    );
}

function Toast({ tone, message }: ToastState) {
    return (
        <div className="fixed right-6 top-6 z-50 rounded-md border border-border bg-white px-4 py-3 shadow-lg">
            <div className="flex items-center gap-3 text-sm font-semibold text-slateDark">
                {tone === 'success'
                    ? <CheckCircle2 className="h-5 w-5 text-success" />
                    : <AlertTriangle className="h-5 w-5 text-danger" />}
                {message}
            </div>
        </div>
    );
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
