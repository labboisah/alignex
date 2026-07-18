import { AlertTriangle, Loader2, RefreshCw, RotateCcw, ShieldAlert, Square, UserCheck, UserMinus, Users } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { ConfirmDialog } from '../components/ui/confirm-dialog';
import { loadSocketIo } from '../lib/socket';
import { cn } from '@/lib/utils';

type ExamSummary = {
    id: string;
    title: string;
    exam_code: string;
    status: string;
};

type MonitorSummary = {
    total_candidates: number;
    not_started: number;
    active: number;
    submitted: number;
    auto_submitted: number;
    disqualified: number;
    disconnected: number;
};

type MonitorCandidate = {
    id: string;
    full_name: string;
    registration_number: string;
    status: string;
    answered_count: number;
    total_questions: number;
    unanswered_count: number;
    progress_percentage: number;
    ip_address: string | null;
    device_fingerprint: string | null;
    started_at: string | null;
    last_saved_at: string | null;
    submitted_at: string | null;
};

type EventLogItem = {
    id: string;
    type: string;
    message: string;
    time: string;
};

type LiveEventPayload = Record<string, unknown>;

const apiBaseUrl = 'http://127.0.0.1:4080';
const liveEvents = ['candidate_logged_in', 'candidate_progress_updated', 'candidate_submitted', 'candidate_disconnected', 'candidate_reconnected', 'candidate_device_reset', 'exam_closed'];

export function ActiveMonitorPage() {
    const [exam, setExam] = useState<ExamSummary | null>(null);
    const [summary, setSummary] = useState<MonitorSummary | null>(null);
    const [candidates, setCandidates] = useState<MonitorCandidate[]>([]);
    const [events, setEvents] = useState<EventLogItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [closing, setClosing] = useState(false);
    const [resettingCandidateId, setResettingCandidateId] = useState<string | null>(null);
    const [closeConfirmOpen, setCloseConfirmOpen] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [toast, setToast] = useState<{ tone: 'success' | 'danger'; message: string } | null>(null);
    const [liveProcessingMessage, setLiveProcessingMessage] = useState<string | null>(null);
    const liveRefreshTimer = useRef<number | null>(null);

    const submittedTotal = (summary?.submitted ?? 0) + (summary?.auto_submitted ?? 0);

    async function refresh() {
        try {
            setRefreshing(true);
            setError(null);

            const examId = exam?.id ?? await resolveActiveExamId();

            if (!examId) {
                setExam(null);
                setSummary(null);
                setCandidates([]);
                return;
            }

            const [examResponse, summaryResponse, candidatesResponse] = await Promise.all([
                fetch(`${apiBaseUrl}/api/exams/${encodeURIComponent(examId)}`),
                fetch(`${apiBaseUrl}/api/monitor/exams/${encodeURIComponent(examId)}`),
                fetch(`${apiBaseUrl}/api/monitor/exams/${encodeURIComponent(examId)}/candidates`),
            ]);

            if (!examResponse.ok || !summaryResponse.ok || !candidatesResponse.ok) {
                throw new Error('Unable to load active monitor data.');
            }

            const examData = await examResponse.json() as { exam: ExamSummary };
            const summaryData = await summaryResponse.json() as { summary: MonitorSummary };
            const candidatesData = await candidatesResponse.json() as { candidates: MonitorCandidate[] };

            setExam(examData.exam);
            setSummary(summaryData.summary);
            setCandidates(candidatesData.candidates);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Could not load active monitor.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useEffect(() => {
        void refresh();
    }, []);

    function showToast(nextToast: { tone: 'success' | 'danger'; message: string }) {
        setToast(nextToast);
        window.setTimeout(() => setToast(null), 4200);
    }

    async function closeCurrentExam() {
        if (!exam || closing) {
            return;
        }

        try {
            setClosing(true);
            setError(null);
            const response = await fetch(`${apiBaseUrl}/api/exams/${encodeURIComponent(exam.id)}/close`, { method: 'POST' });
            const data = await response.json() as { exam?: ExamSummary; message?: string; auto_submitted_count?: number };

            if (!response.ok || !data.exam) {
                throw new Error(data.message ?? `Server returned ${response.status}`);
            }

            setExam(data.exam);
            setCloseConfirmOpen(false);
            showToast({ tone: 'success', message: `Exam closed successfully. ${data.auto_submitted_count ?? 0} active attempt(s) auto-submitted.` });
            await refresh();
        } catch (caught) {
            showToast({ tone: 'danger', message: caught instanceof Error ? caught.message : 'Unable to close exam.' });
        } finally {
            setClosing(false);
        }
    }

    async function resetCandidateDevice(candidate: MonitorCandidate) {
        if (!exam || resettingCandidateId || candidate.status !== 'active') {
            return;
        }

        try {
            setResettingCandidateId(candidate.id);
            const response = await fetch(`${apiBaseUrl}/api/monitor/exams/${encodeURIComponent(exam.id)}/candidates/${encodeURIComponent(candidate.id)}/reset-device`, {
                method: 'POST',
            });
            const data = await response.json() as { message?: string };

            if (!response.ok) {
                throw new Error(data.message ?? `Server returned ${response.status}`);
            }

            showToast({ tone: 'success', message: `${candidate.full_name} can now login from another device.` });
            addEvent('candidate_device_reset', { candidate_id: candidate.id });
            await refresh();
        } catch (caught) {
            showToast({ tone: 'danger', message: caught instanceof Error ? caught.message : 'Unable to reset candidate device.' });
        } finally {
            setResettingCandidateId(null);
        }
    }

    useEffect(() => {
        let socket: { on: (event: string, callback: (payload?: unknown) => void) => void; off: (event: string) => void; disconnect: () => void } | null = null;
        let cancelled = false;

        async function connectSocket() {
            try {
                const io = await loadSocketIo();

                if (cancelled) {
                    return;
                }

                socket = io(apiBaseUrl);

                for (const eventName of liveEvents) {
                    socket.on(eventName, (payload) => {
                        addEvent(eventName, payload);
                        applyLiveEvent(eventName, payload);
                        setLiveProcessingMessage(describeProcessingEvent(eventName, payload));
                        scheduleLiveRefresh();
                    });
                }
            } catch (caught) {
                addEvent('monitor_socket_error', { message: caught instanceof Error ? caught.message : 'Socket connection failed.' });
            }
        }

        void connectSocket();

        return () => {
            cancelled = true;

            if (socket) {
                if (liveRefreshTimer.current) {
                    window.clearTimeout(liveRefreshTimer.current);
                }

                for (const eventName of liveEvents) {
                    socket.off(eventName);
                }

                socket.disconnect();
            }
        };
    }, [exam?.id]);

    const rows = useMemo(() => candidates, [candidates]);

    return (
        <div className="space-y-6">
            {toast && <MonitorToast tone={toast.tone} message={toast.message} />}
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
                    <h1 className="text-2xl font-semibold text-slateDark">Active Monitor</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {exam ? `${exam.title} (${exam.exam_code})` : 'Live candidate supervision for the active exam.'}
                    </p>
                </div>
                <div className="flex flex-wrap justify-end gap-3">
                    {exam?.status === 'active' && (
                        <Button
                            disabled={closing}
                            onClick={() => setCloseConfirmOpen(true)}
                            variant="danger"
                        >
                            {closing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Square className="h-4 w-4" />}
                            Close Exam
                        </Button>
                    )}
                    <Button disabled={refreshing} onClick={() => void refresh()} variant="outline">
                        {refreshing ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                        Refresh
                    </Button>
                </div>
            </div>

            {liveProcessingMessage && (
                <div className="flex items-center gap-3 rounded-md border border-info/30 bg-info/5 px-4 py-3 text-sm font-semibold text-info">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    {liveProcessingMessage}
                </div>
            )}

            {loading && <StateCard icon={<Loader2 className="h-5 w-5 animate-spin text-info" />} text="Loading active monitor." />}
            {error && !loading && <StateCard danger icon={<AlertTriangle className="h-5 w-5 text-danger" />} text={error} />}
            {!loading && !error && !exam && <StateCard icon={<ShieldAlert className="h-5 w-5 text-accentOrange" />} text="No active exam is currently running." />}

            {!loading && !error && exam && summary && (
                <>
                    <section className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                        <SummaryCard icon={<Users className="h-5 w-5 text-info" />} label="Total Candidates" value={summary.total_candidates} />
                        <SummaryCard icon={<UserCheck className="h-5 w-5 text-success" />} label="Active" value={summary.active} />
                        <SummaryCard icon={<UserCheck className="h-5 w-5 text-primary" />} label="Submitted" value={submittedTotal} />
                        <SummaryCard icon={<Users className="h-5 w-5 text-slate-400" />} label="Not Started" value={summary.not_started} />
                        <SummaryCard icon={<UserMinus className="h-5 w-5 text-accentOrange" />} label="Disconnected" value={summary.disconnected} />
                        <SummaryCard icon={<ShieldAlert className="h-5 w-5 text-danger" />} label="Disqualified" value={summary.disqualified} />
                    </section>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Candidate Monitor</CardTitle>
                                <CardDescription>Live progress and session state for all assigned candidates.</CardDescription>
                            </CardHeader>
                            <CardContent className="overflow-x-auto p-0">
                                <table className="w-full min-w-[1040px] border-collapse text-sm">
                                    <thead className="bg-lightBackground text-left text-xs uppercase text-slate-500">
                                        <tr>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Reg No</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Questions</TableHead>
                                            <TableHead>Answered</TableHead>
                                            <TableHead>Unanswered</TableHead>
                                            <TableHead>Progress</TableHead>
                                            <TableHead>Last Save</TableHead>
                                            <TableHead>IP Address</TableHead>
                                            <TableHead>Login Time</TableHead>
                                            <TableHead>Submitted Time</TableHead>
                                            <TableHead>Action</TableHead>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.length === 0 && (
                                            <tr className="border-t border-border">
                                                <td className="px-4 py-8 text-center text-sm text-slate-500" colSpan={12}>
                                                    No candidate sessions are available for this exam yet.
                                                </td>
                                            </tr>
                                        )}
                                        {rows.map((candidate) => (
                                            <tr key={candidate.id} className="border-t border-border">
                                                <TableCell className="font-semibold text-slateDark">{candidate.full_name}</TableCell>
                                                <TableCell>{candidate.registration_number}</TableCell>
                                                <TableCell><StatusBadge status={candidate.status} /></TableCell>
                                                <TableCell>{candidate.total_questions}</TableCell>
                                                <TableCell>{candidate.answered_count}</TableCell>
                                                <TableCell>{candidate.unanswered_count}</TableCell>
                                                <TableCell>
                                                    <div className="min-w-44">
                                                        <div className="mb-1 flex items-center justify-between text-xs text-slate-500">
                                                            <span>{candidate.answered_count}/{candidate.total_questions}</span>
                                                            <span>{candidate.progress_percentage}%</span>
                                                        </div>
                                                        <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                                            <div className="h-full rounded-full bg-primary" style={{ width: `${candidate.progress_percentage}%` }} />
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{formatDateTime(candidate.last_saved_at)}</TableCell>
                                                <TableCell>{candidate.ip_address ?? '-'}</TableCell>
                                                <TableCell>{formatDateTime(candidate.started_at)}</TableCell>
                                                <TableCell>{formatDateTime(candidate.submitted_at)}</TableCell>
                                                <TableCell>
                                                    <Button
                                                        disabled={candidate.status !== 'active' || resettingCandidateId === candidate.id}
                                                        onClick={() => void resetCandidateDevice(candidate)}
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        {resettingCandidateId === candidate.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <RotateCcw className="h-4 w-4" />}
                                                        Reset
                                                    </Button>
                                                </TableCell>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Live Event Log</CardTitle>
                                <CardDescription>Candidate activity received from Socket.IO.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {events.length === 0 && <div className="text-sm text-slate-500">No live events received yet.</div>}
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    {events.map((event) => (
                                        <div key={event.id} className="rounded-md border border-border bg-lightBackground p-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <Badge variant="secondary">{event.type}</Badge>
                                                <span className="text-xs text-slate-500">{formatDateTime(event.time)}</span>
                                            </div>
                                            <div className="mt-2 text-sm font-medium text-slateDark">{event.message}</div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </>
            )}
        </div>
    );

    function addEvent(type: string, payload: unknown) {
        setEvents((current) => [
            {
                id: `${Date.now()}-${Math.random()}`,
                type,
                message: describeEvent(type, payload),
                time: new Date().toISOString(),
            },
            ...current,
        ].slice(0, 40));
    }

    function scheduleLiveRefresh() {
        if (liveRefreshTimer.current) {
            window.clearTimeout(liveRefreshTimer.current);
        }

        liveRefreshTimer.current = window.setTimeout(() => {
            void refresh().finally(() => {
                window.setTimeout(() => setLiveProcessingMessage(null), 700);
            });
        }, 250);
    }

    function applyLiveEvent(type: string, payload: unknown) {
        const record = toLivePayload(payload);
        const candidateId = typeof record.candidate_id === 'string' ? record.candidate_id : null;

        if (!candidateId) {
            return;
        }

        if (type === 'candidate_progress_updated') {
            setCandidates((current) => current.map((candidate) => {
                if (candidate.id !== candidateId) {
                    return candidate;
                }

                const answeredCount = numberFromPayload(record.answered_count, candidate.answered_count);
                const totalQuestions = numberFromPayload(record.total_questions, candidate.total_questions);
                const progressPercentage = numberFromPayload(
                    record.progress_percentage,
                    totalQuestions > 0 ? Math.round((answeredCount / totalQuestions) * 100) : candidate.progress_percentage,
                );

                return {
                    ...candidate,
                    status: candidate.status === 'not_started' ? 'active' : candidate.status,
                    answered_count: answeredCount,
                    total_questions: totalQuestions,
                    unanswered_count: numberFromPayload(record.unanswered_count, Math.max(totalQuestions - answeredCount, 0)),
                    progress_percentage: progressPercentage,
                    last_saved_at: typeof record.saved_at === 'string' ? record.saved_at : candidate.last_saved_at,
                };
            }));
            return;
        }

        if (type === 'candidate_submitted') {
            setCandidates((current) => current.map((candidate) => {
                if (candidate.id !== candidateId) {
                    return candidate;
                }

                const answeredCount = numberFromPayload(record.answered_count, candidate.answered_count);
                const totalQuestions = numberFromPayload(record.total_questions, candidate.total_questions);

                return {
                    ...candidate,
                    status: typeof record.status === 'string' ? record.status : 'submitted',
                    answered_count: answeredCount,
                    total_questions: totalQuestions,
                    unanswered_count: Math.max(totalQuestions - answeredCount, 0),
                    progress_percentage: totalQuestions > 0 ? Math.round((answeredCount / totalQuestions) * 100) : candidate.progress_percentage,
                    submitted_at: typeof record.submitted_at === 'string' ? record.submitted_at : new Date().toISOString(),
                };
            }));
        }
    }
}

async function resolveActiveExamId(): Promise<string | null> {
    const response = await fetch(`${apiBaseUrl}/api/exams`);

    if (!response.ok) {
        throw new Error('Unable to find active exam.');
    }

    const data = await response.json() as { exams: ExamSummary[] };
    return data.exams.find((exam) => exam.status === 'active')?.id ?? null;
}

function SummaryCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: number }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 pt-5">
                <div className="rounded-md border border-border bg-lightBackground p-2">{icon}</div>
                <div>
                    <div className="text-xs font-semibold uppercase text-slate-500">{label}</div>
                    <div className="mt-1 text-3xl font-bold text-slateDark">{value}</div>
                </div>
            </CardContent>
        </Card>
    );
}

function StatusBadge({ status }: { status: string }) {
    return <Badge className={statusBadgeClass(status)} variant="outline">{statusLabel(status)}</Badge>;
}

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'active':
            return 'border-success/30 bg-success/10 text-success';
        case 'submitted':
        case 'auto_submitted':
            return 'border-primary/30 bg-primary/10 text-primary';
        case 'disqualified':
            return 'border-danger/30 bg-danger/10 text-danger';
        case 'not_started':
            return 'border-slate-300 bg-slate-100 text-slate-600';
        default:
            return 'border-accentOrange/30 bg-accentOrange/10 text-accentOrange';
    }
}

function statusLabel(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function TableHead({ children }: { children: React.ReactNode }) {
    return <th className="px-4 py-3 font-semibold">{children}</th>;
}

function TableCell({ children, className }: { children: React.ReactNode; className?: string }) {
    return <td className={cn('px-4 py-3 text-slate-600', className)}>{children}</td>;
}

function StateCard({ icon, text, danger = false }: { icon: React.ReactNode; text: string; danger?: boolean }) {
    return (
        <Card className={danger ? 'border-danger/30' : undefined}>
            <CardContent className="flex items-center gap-3 pt-5 text-sm text-slate-600">
                {icon}
                {text}
            </CardContent>
        </Card>
    );
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function describeEvent(type: string, payload: unknown): string {
    const record = typeof payload === 'object' && payload !== null ? payload as Record<string, unknown> : {};
    const candidate = typeof record.candidate_id === 'string' ? record.candidate_id : 'Candidate';

    switch (type) {
        case 'candidate_logged_in':
            return 'Candidate logged in.';
        case 'candidate_progress_updated':
            return `${candidate} answer saved. ${formatProgressFromPayload(record)}`;
        case 'candidate_submitted':
            return `${candidate} submitted. ${formatProgressFromPayload(record)}`;
        case 'candidate_disconnected':
            return `${candidate} disconnected.`;
        case 'candidate_reconnected':
            return `${candidate} reconnected.`;
        case 'candidate_device_reset':
            return `${candidate} device binding reset.`;
        case 'exam_closed':
            return 'Exam closed by supervisor.';
        default:
            return typeof record.message === 'string' ? record.message : 'Live monitor event received.';
    }
}

function describeProcessingEvent(type: string, payload: unknown): string {
    const record = toLivePayload(payload);
    const candidate = typeof record.candidate_id === 'string' ? record.candidate_id : 'candidate';

    switch (type) {
        case 'candidate_progress_updated':
            return `Processing saved answer for ${candidate}.`;
        case 'candidate_submitted':
            return `Processing submission for ${candidate}.`;
        case 'candidate_logged_in':
            return 'Processing candidate login.';
        case 'candidate_disconnected':
        case 'candidate_reconnected':
            return 'Updating candidate connection state.';
        case 'candidate_device_reset':
            return 'Updating candidate device reset.';
        case 'exam_closed':
            return 'Processing exam close updates.';
        default:
            return 'Processing live monitor update.';
    }
}

function toLivePayload(payload: unknown): LiveEventPayload {
    return typeof payload === 'object' && payload !== null ? payload as LiveEventPayload : {};
}

function numberFromPayload(value: unknown, fallback: number): number {
    return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

function formatProgressFromPayload(record: Record<string, unknown>): string {
    const answered = typeof record.answered_count === 'number' ? record.answered_count : null;
    const total = typeof record.total_questions === 'number' ? record.total_questions : null;

    if (answered === null || total === null) {
        return '';
    }

    return `${answered}/${total} answered.`;
}

function MonitorToast({ tone, message }: { tone: 'success' | 'danger'; message: string }) {
    return (
        <div className={cn(
            'fixed right-5 top-5 z-50 rounded-md border px-4 py-3 text-sm font-semibold shadow-lg',
            tone === 'success'
                ? 'border-success/30 bg-success/10 text-success'
                : 'border-danger/30 bg-danger/10 text-danger',
        )}>
            {message}
        </div>
    );
}
