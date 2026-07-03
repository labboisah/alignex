import { Head } from '@inertiajs/react';
import { Activity, AlertTriangle, CheckCircle2, LogIn, RefreshCw, RotateCcw, StopCircle, Users, WifiOff } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Summary = {
    total_candidates: number;
    logged_in: number;
    active: number;
    submitted: number;
    disconnected: number;
    disqualified: number;
    suspicious: number;
};

type CandidateRow = {
    attempt_id: string;
    candidate_name: string;
    registration_number: string;
    status: string;
    progress: number;
    answered_questions: number;
    total_questions: number;
    login_time?: string | null;
    remaining_time_label: string;
    suspicious_event_count: number;
    ip_address?: string | null;
};

type FeedItem = {
    id: string;
    type: string;
    candidate_name?: string | null;
    registration_number?: string | null;
    message: string;
    occurred_at?: string | null;
    snapshot_url?: string | null;
};

type ProctoringEventItem = {
    id: string;
    candidate_name?: string | null;
    registration_number?: string | null;
    event_type: string;
    severity: string;
    source?: string | null;
    snapshot_url?: string | null;
    occurred_at?: string | null;
    payload?: Record<string, unknown>;
};

type MonitorPayload = {
    type: string;
    payload: {
        row?: CandidateRow;
        event_type?: string;
    };
    occurred_at: string;
};

declare global {
    interface Window {
        Echo?: {
            private: (channel: string) => {
                listen: (event: string, callback: (payload: MonitorPayload) => void) => unknown;
                stopListening?: (event: string) => void;
            };
            leave?: (channel: string) => void;
        };
    }
}

export default function ExamMonitorShow({ exam, summary: initialSummary, rows: initialRows, feed: initialFeed, events: initialEvents, broadcast }: { exam: { id: string; title: string; exam_code: string }; summary: Summary; rows: CandidateRow[]; feed: FeedItem[]; events: ProctoringEventItem[]; broadcast: { channel: string; event: string } }) {
    const [summary, setSummary] = useState(initialSummary);
    const [rows, setRows] = useState(initialRows);
    const [feed, setFeed] = useState(initialFeed);
    const [events, setEvents] = useState(initialEvents);
    const [liveStatus, setLiveStatus] = useState(window.Echo ? 'Live' : 'Polling');
    const [loading, setLoading] = useState(false);
    const [flaggedOnly, setFlaggedOnly] = useState(false);
    const [resetting, setResetting] = useState<string | null>(null);
    const [ending, setEnding] = useState(false);

    const refresh = async () => {
        setLoading(true);
        try {
            const [summaryPayload, rowsPayload, feedPayload, eventsPayload] = await Promise.all([
                getJson<Summary>(`/exams/${exam.id}/monitor/summary`),
                getJson<{ rows: CandidateRow[] }>(`/exams/${exam.id}/monitor/rows`),
                getJson<{ feed: FeedItem[] }>(`/exams/${exam.id}/monitor/feed`),
                getJson<{ events: ProctoringEventItem[] }>(`/exams/${exam.id}/monitor/events`),
            ]);
            setSummary(summaryPayload);
            setRows(rowsPayload.rows);
            setFeed(feedPayload.feed);
            setEvents(eventsPayload.events);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = window.setInterval(refresh, 10000);

        return () => window.clearInterval(timer);
    }, [exam.id]);

    useEffect(() => {
        if (!window.Echo) {
            return;
        }

        setLiveStatus('Live');
        const channel = window.Echo.private(broadcast.channel);
        channel.listen(broadcast.event, (payload) => {
            if (payload.payload.row) {
                setRows((current) => upsertRow(current, payload.payload.row as CandidateRow));
            }
            setFeed((current) => [
                {
                    id: `${payload.type}-${payload.occurred_at}`,
                    type: payload.type,
                    candidate_name: payload.payload.row?.candidate_name,
                    registration_number: payload.payload.row?.registration_number,
                    message: payload.payload.event_type ?? payload.type,
                    occurred_at: payload.occurred_at,
                },
                ...current,
            ].slice(0, 50));
            refresh();
        });

        return () => {
            window.Echo?.leave?.(broadcast.channel);
        };
    }, [broadcast.channel, broadcast.event, exam.id]);

    const sortedRows = useMemo(() => [...rows].sort((a, b) => a.registration_number.localeCompare(b.registration_number)), [rows]);
    const visibleRows = useMemo(() => flaggedOnly ? sortedRows.filter((row) => row.suspicious_event_count > 0) : sortedRows, [flaggedOnly, sortedRows]);

    const resetCandidate = async (row: CandidateRow) => {
        const reason = window.prompt(`Reset ${row.candidate_name}'s attempt? Saved answers will remain.`, 'Candidate device issue during exam.');

        if (reason === null) {
            return;
        }

        setResetting(row.attempt_id);

        try {
            const payload = await postJson<{ row: CandidateRow; summary: Summary; feed: FeedItem[] }>(`/exams/${exam.id}/monitor/attempts/${row.attempt_id}/reset`, { reason });
            setRows((current) => upsertRow(current, payload.row));
            setSummary(payload.summary);
            setFeed(payload.feed);
        } finally {
            setResetting(null);
        }
    };

    const endExam = async () => {
        if (!window.confirm('End this exam now? Active attempts will be auto-submitted.')) {
            return;
        }

        setEnding(true);

        try {
            const payload = await postJson<{ summary: Summary; rows: CandidateRow[]; feed: FeedItem[] }>(`/exams/${exam.id}/monitor/end`, {});
            setSummary(payload.summary);
            setRows(payload.rows);
            setFeed(payload.feed);
        } finally {
            setEnding(false);
        }
    };

    return (
        <PortalAppShell title="Exam Monitor">
            <Head title={`${exam.title} Monitor`} />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Supervisor Dashboard"
                    title={exam.title}
                    description={`${exam.exam_code} | ${liveStatus}`}
                    actions={<div className="flex flex-wrap gap-2"><Button type="button" variant="danger" onClick={endExam} disabled={ending}><StopCircle className="h-4 w-4" />End Exam</Button><Button type="button" variant="secondary" onClick={refresh} disabled={loading}><RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />Refresh</Button></div>}
                />
                <div className="grid gap-4 md:grid-cols-4 xl:grid-cols-7">
                    <Metric label="Total Candidates" value={summary.total_candidates} icon={Users} />
                    <Metric label="Logged In" value={summary.logged_in} icon={LogIn} />
                    <Metric label="Active" value={summary.active} icon={Activity} tone="text-success" />
                    <Metric label="Submitted" value={summary.submitted} icon={CheckCircle2} />
                    <Metric label="Disconnected" value={summary.disconnected} icon={WifiOff} tone="text-warning" />
                    <Metric label="Disqualified" value={summary.disqualified} icon={AlertTriangle} tone="text-danger" />
                    <Metric label="Suspicious" value={summary.suspicious} icon={AlertTriangle} tone="text-warning" />
                </div>

                <div className="mt-6 overflow-x-auto rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-semibold text-slateDark">Candidate Information</h2>
                        <Button type="button" variant={flaggedOnly ? 'primary' : 'secondary'} onClick={() => setFlaggedOnly((value) => !value)}>
                            <AlertTriangle className="h-4 w-4" />
                            Flagged Candidates
                        </Button>
                    </div>
                        <table className="mt-4 w-full text-left text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr><th className="py-2">Candidate Name</th><th>Registration Number</th><th>Status</th><th>Progress</th><th>Answered Questions</th><th>Total Questions</th><th>Login Time</th><th>Remaining Time</th><th>Suspicious Event Count</th><th>IP Address</th><th>Actions</th></tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {visibleRows.map((row) => (
                                    <tr key={row.attempt_id}>
                                        <td className="py-3 font-semibold">{row.candidate_name}</td>
                                        <td>{row.registration_number}</td>
                                        <td><StatusBadge label={row.status.replaceAll('_', ' ')} tone={statusTone(row.status)} /></td>
                                        <td><Progress value={row.progress} /></td>
                                        <td>{row.answered_questions}</td>
                                        <td>{row.total_questions}</td>
                                        <td>{row.login_time ? new Date(row.login_time).toLocaleTimeString() : 'Not logged in'}</td>
                                        <td>{row.remaining_time_label}</td>
                                        <td>{row.suspicious_event_count}</td>
                                        <td>{row.ip_address ?? 'N/A'}</td>
                                        <td>
                                            <Button type="button" size="sm" variant="secondary" disabled={resetting === row.attempt_id} onClick={() => resetCandidate(row)}>
                                                <RotateCcw className={`h-4 w-4 ${resetting === row.attempt_id ? 'animate-spin' : ''}`} />
                                                Reset
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                </div>

                <section className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slateDark">Live Feed</h2>
                    <div className="mt-4 grid max-h-[520px] gap-3 overflow-y-auto md:grid-cols-2 xl:grid-cols-3">
                            {feed.map((item) => (
                                <div key={item.id} className="rounded-md border border-border p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <StatusBadge label={item.type.replaceAll('_', ' ')} tone={statusTone(item.type)} />
                                        <span className="text-xs text-slate-500">{item.occurred_at ? new Date(item.occurred_at).toLocaleTimeString() : ''}</span>
                                    </div>
                                    <div className="mt-2 text-sm font-semibold text-slateDark">{item.candidate_name ?? 'System'}</div>
                                    <div className="text-xs text-slate-500">{item.registration_number}</div>
                                    <div className="mt-1 text-sm text-slate-600">{item.message.replaceAll('_', ' ')}</div>
                                </div>
                            ))}
                    </div>
                </section>

                <section className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slateDark">Event Timeline</h2>
                            <p className="mt-1 text-sm text-slate-600">Anti-cheating events are audit evidence. Software controls support supervision, but they do not guarantee cheating detection.</p>
                        </div>
                        <Button type="button" variant={flaggedOnly ? 'primary' : 'secondary'} onClick={() => setFlaggedOnly((value) => !value)}>
                            <AlertTriangle className="h-4 w-4" />
                            Flagged Candidates
                        </Button>
                    </div>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr><th className="py-2">Time</th><th>Candidate</th><th>Registration Number</th><th>Event</th><th>Severity</th><th>Evidence</th><th>Details</th></tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {events.length === 0 && (
                                    <tr><td colSpan={7} className="py-6 text-center text-slate-500">No anti-cheating events recorded.</td></tr>
                                )}
                                {events.map((event) => (
                                    <tr key={event.id}>
                                        <td className="py-3 text-slate-500">{event.occurred_at ? new Date(event.occurred_at).toLocaleTimeString() : ''}</td>
                                        <td className="font-semibold text-slateDark">{event.candidate_name ?? 'N/A'}</td>
                                        <td>{event.registration_number ?? 'N/A'}</td>
                                        <td>{event.event_type.replaceAll('_', ' ')}</td>
                                        <td><StatusBadge label={event.severity} tone={event.severity === 'critical' ? 'danger' : event.severity === 'info' ? 'neutral' : 'warning'} /></td>
                                        <td>
                                            {event.snapshot_url ? (
                                                <a href={event.snapshot_url} target="_blank" rel="noreferrer" className="inline-block">
                                                    <img src={event.snapshot_url} alt="Webcam evidence" className="h-14 w-20 rounded-md border border-border object-cover" />
                                                </a>
                                            ) : (
                                                <span className="text-slate-500">None</span>
                                            )}
                                        </td>
                                        <td className="max-w-xs truncate text-slate-600">{event.payload ? JSON.stringify(event.payload) : 'N/A'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </PortalAppShell>
    );
}

function Metric({ label, value, icon: Icon, tone = 'text-slateDark' }: { label: string; value: number; icon: typeof Users; tone?: string }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><Icon className={`h-5 w-5 ${tone}`} /><div className="mt-3 text-sm font-semibold text-slate-500">{label}</div><div className={`mt-1 text-2xl font-bold ${tone}`}>{value}</div></div>;
}

function Progress({ value }: { value: number }) {
    return <div className="min-w-28"><div className="h-2 rounded bg-slate-100"><div className="h-2 rounded bg-primary" style={{ width: `${value}%` }} /></div><div className="mt-1 text-xs font-semibold text-slate-500">{value}%</div></div>;
}

function statusTone(status: string): 'success' | 'danger' | 'warning' | 'neutral' {
    if (['active', 'login', 'answer_saved'].includes(status)) return 'success';
    if (['disqualified', 'suspicious'].includes(status)) return 'danger';
    if (['disconnected', 'auto_submit'].includes(status)) return 'warning';
    return 'neutral';
}

function upsertRow(rows: CandidateRow[], row: CandidateRow) {
    const exists = rows.some((item) => item.attempt_id === row.attempt_id);

    return exists ? rows.map((item) => item.attempt_id === row.attempt_id ? row : item) : [...rows, row];
}

async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });

    if (!response.ok) {
        throw new Error('Unable to load monitor data.');
    }

    return response.json();
}

async function postJson<T>(url: string, body: Record<string, unknown>): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error('Unable to reset candidate.');
    }

    return response.json();
}
