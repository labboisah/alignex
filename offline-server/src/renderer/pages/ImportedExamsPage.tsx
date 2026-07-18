import {
    AlertTriangle,
    ArrowLeft,
    ClipboardList,
    Download,
    Eye,
    Loader2,
    MonitorUp,
    Play,
    Copy,
    CheckCircle2,
    XCircle,
    RefreshCw,
    Search,
    Square,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { ConfirmDialog } from '../components/ui/confirm-dialog';
import { useServerStatus } from '../hooks/useServerStatus';
import { cn } from '@/lib/utils';

type ExamStatus = 'ready' | 'active' | 'closed' | 'exported';

type ImportedExamSummary = {
    id: string;
    title: string;
    exam_code: string;
    organization_name: string;
    status: ExamStatus;
    start_at: string | null;
    end_at: string | null;
    duration_minutes: number;
    candidate_count: number;
    question_count: number;
    active_candidates: number;
    submitted_candidates: number;
    actual_started_at: string | null;
    actual_closed_at: string | null;
};

type ExamCandidateRow = {
    id: string;
    candidate_no: string;
    full_name: string;
    group_name: string | null;
    status: string;
    attempt_status: string | null;
    started_at: string | null;
    submitted_at: string | null;
    ip_address: string | null;
    device_fingerprint: string | null;
    answered_count: number;
    total_questions: number;
};

type PaperVerification = {
    status: 'passed' | 'failed';
    candidate_count: number;
    attempt_count: number;
    candidates_with_papers: number;
    candidates_without_attempts: number;
    candidates_without_papers: number;
    question_count: number;
    min_questions_per_paper: number;
    max_questions_per_paper: number;
    duplicate_question_assignments: number;
    invalid_question_assignments: number;
    invalid_option_orders: number;
    issues: string[];
};

type ImportedExamsPageProps = {
    detailExamId?: string | null;
    onBackToList?: () => void;
    onGoToMonitor?: () => void;
    onGoToResultsExport?: () => void;
    onViewDetails: (examId: string) => void;
};

const apiBaseUrl = 'http://127.0.0.1:4080';
const statusOptions = ['all', 'ready', 'active', 'closed', 'exported'] as const;

export function ImportedExamsPage({ detailExamId = null, onBackToList, onGoToMonitor, onGoToResultsExport, onViewDetails }: ImportedExamsPageProps) {
    if (detailExamId) {
        return (
            <ExamDetailsPage
                examId={detailExamId}
                onBackToList={onBackToList ?? (() => undefined)}
                onGoToMonitor={onGoToMonitor ?? (() => undefined)}
                onGoToResultsExport={onGoToResultsExport ?? (() => undefined)}
            />
        );
    }

    return <ImportedExamsTable onViewDetails={onViewDetails} />;
}

function ImportedExamsTable({ onViewDetails }: { onViewDetails: (examId: string) => void }) {
    const [exams, setExams] = useState<ImportedExamSummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<(typeof statusOptions)[number]>('all');

    async function refresh() {
        try {
            setError(null);
            const response = await fetch(`${apiBaseUrl}/api/exams`);

            if (!response.ok) {
                throw new Error(`Server returned ${response.status}`);
            }

            const data = await response.json() as { exams: ImportedExamSummary[] };
            setExams(data.exams);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Could not load imported exams.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void refresh();
    }, []);

    const filteredExams = useMemo(() => {
        const term = search.trim().toLowerCase();

        return exams.filter((exam) => {
            const matchesSearch = term.length === 0
                || exam.title.toLowerCase().includes(term)
                || exam.exam_code.toLowerCase().includes(term)
                || exam.organization_name.toLowerCase().includes(term);
            const matchesStatus = statusFilter === 'all' || exam.status === statusFilter;

            return matchesSearch && matchesStatus;
        });
    }, [exams, search, statusFilter]);

    return (
        <div className="space-y-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slateDark">Imported Exams</h1>
                    <p className="mt-1 text-sm text-slate-500">Exam packages currently registered on this center server.</p>
                </div>
                <Button onClick={() => void refresh()} variant="outline">
                    <RefreshCw className="h-4 w-4" />
                    Refresh
                </Button>
            </div>

            <Card>
                <CardContent className="grid gap-3 pt-5 lg:grid-cols-[1fr_220px]">
                    <label className="relative block">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            className="h-10 w-full rounded-md border border-border bg-white pl-9 pr-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search title, code, or organization"
                            value={search}
                        />
                    </label>
                    <select
                        className="h-10 rounded-md border border-border bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                        onChange={(event) => setStatusFilter(event.target.value as (typeof statusOptions)[number])}
                        value={statusFilter}
                    >
                        {statusOptions.map((status) => (
                            <option key={status} value={status}>{status === 'all' ? 'All statuses' : statusLabel(status)}</option>
                        ))}
                    </select>
                </CardContent>
            </Card>

            {loading && <StateCard icon={<Loader2 className="h-5 w-5 animate-spin text-info" />} text="Loading imported exams." />}
            {error && !loading && <StateCard danger icon={<AlertTriangle className="h-5 w-5 text-danger" />} text={error} />}
            {!loading && !error && exams.length === 0 && (
                <StateCard icon={<ClipboardList className="h-5 w-5 text-slate-400" />} text="No exam package has been imported yet." />
            )}
            {!loading && !error && exams.length > 0 && filteredExams.length === 0 && (
                <StateCard icon={<Search className="h-5 w-5 text-slate-400" />} text="No exams match the current search or status filter." />
            )}

            {!loading && !error && filteredExams.length > 0 && (
                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full min-w-[980px] border-collapse text-sm">
                            <thead className="bg-lightBackground text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <TableHead>Exam Title</TableHead>
                                    <TableHead>Exam Code</TableHead>
                                    <TableHead>Organization</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Candidates</TableHead>
                                    <TableHead>Questions</TableHead>
                                    <TableHead>Duration</TableHead>
                                    <TableHead>Actions</TableHead>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredExams.map((exam) => (
                                    <tr key={exam.id} className="border-t border-border">
                                        <TableCell className="font-semibold text-slateDark">{exam.title}</TableCell>
                                        <TableCell>{exam.exam_code}</TableCell>
                                        <TableCell>{exam.organization_name}</TableCell>
                                        <TableCell><StatusBadge status={exam.status} /></TableCell>
                                        <TableCell>{exam.candidate_count}</TableCell>
                                        <TableCell>{exam.question_count}</TableCell>
                                        <TableCell>{exam.duration_minutes} min</TableCell>
                                        <TableCell>
                                            <Button onClick={() => onViewDetails(exam.id)} size="sm" variant="secondary">
                                                <Eye className="h-4 w-4" />
                                                View Details
                                            </Button>
                                        </TableCell>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function ExamDetailsPage({
    examId,
    onBackToList,
    onGoToMonitor,
    onGoToResultsExport,
}: {
    examId: string;
    onBackToList: () => void;
    onGoToMonitor: () => void;
    onGoToResultsExport: () => void;
}) {
    const { status: serverStatus, refresh: refreshServerStatus } = useServerStatus();
    const [exam, setExam] = useState<ImportedExamSummary | null>(null);
    const [candidates, setCandidates] = useState<ExamCandidateRow[]>([]);
    const [verification, setVerification] = useState<PaperVerification | null>(null);
    const [loading, setLoading] = useState(true);
    const [candidatesLoading, setCandidatesLoading] = useState(true);
    const [verificationLoading, setVerificationLoading] = useState(true);
    const [starting, setStarting] = useState(false);
    const [closing, setClosing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [closeConfirmOpen, setCloseConfirmOpen] = useState(false);
    const [toast, setToast] = useState<{ tone: 'success' | 'danger'; message: string } | null>(null);
    const [candidateSearch, setCandidateSearch] = useState('');
    const canExportResults = serverStatus?.plan_features?.result_package_export === true;

    const candidateAccessUrl = serverStatus?.candidateUrl ?? null;
    const filteredCandidates = useMemo(() => {
        const term = candidateSearch.trim().toLowerCase();

        if (term.length === 0) {
            return candidates;
        }

        return candidates.filter((candidate) => (
            candidate.full_name.toLowerCase().includes(term)
            || candidate.candidate_no.toLowerCase().includes(term)
            || (candidate.group_name ?? '').toLowerCase().includes(term)
            || (candidate.attempt_status ?? candidate.status).toLowerCase().includes(term)
        ));
    }, [candidateSearch, candidates]);
    const candidateStats = useMemo(() => ({
        total: candidates.length,
        notStarted: candidates.filter((candidate) => (candidate.attempt_status ?? candidate.status) === 'not_started').length,
        active: candidates.filter((candidate) => (candidate.attempt_status ?? candidate.status) === 'active').length,
        submitted: candidates.filter((candidate) => ['submitted', 'auto_submitted'].includes(candidate.attempt_status ?? candidate.status)).length,
    }), [candidates]);

    function showToast(nextToast: { tone: 'success' | 'danger'; message: string }) {
        setToast(nextToast);
        window.setTimeout(() => setToast(null), 4200);
    }

    async function refresh() {
        try {
            setError(null);
            const response = await fetch(`${apiBaseUrl}/api/exams/${encodeURIComponent(examId)}`);

            if (!response.ok) {
                throw new Error(response.status === 404 ? 'Exam not found.' : `Server returned ${response.status}`);
            }

            const data = await response.json() as { exam: ImportedExamSummary };
            setExam(data.exam);
            await Promise.all([refreshCandidates(), refreshVerification()]);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Could not load exam details.');
        } finally {
            setLoading(false);
        }
    }

    async function refreshCandidates() {
        try {
            setCandidatesLoading(true);
            const response = await fetch(`${apiBaseUrl}/api/exams/${encodeURIComponent(examId)}/candidates`);

            if (!response.ok) {
                throw new Error(response.status === 404 ? 'Exam not found.' : `Server returned ${response.status}`);
            }

            const data = await response.json() as { candidates: ExamCandidateRow[] };
            setCandidates(data.candidates);
        } catch (caught) {
            showToast({ tone: 'danger', message: caught instanceof Error ? caught.message : 'Could not load candidates.' });
        } finally {
            setCandidatesLoading(false);
        }
    }

    async function refreshVerification() {
        try {
            setVerificationLoading(true);
            const response = await fetch(`${apiBaseUrl}/api/exams/${encodeURIComponent(examId)}/paper-verification`);

            if (!response.ok) {
                throw new Error(response.status === 404 ? 'Exam not found.' : `Server returned ${response.status}`);
            }

            const data = await response.json() as { verification: PaperVerification };
            setVerification(data.verification);
        } catch (caught) {
            showToast({ tone: 'danger', message: caught instanceof Error ? caught.message : 'Could not verify papers.' });
        } finally {
            setVerificationLoading(false);
        }
    }

    useEffect(() => {
        void refresh();
    }, [examId]);

    async function startCurrentExam() {
        if (starting) {
            return;
        }

        try {
            setStarting(true);
            setError(null);
            const response = await fetch(`${apiBaseUrl}/api/exams/${encodeURIComponent(examId)}/start`, { method: 'POST' });
            const data = await response.json() as { exam?: ImportedExamSummary; message?: string };

            if (!response.ok || !data.exam) {
                throw new Error(data.message ?? `Server returned ${response.status}`);
            }

            setExam(data.exam);
            setConfirmOpen(false);
            await Promise.all([refreshCandidates(), refreshVerification()]);
            await refreshServerStatus();
            showToast({ tone: 'success', message: 'Exam started successfully.' });
        } catch (caught) {
            showToast({ tone: 'danger', message: caught instanceof Error ? caught.message : 'Unable to start exam.' });
        } finally {
            setStarting(false);
        }
    }

    async function closeCurrentExam() {
        if (closing) {
            return;
        }

        try {
            setClosing(true);
            setError(null);
            const response = await fetch(`${apiBaseUrl}/api/exams/${encodeURIComponent(examId)}/close`, { method: 'POST' });
            const data = await response.json() as { exam?: ImportedExamSummary; message?: string; auto_submitted_count?: number };

            if (!response.ok || !data.exam) {
                throw new Error(data.message ?? `Server returned ${response.status}`);
            }

            setExam(data.exam);
            setCloseConfirmOpen(false);
            await Promise.all([refreshCandidates(), refreshVerification()]);
            await refreshServerStatus();
            showToast({ tone: 'success', message: `Exam closed successfully. ${data.auto_submitted_count ?? 0} active attempt(s) auto-submitted.` });
        } catch (caught) {
            showToast({ tone: 'danger', message: caught instanceof Error ? caught.message : 'Unable to close exam.' });
        } finally {
            setClosing(false);
        }
    }

    async function copyCandidateUrl() {
        if (!candidateAccessUrl) {
            return;
        }

        try {
            await navigator.clipboard.writeText(candidateAccessUrl);
            showToast({ tone: 'success', message: 'Candidate URL copied.' });
        } catch {
            showToast({ tone: 'danger', message: 'Could not copy candidate URL.' });
        }
    }

    return (
        <div className="space-y-6">
            {toast && <Toast tone={toast.tone} message={toast.message} />}
            <ConfirmDialog
                confirmLabel="Start Exam"
                loading={starting}
                message="Starting this exam will allow candidates to login from the candidate computers."
                onConfirm={() => void startCurrentExam()}
                onOpenChange={setConfirmOpen}
                open={confirmOpen}
                title="Start Exam"
            />
            <ConfirmDialog
                confirmLabel="Close Exam"
                confirmTone="danger"
                loading={closing}
                message="All active candidates will be auto-submitted immediately. No new candidate login will be allowed after this exam is closed."
                onConfirm={() => void closeCurrentExam()}
                onOpenChange={setCloseConfirmOpen}
                open={closeConfirmOpen}
                title="Close Exam"
            />

            <div className="flex items-start justify-between gap-4">
                <div>
                    <Button onClick={onBackToList} size="sm" variant="ghost">
                        <ArrowLeft className="h-4 w-4" />
                        Back
                    </Button>
                    <h1 className="mt-3 text-2xl font-semibold text-slateDark">Exam Details</h1>
                </div>
                <Button onClick={() => void refresh()} variant="outline">
                    <RefreshCw className="h-4 w-4" />
                    Refresh
                </Button>
            </div>

            {loading && <StateCard icon={<Loader2 className="h-5 w-5 animate-spin text-info" />} text="Loading exam details." />}
            {error && !loading && <StateCard danger icon={<AlertTriangle className="h-5 w-5 text-danger" />} text={error} />}

            {exam && !loading && !error && (
                <>
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle className="text-xl">{exam.title}</CardTitle>
                                    <CardDescription className="mt-1">{exam.organization_name}</CardDescription>
                                </div>
                                <StatusBadge status={exam.status} />
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-4">
                            <Info label="Exam Code" value={exam.exam_code} />
                            <Info label="Duration" value={`${exam.duration_minutes} minutes`} />
                            <Info label="Candidates" value={String(exam.candidate_count)} />
                            <Info label="Questions" value={String(exam.question_count)} />
                            <Info label="Start Time" value={formatDateTime(exam.start_at)} />
                            <Info label="End Time" value={formatDateTime(exam.end_at)} />
                            <Info label="Active Candidates" value={String(exam.active_candidates)} />
                            <Info label="Submitted Candidates" value={String(exam.submitted_candidates)} />
                        </CardContent>
                    </Card>

                    <Card className={verification?.status === 'failed' ? 'border-danger/30' : verification?.status === 'passed' ? 'border-success/30' : undefined}>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>Question Paper Verification</CardTitle>
                                    <CardDescription>Checks that every imported candidate has a complete generated paper.</CardDescription>
                                </div>
                                <Button onClick={() => void refreshVerification()} size="sm" variant="outline">
                                    <RefreshCw className="h-4 w-4" />
                                    Verify
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {verificationLoading && <InlineState icon={<Loader2 className="h-5 w-5 animate-spin text-info" />} text="Verifying candidate papers." />}
                            {!verificationLoading && verification && (
                                <>
                                    <div className="flex items-center gap-3 rounded-md border border-border bg-lightBackground px-4 py-3">
                                        {verification.status === 'passed'
                                            ? <CheckCircle2 className="h-5 w-5 text-success" />
                                            : <AlertTriangle className="h-5 w-5 text-danger" />}
                                        <div>
                                            <div className="text-sm font-semibold text-slateDark">
                                                {verification.status === 'passed' ? 'All candidate papers verified' : 'Question paper verification failed'}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {verification.candidates_with_papers} of {verification.candidate_count} candidate(s) have imported papers.
                                            </div>
                                        </div>
                                    </div>

                                    <div className="grid gap-3 md:grid-cols-4">
                                        <Info label="Attempts" value={`${verification.attempt_count} / ${verification.candidate_count}`} />
                                        <Info label="Questions" value={String(verification.question_count)} />
                                        <Info label="Paper Size" value={`${verification.min_questions_per_paper} - ${verification.max_questions_per_paper}`} />
                                        <Info label="Issues" value={String(verification.issues.length)} />
                                    </div>

                                    {verification.issues.length > 0 && (
                                        <div className="rounded-md border border-danger/30 bg-danger/5 p-4 text-sm text-danger">
                                            <div className="font-semibold">Issues found</div>
                                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                                {verification.issues.map((issue) => <li key={issue}>{issue}</li>)}
                                            </ul>
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <CardTitle>Imported Candidates</CardTitle>
                                    <CardDescription>Candidate roster and attempt status for this exam package.</CardDescription>
                                </div>
                                <Button onClick={() => void refreshCandidates()} size="sm" variant="outline">
                                    <RefreshCw className="h-4 w-4" />
                                    Refresh
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 md:grid-cols-4">
                                <Info label="Total Imported" value={String(candidateStats.total)} />
                                <Info label="Not Started" value={String(candidateStats.notStarted)} />
                                <Info label="Active" value={String(candidateStats.active)} />
                                <Info label="Submitted" value={String(candidateStats.submitted)} />
                            </div>

                            <label className="relative block">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    className="h-10 w-full rounded-md border border-border bg-white pl-9 pr-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                                    onChange={(event) => setCandidateSearch(event.target.value)}
                                    placeholder="Search candidate name, registration number, group, or status"
                                    value={candidateSearch}
                                />
                            </label>

                            {candidatesLoading && <InlineState icon={<Loader2 className="h-5 w-5 animate-spin text-info" />} text="Loading candidates." />}
                            {!candidatesLoading && candidates.length === 0 && <InlineState icon={<ClipboardList className="h-5 w-5 text-slate-400" />} text="No candidates were found for this exam." />}
                            {!candidatesLoading && candidates.length > 0 && filteredCandidates.length === 0 && <InlineState icon={<Search className="h-5 w-5 text-slate-400" />} text="No candidates match the current search." />}

                            {!candidatesLoading && filteredCandidates.length > 0 && (
                                <div className="overflow-x-auto rounded-md border border-border">
                                    <table className="w-full min-w-[980px] border-collapse text-sm">
                                        <thead className="bg-lightBackground text-left text-xs uppercase text-slate-500">
                                            <tr>
                                                <TableHead>Name</TableHead>
                                                <TableHead>Registration No.</TableHead>
                                                <TableHead>Group</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead>Progress</TableHead>
                                                <TableHead>Started</TableHead>
                                                <TableHead>Submitted</TableHead>
                                                <TableHead>Device / IP</TableHead>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredCandidates.map((candidate) => (
                                                <tr key={candidate.id} className="border-t border-border">
                                                    <TableCell className="font-semibold text-slateDark">{candidate.full_name}</TableCell>
                                                    <TableCell>{candidate.candidate_no}</TableCell>
                                                    <TableCell>{candidate.group_name ?? 'None'}</TableCell>
                                                    <TableCell><CandidateStatusBadge status={candidate.attempt_status ?? candidate.status} /></TableCell>
                                                    <TableCell>{candidate.answered_count} / {candidate.total_questions}</TableCell>
                                                    <TableCell>{formatDateTime(candidate.started_at)}</TableCell>
                                                    <TableCell>{formatDateTime(candidate.submitted_at)}</TableCell>
                                                    <TableCell>
                                                        <div className="max-w-[220px] truncate text-xs text-slate-500">
                                                            {candidate.ip_address ?? 'No IP'}
                                                        </div>
                                                        <div className="max-w-[220px] truncate text-xs text-slate-400">
                                                            {candidate.device_fingerprint ? `Device: ${candidate.device_fingerprint}` : 'No device bound'}
                                                        </div>
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
                            <CardTitle>Actions</CardTitle>
                            <CardDescription>Available actions are based on the exam status.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-3">
                            {exam.status === 'ready' && (
                                <Button disabled={starting} onClick={() => setConfirmOpen(true)}>
                                    {starting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Play className="h-4 w-4" />}
                                    Start Exam
                                </Button>
                            )}
                            {exam.status === 'active' && <Button onClick={onGoToMonitor} variant="secondary"><MonitorUp className="h-4 w-4" />Monitor</Button>}
                            {exam.status === 'active' && (
                                <Button
                                    disabled={closing}
                                    onClick={() => setCloseConfirmOpen(true)}
                                    variant="danger"
                                >
                                    {closing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Square className="h-4 w-4" />}
                                    Close Exam
                                </Button>
                            )}
                            {canExportResults && exam.status === 'closed' && <Button onClick={onGoToResultsExport} variant="accent"><Download className="h-4 w-4" />Export Result</Button>}
                            {canExportResults && exam.status === 'exported' && <Button onClick={onGoToResultsExport} variant="accent"><Download className="h-4 w-4" />Export Result</Button>}
                        </CardContent>
                    </Card>

                    {exam.status === 'active' && (
                        <Card className="border-success/30">
                            <CardHeader>
                                <CardTitle>Candidate Access</CardTitle>
                                <CardDescription>Candidates can now login from the candidate computers.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center gap-3">
                                <div className="min-w-0 rounded-md border border-border bg-lightBackground px-3 py-2 font-mono text-sm font-semibold text-slateDark">
                                    {candidateAccessUrl ?? 'Local IP unavailable'}
                                </div>
                                <Button disabled={!candidateAccessUrl} onClick={() => void copyCandidateUrl()} variant="secondary">
                                    <Copy className="h-4 w-4" />
                                    Copy Candidate URL
                                </Button>
                                <Button onClick={onGoToMonitor}>
                                    <MonitorUp className="h-4 w-4" />
                                    Go to Active Monitor
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </>
            )}
        </div>
    );
}

function CandidateStatusBadge({ status }: { status: string }) {
    const tone = status === 'active'
        ? 'border-success/30 bg-success/10 text-success'
        : ['submitted', 'auto_submitted'].includes(status)
            ? 'border-info/30 bg-info/10 text-info'
            : status === 'disqualified'
                ? 'border-danger/30 bg-danger/10 text-danger'
                : 'border-slate-300 bg-slate-100 text-slate-700';

    return <Badge className={tone} variant="outline">{statusLabel(status.replace('_', ' '))}</Badge>;
}

function StatusBadge({ status }: { status: ExamStatus }) {
    return <Badge className={statusBadgeClass(status)} variant="outline">{statusLabel(status)}</Badge>;
}

function statusBadgeClass(status: ExamStatus): string {
    switch (status) {
        case 'ready':
            return 'border-info/30 bg-info/10 text-info';
        case 'active':
            return 'border-success/30 bg-success/10 text-success';
        case 'closed':
            return 'border-accentOrange/30 bg-accentOrange/10 text-accentOrange';
        case 'exported':
            return 'border-slate-300 bg-slate-100 text-slate-700';
    }
}

function statusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function StateCard({ icon, text, danger = false }: { icon: ReactNode; text: string; danger?: boolean }) {
    return (
        <Card className={danger ? 'border-danger/30' : undefined}>
            <CardContent className="flex items-center gap-3 pt-5 text-sm text-slate-600">
                {icon}
                {text}
            </CardContent>
        </Card>
    );
}

function InlineState({ icon, text }: { icon: ReactNode; text: string }) {
    return (
        <div className="flex items-center gap-3 rounded-md border border-border bg-lightBackground px-4 py-3 text-sm text-slate-600">
            {icon}
            {text}
        </div>
    );
}

function TableHead({ children }: { children: ReactNode }) {
    return <th className="px-4 py-3 font-semibold">{children}</th>;
}

function TableCell({ children, className }: { children: ReactNode; className?: string }) {
    return <td className={cn('px-4 py-3 text-slate-600', className)}>{children}</td>;
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <div className="text-xs font-medium uppercase text-slate-500">{label}</div>
            <div className="mt-1 truncate font-semibold text-slateDark">{value}</div>
        </div>
    );
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Not set';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function Toast({ tone, message }: { tone: 'success' | 'danger'; message: string }) {
    return (
        <div className="fixed right-6 top-6 z-50 rounded-md border border-border bg-white px-4 py-3 shadow-lg">
            <div className="flex items-center gap-3 text-sm font-semibold text-slateDark">
                {tone === 'success' ? <CheckCircle2 className="h-5 w-5 text-success" /> : <XCircle className="h-5 w-5 text-danger" />}
                {message}
            </div>
        </div>
    );
}
