import { CheckCircle2, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ExamAlertBanner } from '../components/exam/ExamAlertBanner';
import { ExamFooterNav } from '../components/exam/ExamFooterNav';
import { ExamTopBar } from '../components/exam/ExamTopBar';
import { QuestionCard } from '../components/exam/QuestionCard';
import { QuestionPalette } from '../components/exam/QuestionPalette';
import { SubmitConfirmDialog } from '../components/exam/SubmitConfirmDialog';
import { useExamSession } from '../context/ExamSessionContext';
import { useAntiCheatControls } from '../hooks/useAntiCheatControls';
import { useExamQuestionState } from '../hooks/useExamQuestionState';
import { useCandidateSocket } from '../hooks/useCandidateSocket';
import { ApiClientError, apiClient } from '../services/apiClient';
import type { ServerControlPayload } from '../services/candidateSocket';
import { enterExamMode, exitExamMode, isExamLockdownEnabled } from '../services/examMode';

export function ExamScreenPage({
    onSubmitted,
    onDisqualified,
    onExamClosed,
    onSubmitFailed,
}: {
    onSubmitted: () => void;
    onDisqualified: (reason?: string) => void;
    onExamClosed: () => void;
    onSubmitFailed: () => void;
}) {
    const session = useExamSession();
    const { questions, selectedAnswers, currentQuestionIndex, currentQuestion, setCurrentQuestion, selectAnswerLocally } = useExamQuestionState();
    const [submitDialogOpen, setSubmitDialogOpen] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [savingQuestionIds, setSavingQuestionIds] = useState<Set<string>>(new Set());
    const [syncingPending, setSyncingPending] = useState(false);
    const [autoSubmitState, setAutoSubmitState] = useState<'idle' | 'syncing' | 'submitting' | 'reconnecting' | 'submitted'>('idle');
    const [serverMessage, setServerMessage] = useState<string | null>(null);
    const [serverLockMessage, setServerLockMessage] = useState<string | null>(null);
    const [lockdownActive, setLockdownActive] = useState(false);
    const debounceTimers = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
    const saveVersions = useRef<Record<string, number>>({});
    const latestSelectedOptions = useRef<Record<string, string>>({});
    const retryInFlight = useRef(false);
    const autoSubmitStarted = useRef(false);
    const autoSubmitCancelled = useRef(false);
    const sessionRef = useRef(session);
    const questionStartedAt = useRef(Date.now());
    const pendingQuestionIds = useMemo(() => new Set([...Object.keys(session.pending_answers), ...savingQuestionIds]), [session.pending_answers, savingQuestionIds]);
    const failedQuestionIds = useMemo(
        () => (session.save_status === 'failed' ? new Set(Object.keys(session.pending_answers)) : new Set<string>()),
        [session.pending_answers, session.save_status],
    );
    const questionIds = useMemo(() => questions.map((question) => question.id), [questions]);
    const selectedOptionId = currentQuestion ? selectedAnswers[currentQuestion.id] : null;
    const lastQuestion = currentQuestionIndex >= questions.length - 1;
    const pendingAnswerCount = Object.keys(session.pending_answers).length;
    const answeredCount = questionIds.filter((questionId) => Boolean(selectedAnswers[questionId])).length;
    const unansweredCount = Math.max(0, questions.length - answeredCount);
    const remainingSeconds = Math.max(0, session.remaining_time_seconds ?? 0);
    const lessThanFiveMinutes = remainingSeconds > 0 && remainingSeconds < 300;
    const lessThanOneMinute = remainingSeconds > 0 && remainingSeconds < 60;
    const timeExpired = session.remaining_time_seconds !== null && remainingSeconds <= 0;
    const examLocked = Boolean(serverLockMessage) || timeExpired || session.submission_status === 'submitting' || session.submission_status === 'auto_submitted' || session.submission_status === 'submitted';
    const lockdownEnabled = isExamLockdownEnabled();
    const antiCheat = useAntiCheatControls({
        enabled: !examLocked && session.submission_status === 'idle',
    });

    useEffect(() => {
        questionStartedAt.current = Date.now();
    }, [currentQuestionIndex]);

    useEffect(() => {
        sessionRef.current = session;
    }, [session]);

    useEffect(() => {
        for (const [questionId, optionId] of Object.entries(selectedAnswers)) {
            if (optionId) {
                latestSelectedOptions.current[questionId] = optionId;
            }
        }
    }, [selectedAnswers]);

    useEffect(() => {
        return () => {
            Object.values(debounceTimers.current).forEach((timer) => clearTimeout(timer));
            autoSubmitCancelled.current = true;
        };
    }, []);

    useEffect(() => {
        let mounted = true;

        void enterExamMode()
            .then(() => {
                if (mounted && lockdownEnabled && window.alignexCandidate?.enterExamMode) {
                    setLockdownActive(true);
                }
            })
            .catch(() => {
                if (mounted) {
                    setLockdownActive(false);
                }
            });

        return () => {
            mounted = false;
            void exitExamMode();
        };
    }, [lockdownEnabled]);

    const retryPendingAnswers = useCallback(async () => {
        if (retryInFlight.current) {
            return false;
        }

        const currentSession = sessionRef.current;
        const pendingAnswers = Object.values(currentSession.pending_answers);

        if (pendingAnswers.length === 0) {
            return true;
        }

        if (!currentSession.server_url) {
            session.setConnectionStatus('disconnected');
            return false;
        }

        retryInFlight.current = true;
        setSyncingPending(true);
        session.markAnswerSyncing();
        session.setConnectionStatus(currentSession.connection_status === 'disconnected' ? 'reconnecting' : 'syncing');

        try {
            await apiClient.healthCheck(currentSession.server_url);
            session.setConnectionStatus('syncing');
        } catch {
            session.setConnectionStatus('disconnected');
            session.markAnswerFailed();
            retryInFlight.current = false;
            setSyncingPending(false);
            return false;
        }

        let allSynced = true;

        for (const answer of pendingAnswers) {
            const latestSelectedOption = sessionRef.current.selected_answers[answer.question_id];

            if (latestSelectedOption !== answer.selected_option_id) {
                continue;
            }

            try {
                const response = await apiClient.saveAnswer(answer);
                const stillLatest = sessionRef.current.selected_answers[answer.question_id] === answer.selected_option_id;

                if (stillLatest) {
                    session.markAnswerSaved({
                        question_id: answer.question_id,
                        selected_option_id: answer.selected_option_id,
                        saved_at: response.saved_at,
                    });
                    session.removePendingAnswer(answer.question_id);
                }
            } catch (caught) {
                allSynced = false;

                if (isNetworkError(caught)) {
                    session.setConnectionStatus('disconnected');
                    break;
                }
            }
        }

        if (allSynced) {
            session.setConnectionStatus('connected');
            session.markAnswersSynced();
        } else {
            session.markAnswerFailed();
        }

        retryInFlight.current = false;
        setSyncingPending(false);
        return allSynced;
    }, [session]);

    useEffect(() => {
        if (pendingAnswerCount === 0) {
            return;
        }

        const retryTimer = setInterval(() => {
            void retryPendingAnswers();
        }, 1_500);

        return () => clearInterval(retryTimer);
    }, [pendingAnswerCount, retryPendingAnswers]);

    useEffect(() => {
        if (pendingAnswerCount === 0) {
            return;
        }

        function retryWhenAvailable() {
            void retryPendingAnswers();
        }

        window.addEventListener('online', retryWhenAvailable);
        window.addEventListener('focus', retryWhenAvailable);

        return () => {
            window.removeEventListener('online', retryWhenAvailable);
            window.removeEventListener('focus', retryWhenAvailable);
        };
    }, [pendingAnswerCount, retryPendingAnswers]);

    useEffect(() => {
        if (session.remaining_time_seconds === null || session.remaining_time_seconds <= 0 || session.submission_status !== 'idle') {
            return;
        }

        const timer = setInterval(() => {
            session.tickRemainingTime();
        }, 1_000);

        return () => clearInterval(timer);
    }, [session]);

    useEffect(() => {
        if (!timeExpired || autoSubmitStarted.current) {
            return;
        }

        autoSubmitStarted.current = true;
        void autoSubmitAfterExpiry();
    }, [timeExpired]);

    const submitFromServerControl = useCallback(
        async (eventType: 'exam_closed' | 'force_submit', payload?: ServerControlPayload) => {
            if (sessionRef.current.submission_status === 'submitting' || sessionRef.current.submission_status === 'submitted' || sessionRef.current.submission_status === 'auto_submitted') {
                return;
            }

            setServerLockMessage(payload?.message ?? (eventType === 'exam_closed' ? 'This exam has been closed by the supervisor.' : 'The supervisor has requested submission.'));
            Object.values(debounceTimers.current).forEach((timer) => clearTimeout(timer));
            debounceTimers.current = {};
            setSavingQuestionIds(new Set());
            session.setSubmissionStatus('submitting');
            await sendCandidateEvent(eventType, payload?.message ?? payload?.reason);
            await retryPendingAnswers();

            try {
                const response = await apiClient.submitExam();
                session.completeSubmission(response);
                session.setConnectionStatus('connected');
                onSubmitted();
            } catch (caught) {
                session.setSubmissionStatus('failed');
                setSubmitError(toSubmitErrorMessage(caught));
                session.setConnectionStatus(isNetworkError(caught) ? 'disconnected' : 'reconnecting');
            }
        },
        [onSubmitted, retryPendingAnswers, session],
    );

    const handleExamClosed = useCallback(
        (payload?: ServerControlPayload) => {
            if (payload?.action === 'stop') {
                setServerLockMessage(payload.message ?? 'This exam has been closed by the supervisor.');
                void sendCandidateEvent('exam_closed', payload.message ?? payload.reason);
                onExamClosed();
                return;
            }

            void submitFromServerControl('exam_closed', payload);
        },
        [onExamClosed, submitFromServerControl],
    );

    const handleForceSubmit = useCallback(
        (payload?: ServerControlPayload) => {
            void submitFromServerControl('force_submit', payload);
        },
        [submitFromServerControl],
    );

    const handleDisqualified = useCallback(
        (payload?: ServerControlPayload) => {
            setServerLockMessage(payload?.message ?? payload?.reason ?? 'You have been disqualified.');
            void sendCandidateEvent('candidate_disqualified', payload?.message ?? payload?.reason);
            onDisqualified(payload?.message ?? payload?.reason);
        },
        [onDisqualified],
    );

    const handleServerMessage = useCallback((payload?: ServerControlPayload) => {
        setServerMessage(payload?.message ?? 'Message received from Center Server.');
    }, []);

    const handleSocketStatusChange = useCallback(
        (status: 'connected' | 'reconnecting' | 'disconnected') => {
            session.setConnectionStatus(status);
        },
        [session],
    );

    useCandidateSocket({
        attemptToken: session.attempt_token,
        candidateId: session.candidate?.id,
        enabled: Boolean(session.server_url && session.attempt_token && session.submission_status !== 'submitted' && session.submission_status !== 'auto_submitted'),
        examId: session.exam?.id,
        heartbeatEnabled: true,
        serverUrl: session.server_url,
        onDisqualified: handleDisqualified,
        onExamClosed: handleExamClosed,
        onForceSubmit: handleForceSubmit,
        onServerMessage: handleServerMessage,
        onStatusChange: handleSocketStatusChange,
    });

    async function autoSubmitAfterExpiry() {
        Object.values(debounceTimers.current).forEach((timer) => clearTimeout(timer));
        debounceTimers.current = {};
        setSavingQuestionIds(new Set());
        session.setSubmissionStatus('submitting');
        setAutoSubmitState('syncing');

        await sendCandidateEvent('time_expired');
        await sendCandidateEvent('auto_submit_started');

        while (!autoSubmitCancelled.current) {
            setAutoSubmitState('syncing');
            await retryPendingAnswers();

            try {
                setAutoSubmitState('submitting');
                const response = await apiClient.autoSubmitExam();
                await sendCandidateEvent('auto_submit_success');
                setAutoSubmitState('submitted');
                session.completeSubmission(response);
                session.setConnectionStatus('connected');
                onSubmitted();
                return;
            } catch (caught) {
                await sendCandidateEvent('auto_submit_failed', caught instanceof Error ? caught.message : 'Auto-submit failed.');
                setAutoSubmitState('reconnecting');
                session.setConnectionStatus(isNetworkError(caught) ? 'disconnected' : 'reconnecting');
                session.setSubmissionStatus('submitting');
                await delay(5_000);
            }
        }
    }

    async function handleManualSubmit() {
        if (examLocked) {
            return;
        }

        setSubmitError(null);
        session.setSubmissionStatus('submitting');

        try {
            await flushScheduledAnswerSaves();
            const pendingSynced = await retryPendingAnswers();

            if (!pendingSynced || Object.keys(sessionRef.current.pending_answers).length > 0) {
                session.setSubmissionStatus('failed');
                setSubmitError('Some answers are still syncing. Please try submit again in a moment.');
                return;
            }

            const response = await apiClient.submitExam();
            session.completeSubmission(response);
            session.setConnectionStatus('connected');
            setSubmitDialogOpen(false);
            onSubmitted();
        } catch (caught) {
            logDevelopmentSubmitError(caught);
            session.setSubmissionStatus('failed');
            setSubmitError(toSubmitErrorMessage(caught));
            onSubmitFailed();

            if (isNetworkError(caught)) {
                session.setConnectionStatus('disconnected');
            }
        }
    }

    function selectOption(optionId: string) {
        if (!currentQuestion || examLocked) {
            return;
        }

        selectAnswerLocally(currentQuestion.id, optionId);
        scheduleAnswerSave(currentQuestion.id, optionId);
    }

    function goPrevious() {
        if (examLocked) {
            return;
        }

        setCurrentQuestion(Math.max(0, currentQuestionIndex - 1));
    }

    function goNext() {
        if (examLocked) {
            return;
        }

        setCurrentQuestion(Math.min(questions.length - 1, currentQuestionIndex + 1));
    }

    function scheduleAnswerSave(questionId: string, optionId: string) {
        if (examLocked) {
            return;
        }

        const timeSpentSeconds = Math.max(1, Math.floor((Date.now() - questionStartedAt.current) / 1000));

        latestSelectedOptions.current[questionId] = optionId;
        saveVersions.current[questionId] = (saveVersions.current[questionId] ?? 0) + 1;
        const version = saveVersions.current[questionId];

        if (debounceTimers.current[questionId]) {
            clearTimeout(debounceTimers.current[questionId]);
        }

        session.addPendingAnswer({
            question_id: questionId,
            selected_option_id: optionId,
            time_spent_seconds: timeSpentSeconds,
        });
        setSavingQuestionIds((current) => new Set(current).add(questionId));
        session.markAnswerSaving();

        debounceTimers.current[questionId] = setTimeout(() => {
            void saveLatestAnswer(questionId, optionId, version, timeSpentSeconds);
        }, 100);
    }

    async function flushScheduledAnswerSaves() {
        const scheduledSaves = Object.entries(debounceTimers.current)
            .map(([questionId, timer]) => {
                clearTimeout(timer);
                const optionId = latestSelectedOptions.current[questionId];
                const version = saveVersions.current[questionId] ?? 0;

                return optionId ? saveLatestAnswer(questionId, optionId, version, Math.max(1, Math.floor((Date.now() - questionStartedAt.current) / 1000))) : null;
            })
            .filter((save): save is Promise<void> => Boolean(save));

        debounceTimers.current = {};

        await Promise.allSettled(scheduledSaves);
    }

    async function saveLatestAnswer(questionId: string, optionId: string, version: number, timeSpentSeconds: number) {
        try {
            const response = await apiClient.saveAnswer({
                question_id: questionId,
                selected_option_id: optionId,
                time_spent_seconds: timeSpentSeconds,
            });

            if (saveVersions.current[questionId] !== version || latestSelectedOptions.current[questionId] !== optionId) {
                return;
            }

            session.markAnswerSaved({
                question_id: questionId,
                selected_option_id: optionId,
                saved_at: response.saved_at,
            });
            session.setConnectionStatus('connected');
            session.removePendingAnswer(questionId);
            setSavingQuestionIds((current) => withoutQuestion(current, questionId));
        } catch (caught) {
            if (saveVersions.current[questionId] !== version || latestSelectedOptions.current[questionId] !== optionId) {
                return;
            }

            session.markAnswerFailed();
            session.addPendingAnswer({
                question_id: questionId,
                selected_option_id: optionId,
                time_spent_seconds: timeSpentSeconds,
            });
            if (isNetworkError(caught)) {
                session.setConnectionStatus('disconnected');
            }
            setSavingQuestionIds((current) => withoutQuestion(current, questionId));
        }
    }

    useEffect(() => {
        function handleExamShortcut(event: KeyboardEvent) {
            if (event.defaultPrevented || event.ctrlKey || event.altKey || event.metaKey || isEditableTarget(event.target)) {
                return;
            }

            const shortcut = event.key.toLowerCase();

            if (submitDialogOpen) {
                if (shortcut === 'y' && pendingAnswerCount === 0 && savingQuestionIds.size === 0 && sessionRef.current.submission_status !== 'submitting') {
                    event.preventDefault();
                    void handleManualSubmit();
                }
                return;
            }

            if (shortcut === 'n') {
                event.preventDefault();
                goNext();
                return;
            }

            if (shortcut === 'p') {
                event.preventDefault();
                goPrevious();
                return;
            }

            if (shortcut === 's') {
                event.preventDefault();
                setSubmitError(null);
                setSubmitDialogOpen(true);
                return;
            }

            const optionIndex = ['a', 'b', 'c', 'd', 'e'].indexOf(shortcut);

            if (optionIndex >= 0) {
                const expectedLabel = shortcut.toUpperCase();
                const option = currentQuestion?.options.find((item) => item.option_label.toUpperCase() === expectedLabel) ?? currentQuestion?.options[optionIndex];

                if (option) {
                    event.preventDefault();
                    selectOption(option.id);
                }
            }
        }

        window.addEventListener('keydown', handleExamShortcut);

        return () => window.removeEventListener('keydown', handleExamShortcut);
    }, [currentQuestion, currentQuestionIndex, examLocked, pendingAnswerCount, savingQuestionIds.size, submitDialogOpen]);

    return (
        <div className="min-h-screen bg-lightBackground text-slateDark">
            <ExamTopBar
                candidate={session.candidate}
                connectionStatus={session.connection_status}
                exam={session.exam}
                remainingSeconds={remainingSeconds}
                lockdownActive={lockdownActive}
            />

            <main className="mx-auto grid max-w-7xl gap-3 px-4 py-3 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div className="space-y-3">
                    {antiCheat.warningVisible && (
                        <ExamAlertBanner
                            actionLabel="Dismiss"
                            message={`${antiCheat.warningMessage} Warnings: ${antiCheat.warningCount}`}
                            tone="warning"
                            onAction={antiCheat.dismissWarning}
                        />
                    )}
                    {serverLockMessage && (
                        <ExamAlertBanner tone="danger" message={serverLockMessage} />
                    )}
                    {serverMessage && (
                        <ExamAlertBanner tone="info" message={serverMessage} />
                    )}
                    {timeExpired && (
                        <ExamAlertBanner tone="danger" message="Time is up. Your exam is being submitted automatically." />
                    )}
                    {!timeExpired && lessThanOneMinute && (
                        <ExamAlertBanner tone="danger" message="Less than 1 minute remaining." />
                    )}
                    {!timeExpired && !lessThanOneMinute && lessThanFiveMinutes && (
                        <ExamAlertBanner tone="warning" message="Less than 5 minutes remaining." />
                    )}
                    {currentQuestion ? (
                        <QuestionCard
                            question={currentQuestion}
                            questionNumber={currentQuestionIndex + 1}
                            selectedOptionId={selectedOptionId}
                            totalQuestions={questions.length}
                            disabled={examLocked}
                            onSelectOption={selectOption}
                        />
                    ) : (
                        <ExamAlertBanner tone="danger" message="No questions are loaded for this exam. Please contact the supervisor." />
                    )}
                </div>

                <QuestionPalette
                    currentIndex={currentQuestionIndex}
                    failedQuestionIds={failedQuestionIds}
                    pendingQuestionIds={pendingQuestionIds}
                    questionIds={questionIds}
                    selectedAnswers={selectedAnswers}
                    totalQuestions={questions.length}
                    disabled={examLocked}
                    onSelectQuestion={setCurrentQuestion}
                />
            </main>

            <ExamFooterNav
                canGoNext={!examLocked && currentQuestionIndex < questions.length - 1}
                canGoPrevious={!examLocked && currentQuestionIndex > 0}
                nextLabel={lastQuestion ? 'Review' : 'Next'}
                onNext={goNext}
                onPrevious={goPrevious}
                onSubmit={() => {
                    setSubmitError(null);
                    setSubmitDialogOpen(true);
                }}
                submitDisabled={examLocked}
                submitDisabledReason={timeExpired ? 'Time is up. Auto-submit is running.' : undefined}
            />

            <SubmitConfirmDialog
                answeredCount={answeredCount}
                candidateName={session.candidate?.full_name ?? 'Candidate'}
                error={submitError}
                examTitle={session.exam?.title ?? 'Exam'}
                open={submitDialogOpen}
                pendingCount={pendingAnswerCount + savingQuestionIds.size}
                submitting={session.submission_status === 'submitting'}
                unansweredCount={unansweredCount}
                onClose={() => {
                    if (session.submission_status !== 'submitting') {
                        setSubmitDialogOpen(false);
                    }
                }}
                onConfirm={() => void handleManualSubmit()}
                onRetrySync={() => void retryPendingAnswers()}
            />

            {timeExpired && (
                <AutoSubmitOverlay state={autoSubmitState} />
            )}
        </div>
    );
}

async function sendCandidateEvent(eventType: string, message?: string): Promise<void> {
    try {
        await apiClient.sendCandidateEvent({
            event_type: eventType,
            severity: eventType.includes('failed') ? 'warning' : 'info',
            message,
        });
    } catch {
        // Auto-submit must keep moving even if event logging is temporarily unavailable.
    }
}

function AutoSubmitOverlay({ state }: { state: 'idle' | 'syncing' | 'submitting' | 'reconnecting' | 'submitted' }) {
    const success = state === 'submitted';
    const message =
        state === 'reconnecting'
            ? 'Reconnecting to Center Server. Auto-submit will continue.'
            : state === 'syncing'
              ? 'Syncing pending answers before submission...'
              : state === 'submitted'
                ? 'Submitted successfully.'
                : 'Time is up. Your exam is being submitted automatically.';

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slateDark/75 p-4 backdrop-blur-sm">
            <div className="w-full max-w-lg rounded-md border border-border bg-white p-8 text-center shadow-2xl">
                {success ? (
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success/10 text-success">
                        <CheckCircle2 className="h-8 w-8" />
                    </div>
                ) : (
                    <Loader2 className="mx-auto h-14 w-14 animate-spin text-accentOrange" />
                )}
                <h2 className="mt-5 text-2xl font-bold text-slateDark">{success ? 'Submitted' : 'Auto-submit in progress'}</h2>
                <p className="mt-3 text-lg font-semibold text-slate-600">{message}</p>
                {!success && <p className="mt-2 text-sm font-semibold text-slate-500">Please keep this app open.</p>}
            </div>
        </div>
    );
}

function toSubmitErrorMessage(caught: unknown): string {
    return caught instanceof ApiClientError && (caught.code === 'network_error' || caught.code === 'timeout')
        ? 'Unable to reach the Center Server. Please try again.'
        : 'Unable to submit exam. Please try again.';
}

function logDevelopmentSubmitError(error: unknown): void {
    if (import.meta.env.DEV) {
        console.error('Candidate submit failed', error);
    }
}

function isNetworkError(caught: unknown): boolean {
    return caught instanceof ApiClientError && (caught.code === 'network_error' || caught.code === 'timeout');
}

function withoutQuestion(values: Set<string>, questionId: string): Set<string> {
    const next = new Set(values);
    next.delete(questionId);
    return next;
}

function isEditableTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const tagName = target.tagName.toLowerCase();

    return target.isContentEditable || tagName === 'input' || tagName === 'textarea' || tagName === 'select';
}

function delay(milliseconds: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}
