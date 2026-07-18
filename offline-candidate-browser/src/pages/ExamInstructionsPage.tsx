import { ArrowLeft, BookOpenCheck, Clock, Loader2, Play, RefreshCw, ShieldCheck, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { AlertBanner } from '../components/AlertBanner';
import { AppLogo } from '../components/AppLogo';
import { AppShell } from '../components/AppShell';
import { ConnectionStatus } from '../components/ConnectionStatus';
import { ErrorState } from '../components/ErrorState';
import { PageCard } from '../components/PageCard';
import { Button } from '../components/ui/button';
import { useExamSession } from '../context/ExamSessionContext';
import { useCandidateSocket } from '../hooks/useCandidateSocket';
import { ApiClientError, apiClient } from '../services/apiClient';
import type { ServerControlPayload } from '../services/candidateSocket';
import { getDeviceFingerprint } from '../services/deviceFingerprint';
import { isExamLockdownEnabled } from '../services/examMode';
import type { CandidateSavedAnswer, SavedAnswer } from '../types/api';
import { logDevelopmentError } from '../utils/logDevelopmentError';

export function ExamInstructionsPage({ onBackToLogin, onStartExam, onDisqualified }: { onBackToLogin: () => void; onStartExam: () => void; onDisqualified: (reason?: string) => void }) {
    const session = useExamSession();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [deviceVerified, setDeviceVerified] = useState(false);
    const lockdownEnabled = isExamLockdownEnabled();

    useEffect(() => {
        let mounted = true;

        getDeviceFingerprint()
            .then(() => {
                if (mounted) setDeviceVerified(true);
            })
            .catch(() => {
                if (mounted) setDeviceVerified(false);
            });

        return () => {
            mounted = false;
        };
    }, []);

    const handleSocketStatusChange = useCallback(
        (status: 'connected' | 'reconnecting' | 'disconnected') => {
            session.setConnectionStatus(status);
        },
        [session],
    );

    const handleDisqualified = useCallback(
        (payload?: ServerControlPayload) => {
            onDisqualified(payload?.message ?? payload?.reason);
        },
        [onDisqualified],
    );

    const handleExamClosed = useCallback((payload?: ServerControlPayload) => {
        setError(payload?.message ?? 'This exam has been closed by the supervisor.');
    }, []);

    const handleForceSubmit = useCallback((payload?: ServerControlPayload) => {
        setError(payload?.message ?? 'The supervisor has requested submission. Please contact the supervisor.');
    }, []);

    const handleServerMessage = useCallback((payload?: ServerControlPayload) => {
        setError(payload?.message ?? 'Message received from Center Server.');
    }, []);

    useCandidateSocket({
        attemptToken: session.attempt_token,
        candidateId: session.candidate?.id,
        enabled: Boolean(session.server_url && session.attempt_token),
        examId: session.exam?.id,
        heartbeatEnabled: false,
        serverUrl: session.server_url,
        onDisqualified: handleDisqualified,
        onExamClosed: handleExamClosed,
        onForceSubmit: handleForceSubmit,
        onServerMessage: handleServerMessage,
        onStatusChange: handleSocketStatusChange,
    });

    async function startExam() {
        try {
            setLoading(true);
            setError(null);
            const payload = await apiClient.getCandidateExam();
            session.setExamData({
                exam: payload.exam_with_questions,
                questions: payload.questions_with_options,
                saved_answers: toSavedAnswerMap(payload.saved_answers),
                selected_answers: toSelectedAnswerMap(payload.saved_answers),
                remaining_time_seconds: payload.remaining_time_seconds,
            });
            session.setConnectionStatus('connected');
            onStartExam();
        } catch (caught) {
            logDevelopmentError('Candidate exam load failed', caught);
            session.setConnectionStatus(caught instanceof ApiClientError && (caught.code === 'network_error' || caught.code === 'timeout') ? 'disconnected' : session.connection_status);
            setError(
                caught instanceof ApiClientError && (caught.code === 'network_error' || caught.code === 'timeout')
                    ? 'Unable to connect to Center Server. Please try again.'
                    : 'Unable to load exam. Please contact the supervisor.',
            );
        } finally {
            setLoading(false);
        }
    }

    return (
        <AppShell>
            <PageCard className="max-w-4xl">
                <div className="flex justify-center">
                    <AppLogo />
                </div>

                <div className="mt-8 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <BookOpenCheck className="h-9 w-9" />
                    </div>
                    <h1 className="mt-5 text-3xl font-bold text-slateDark">Exam Instructions</h1>
                    <p className="mt-2 text-lg text-slate-500">Read the details and rules carefully before starting.</p>
                </div>

                <div className="mt-8">
                    <AlertBanner
                        tone="warning"
                        title="Security reminder"
                        message="Remain on the exam window and keep this computer connected to the Center Server throughout the exam."
                    />
                </div>

                {lockdownEnabled && (
                    <div className="mt-4">
                        <AlertBanner
                            tone="warning"
                            title="Exam mode"
                            message="When you start, the app will enter fullscreen exam mode and record attempts to leave or open restricted tools."
                        />
                    </div>
                )}

                {session.candidate && session.exam ? (
                    <div className="mt-8 grid gap-4 md:grid-cols-2">
                        <InfoCard label="Candidate Name" value={session.candidate.full_name} />
                        <InfoCard label="Registration Number" value={session.candidate.registration_number} />
                        <InfoCard label="Exam Title" value={session.exam.title} />
                        <InfoCard label="Exam Code" value={session.exam.exam_code} />
                        <InfoCard label="Duration" value={`${session.exam.duration_minutes} minutes`} />
                        <InfoCard label="Total Questions" value={String(session.exam.total_questions ?? session.questions.length ?? '-')} />
                        <InfoCard className="md:col-span-2" icon={<Clock className="h-5 w-5 text-info" />} label="Remaining Time" value={formatSeconds(session.remaining_time_seconds ?? 0)} />
                    </div>
                ) : (
                    <div className="mt-8">
                        <ErrorState title="Candidate session missing" message="Please return to login and start a new candidate session." />
                    </div>
                )}

                <section className="mt-8 rounded-md border border-border bg-lightBackground p-5">
                    <h2 className="text-xl font-bold text-slateDark">Exam Rules</h2>
                    <ul className="mt-4 grid gap-3 text-lg font-medium text-slate-700">
                        {rules.map((rule) => (
                            <li key={rule} className="flex items-start gap-3">
                                <ShieldCheck className="mt-1 h-5 w-5 shrink-0 text-primary" />
                                <span>{rule}</span>
                            </li>
                        ))}
                    </ul>
                </section>

                {error && (
                    <div className="mt-8">
                        <ErrorState message={error} title="Exam could not load" onRetry={() => void startExam()} />
                    </div>
                )}

                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Button type="button" variant="outline" onClick={onBackToLogin} disabled={loading}>
                        <ArrowLeft className="h-4 w-4" />
                        Back to Login
                    </Button>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        {error && (
                            <Button type="button" variant="secondary" onClick={() => void startExam()} disabled={loading}>
                                <RefreshCw className="h-4 w-4" />
                                Retry
                            </Button>
                        )}
                        <Button type="button" size="lg" onClick={() => void startExam()} disabled={loading || !session.candidate || !session.exam}>
                            {loading ? <Loader2 className="h-5 w-5 animate-spin" /> : <Play className="h-5 w-5" />}
                            Start Exam
                        </Button>
                    </div>
                </div>

                <InstructionsFooter
                    connectionStatus={session.connection_status === 'connected' ? 'connected' : session.connection_status === 'disconnected' ? 'disconnected' : session.connection_status === 'reconnecting' ? 'reconnecting' : 'idle'}
                    deviceVerified={deviceVerified}
                />
            </PageCard>
        </AppShell>
    );
}

export function InstructionsFooter({ connectionStatus, deviceVerified }: { connectionStatus: 'connected' | 'reconnecting' | 'disconnected' | 'idle'; deviceVerified: boolean }) {
    return (
        <footer className="mt-8 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-between">
            <ConnectionStatus
                status={connectionStatus}
                label={connectionStatus === 'connected' ? 'Server connected' : connectionStatus === 'reconnecting' ? 'Reconnecting' : connectionStatus === 'disconnected' ? 'Server disconnected' : 'Server status unknown'}
            />
            <div
                className={`inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold ${
                    deviceVerified ? 'border-success/30 bg-success/5 text-success' : 'border-danger/30 bg-danger/5 text-danger'
                }`}
            >
                {deviceVerified ? <ShieldCheck className="h-4 w-4" /> : <TriangleAlert className="h-4 w-4" />}
                {deviceVerified ? 'Device verified' : 'Device verification failed'}
            </div>
        </footer>
    );
}

const rules = [
    'Do not close the app during exam.',
    'Do not disconnect from the network.',
    'Do not switch away from the exam window.',
    'Your answers will be saved automatically.',
    'Submit before time expires.',
];

function InfoCard({ label, value, icon, className = '' }: { label: string; value: string; icon?: ReactNode; className?: string }) {
    return (
        <div className={`rounded-md border border-border bg-white p-4 ${className}`}>
            <div className="flex items-center gap-2 text-sm font-bold uppercase text-slate-500">
                {icon}
                {label}
            </div>
            <div className="mt-2 break-words text-2xl font-bold text-slateDark">{value}</div>
        </div>
    );
}

function toSavedAnswerMap(savedAnswers: CandidateSavedAnswer[]): Record<string, SavedAnswer> {
    return Object.fromEntries(
        savedAnswers.map((answer) => [
            answer.question_id,
            {
                question_id: answer.question_id,
                selected_option_id: answer.option_ids[0] ?? null,
                saved_at: answer.saved_at,
            },
        ]),
    );
}

function toSelectedAnswerMap(savedAnswers: CandidateSavedAnswer[]): Record<string, string | null> {
    return Object.fromEntries(savedAnswers.map((answer) => [answer.question_id, answer.option_ids[0] ?? null]));
}

function formatSeconds(value: number): string {
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    const seconds = value % 60;
    return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
}
