import { AlertTriangle, CheckCircle2, Loader2, MonitorCheck, Play } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ExamScreen, type SubmissionSummary } from './ExamScreen';

type CandidateLoginResponse = {
    attempt_token: string;
    candidate: {
        id: string;
        registration_number: string;
        full_name: string;
        group_name: string | null;
    };
    exam: {
        id: string;
        exam_code: string;
        title: string;
        organization_name: string;
        duration_minutes: number;
        total_questions: number;
        started_at: string | null;
    };
    remaining_time_seconds: number;
};

export type CandidateExamPayload = {
    candidate: CandidateLoginResponse['candidate'];
    exam: CandidateLoginResponse['exam'];
    questions: Array<{
        id: string;
        subject_id: string | null;
        question_type: string;
        body: string;
        marks: number;
        display_order: number;
        question_image_url?: string | null;
    }>;
    options: Array<{
        id: string;
        question_id: string;
        option_label: string;
        body: string;
        display_order: number;
    }>;
    saved_answers: Array<{
        question_id: string;
        option_ids: string[];
        text_answer: string | null;
        saved_at: string;
    }>;
    remaining_time_seconds: number;
};

type HealthStatus = {
    serverStatus: string;
    localIpAddress: string;
};

const apiBaseUrl = window.location.origin;

export function CandidateApp() {
    const [examCode, setExamCode] = useState('');
    const [registrationNumber, setRegistrationNumber] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [health, setHealth] = useState<HealthStatus | null>(null);
    const [session, setSession] = useState<CandidateLoginResponse | null>(null);
    const [examPayload, setExamPayload] = useState<CandidateExamPayload | null>(null);
    const [submitted, setSubmitted] = useState(false);
    const [submissionSummary, setSubmissionSummary] = useState<SubmissionSummary | null>(null);
    const deviceFingerprint = useMemo(() => getDeviceFingerprint(), []);

    useEffect(() => {
        let alive = true;

        async function loadHealth() {
            try {
                const response = await fetch(`${apiBaseUrl}/api/health`);
                if (!response.ok) return;
                const data = await response.json() as HealthStatus;
                if (alive) setHealth(data);
            } catch {
                if (alive) setHealth(null);
            }
        }

        void loadHealth();
        const timer = window.setInterval(() => void loadHealth(), 5000);

        return () => {
            alive = false;
            window.clearInterval(timer);
        };
    }, []);

    async function login() {
        try {
            setLoading(true);
            setError(null);

            const response = await fetch(`${apiBaseUrl}/api/candidate/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    exam_code: examCode.trim(),
                    registration_number: registrationNumber.trim(),
                    device_fingerprint: deviceFingerprint,
                }),
            });
            const data = await response.json() as CandidateLoginResponse | { message?: string };

            if (!response.ok) {
                throw new Error('message' in data && data.message ? data.message : `Login failed with status ${response.status}.`);
            }

            const success = data as CandidateLoginResponse;
            window.localStorage.setItem('alignex_attempt_token', success.attempt_token);
            setSession(success);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Candidate login failed.');
        } finally {
            setLoading(false);
        }
    }

    if (session) {
        if (submitted) {
            return <SubmittedPage session={session} summary={submissionSummary} />;
        }

        if (examPayload) {
            return (
                <ExamScreen
                    attemptToken={session.attempt_token}
                    onSubmitted={(summary) => {
                        setSubmissionSummary(summary);
                        setExamPayload(null);
                        setSubmitted(true);
                    }}
                    payload={examPayload}
                />
            );
        }

        return <InstructionsPage health={health} onExamLoaded={setExamPayload} session={session} />;
    }

    return (
        <main className="min-h-screen bg-lightBackground px-4 py-8 text-slateDark">
            <div className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-3xl flex-col justify-center">
                <div className="mb-8 text-center">
                    <img src="./images/logo.png" alt="AlignEx" className="mx-auto h-16 w-16 object-contain" />
                    <div className="mt-3 text-lg font-semibold text-primary">AlignEx Center Server</div>
                    <h1 className="mt-3 text-4xl font-bold tracking-normal text-slateDark">Candidate Login</h1>
                    <p className="mt-3 text-lg text-slate-500">Enter your exam code and registration number to begin.</p>
                </div>

                <section className="rounded-md border border-border bg-white p-6 shadow-sm">
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-lightBackground px-4 py-3">
                        <div>
                            <div className="text-sm font-medium text-slate-500">Center Server</div>
                            <div className="font-semibold text-slateDark">AlignEx Offline CBT Center</div>
                        </div>
                        <div className="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold">
                            {health?.serverStatus === 'online' ? (
                                <CheckCircle2 className="h-5 w-5 text-success" />
                            ) : (
                                <AlertTriangle className="h-5 w-5 text-danger" />
                            )}
                            {health?.serverStatus === 'online' ? 'Connected' : 'Checking connection'}
                        </div>
                    </div>

                    {error && (
                        <div className="mb-5 flex gap-3 rounded-md border border-danger/30 bg-danger/5 p-4 text-base text-danger">
                            <AlertTriangle className="mt-1 h-5 w-5 shrink-0" />
                            <div>{error}</div>
                        </div>
                    )}

                    <div className="space-y-5">
                        <label className="block">
                            <span className="text-base font-semibold text-slateDark">Exam Code</span>
                            <input
                                autoComplete="off"
                                className="mt-2 h-16 w-full rounded-md border border-border px-4 text-2xl font-semibold uppercase outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                                onChange={(event) => setExamCode(event.target.value.toUpperCase())}
                                placeholder="EXAM CODE"
                                value={examCode}
                            />
                        </label>

                        <label className="block">
                            <span className="text-base font-semibold text-slateDark">Registration Number</span>
                            <input
                                autoComplete="off"
                                className="mt-2 h-16 w-full rounded-md border border-border px-4 text-2xl font-semibold uppercase outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                                onChange={(event) => setRegistrationNumber(event.target.value.toUpperCase())}
                                placeholder="REGISTRATION NUMBER"
                                value={registrationNumber}
                            />
                        </label>

                        <button
                            className="flex h-16 w-full items-center justify-center gap-3 rounded-md bg-primary px-6 text-xl font-bold text-white transition-colors hover:bg-darkGreen disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={loading || examCode.trim().length === 0 || registrationNumber.trim().length === 0}
                            onClick={() => void login()}
                            type="button"
                        >
                            {loading && <Loader2 className="h-6 w-6 animate-spin" />}
                            Login
                        </button>
                    </div>
                </section>
            </div>
        </main>
    );
}

function SubmittedPage({ session, summary }: { session: CandidateLoginResponse; summary: SubmissionSummary | null }) {
    return (
        <main className="min-h-screen bg-lightBackground px-4 py-8 text-slateDark">
            <div className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-3xl flex-col justify-center">
                <section className="rounded-md border border-success/30 bg-white p-8 text-center shadow-sm">
                    <CheckCircle2 className="mx-auto h-14 w-14 text-success" />
                    <h1 className="mt-5 text-3xl font-bold tracking-normal">Exam Submitted</h1>
                    <p className="mt-3 text-lg text-slate-500">
                        Your exam has been submitted to the center server.
                    </p>
                    <div className="mt-6 rounded-md border border-border bg-lightBackground p-4 text-left">
                        <div className="text-sm font-semibold uppercase text-slate-500">Candidate</div>
                        <div className="mt-1 font-bold">{session.candidate.full_name}</div>
                        <div className="mt-1 text-sm text-slate-500">{session.candidate.registration_number}</div>
                        <div className="mt-4 text-sm font-semibold uppercase text-slate-500">Exam</div>
                        <div className="mt-1 font-bold">{session.exam.title}</div>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <InfoCard label="Answered" value={String(summary?.answered_count ?? 0)} />
                            <InfoCard label="Total Questions" value={String(summary?.total_questions ?? session.exam.total_questions)} />
                            <InfoCard label="Submitted" value={summary?.submitted_at ? formatSubmittedAt(summary.submitted_at) : 'Recorded'} />
                        </div>
                    </div>
                </section>
            </div>
        </main>
    );
}

function formatSubmittedAt(value: string): string {
    return new Date(value).toLocaleString();
}

function InstructionsPage({
    session,
    health,
    onExamLoaded,
}: {
    session: CandidateLoginResponse;
    health: HealthStatus | null;
    onExamLoaded: (payload: CandidateExamPayload) => void;
}) {
    const [loadingExam, setLoadingExam] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function startExam() {
        try {
            setLoadingExam(true);
            setError(null);
            const response = await fetch(`${apiBaseUrl}/api/candidate/exam`, {
                headers: {
                    Authorization: `Bearer ${session.attempt_token}`,
                },
            });
            const data = await response.json() as CandidateExamPayload | { message?: string };

            if (!response.ok) {
                throw new Error('message' in data && data.message ? data.message : `Unable to load questions. Status ${response.status}.`);
            }

            onExamLoaded(data as CandidateExamPayload);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Questions cannot be loaded.');
        } finally {
            setLoadingExam(false);
        }
    }

    return (
        <main className="min-h-screen bg-lightBackground px-4 py-8 text-slateDark">
            <div className="mx-auto max-w-4xl space-y-6">
                <div className="rounded-md border border-border bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="text-sm font-semibold uppercase text-primary">Exam Instructions</div>
                            <h1 className="mt-2 text-3xl font-bold tracking-normal">{session.exam.title}</h1>
                            <p className="mt-2 text-lg text-slate-500">{session.exam.organization_name}</p>
                        </div>
                        <div className="rounded-md border border-success/30 bg-success/10 px-3 py-2 text-sm font-semibold text-success">
                            Logged In
                        </div>
                    </div>
                </div>

                <section className="grid gap-4 md:grid-cols-3">
                    <InfoCard label="Candidate" value={session.candidate.full_name} />
                    <InfoCard label="Registration No." value={session.candidate.registration_number} />
                    <InfoCard label="Duration" value={`${session.exam.duration_minutes} minutes`} />
                    <InfoCard label="Total Questions" value={String(session.exam.total_questions)} />
                </section>

                <section className="rounded-md border border-border bg-white p-6 shadow-sm">
                    <div className="flex items-center gap-3">
                        <MonitorCheck className="h-6 w-6 text-primary" />
                        <h2 className="text-xl font-semibold">Before You Start</h2>
                    </div>
                    <ul className="mt-5 space-y-3 text-lg leading-8 text-slate-600">
                        <li>Remain on this computer for the duration of the exam.</li>
                        <li>Do not refresh, close, or switch away from the exam window unless instructed.</li>
                        <li>Your answers will be saved to the center server during the exam.</li>
                        <li>Read all questions carefully before submitting.</li>
                    </ul>
                    {error && (
                        <div className="mt-5 flex gap-3 rounded-md border border-danger/30 bg-danger/5 p-4 text-base text-danger">
                            <AlertTriangle className="mt-1 h-5 w-5 shrink-0" />
                            <div>{error}</div>
                        </div>
                    )}
                    <div className="mt-6 rounded-md border border-border bg-lightBackground p-4 text-sm text-slate-500">
                        Server connection: {health?.serverStatus === 'online' ? 'Connected' : 'Checking'}
                    </div>
                    <button
                        className="mt-6 flex h-16 w-full items-center justify-center gap-3 rounded-md bg-primary px-6 text-xl font-bold text-white transition-colors hover:bg-darkGreen disabled:cursor-not-allowed disabled:opacity-60"
                        disabled={loadingExam}
                        onClick={() => void startExam()}
                        type="button"
                    >
                        {loadingExam ? <Loader2 className="h-6 w-6 animate-spin" /> : <Play className="h-6 w-6" />}
                        Start Exam
                    </button>
                </section>
            </div>
        </main>
    );
}

function InfoCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-border bg-white p-5 shadow-sm">
            <div className="text-sm font-medium uppercase text-slate-500">{label}</div>
            <div className="mt-2 text-xl font-semibold text-slateDark">{value}</div>
        </div>
    );
}

function getDeviceFingerprint(): string {
    const existing = window.localStorage.getItem('alignex_device_fingerprint');

    if (existing) {
        return existing;
    }

    const next = crypto.randomUUID();
    window.localStorage.setItem('alignex_device_fingerprint', next);
    return next;
}
